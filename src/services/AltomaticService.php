<?php
namespace altomatic\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use altomatic\Altomatic;
use altomatic\providers\ProviderInterface;
use altomatic\providers\OpenAIProvider;
use altomatic\providers\GoogleVisionProvider;
use altomatic\providers\AwsRekognitionProvider;
use altomatic\providers\AzureVisionProvider;

class AltomaticService extends Component
{
    // --- Public API ----------------------------------------------------------

    public function generateForAsset(Asset $asset): ?string
    {
        if ($asset->kind !== Asset::KIND_IMAGE) {
            return null;
        }

        // If not configured, bail early (extra safety)
        $errors = [];
        if (!$this->isConfigured($errors)) {
            Craft::warning('Altomatic not configured: ' . implode('; ', $errors), __METHOD__);
            return null;
        }

        $settings = Altomatic::$plugin->getSettings();

        $currentVal = $settings->targetFieldHandle === 'title'
            ? $asset->title
            : $asset->getFieldValue($settings->targetFieldHandle);

        if (!$settings->overwriteExisting && !empty($currentVal)) {
            return null;
        }

        $imgUrl = $asset->getUrl();
        if (!$imgUrl) {
            $imgUrl = $this->getLocalFilePath($asset);
        }

        $provider = $this->getProvider();
        $alt = $provider->generateAlt($asset, $imgUrl);

        if (!$alt) {
            return null;
        }

        // Truncate to a reasonable ALT length
        $alt = trim(mb_substr($alt, 0, 180));

        if ($settings->targetFieldHandle === 'title') {
            $asset->title = $alt;
        } else {
            $asset->setFieldValue($settings->targetFieldHandle, $alt);
        }

        Craft::$app->getElements()->saveElement($asset, true, true, false);
        return $alt;
    }

    public function getProvider(): ProviderInterface
    {
        $settings = Altomatic::$plugin->getSettings();
        return match ($settings->provider) {
            'google' => new GoogleVisionProvider(),
            'aws'    => new AwsRekognitionProvider(),
            'azure'  => new AzureVisionProvider(),
            default  => new OpenAIProvider(),
        };
    }

    /** Validate configuration for the selected provider. */
    public function isConfigured(?array &$errors = null): bool
    {
        $errors = $errors ?? [];
        $s = Altomatic::$plugin->getSettings();

        if (!$s->targetFieldHandle) {
            $errors[] = 'Target field is not selected.';
        }

        switch ($s->provider) {
            case 'google':
                $key = $s->googleApiKey ?: getenv('ALTOMATIC_GOOGLE_API_KEY');
                if (!$key) $errors[] = 'Google API Key is missing (set in settings or ALTOMATIC_GOOGLE_API_KEY).';
                break;
            case 'aws':
                $key = $s->awsKey ?: getenv('ALTOMATIC_AWS_KEY');
                $sec = $s->awsSecret ?: getenv('ALTOMATIC_AWS_SECRET');
                $reg = $s->awsRegion ?: getenv('ALTOMATIC_AWS_REGION');
                if (!$key || !$sec) $errors[] = 'AWS credentials are missing (AWS Key/Secret).';
                if (!$reg) $errors[] = 'AWS region is missing.';
                break;
            case 'azure':
                $ep  = $s->azureEndpoint ?: getenv('ALTOMATIC_AZURE_ENDPOINT');
                $key = $s->azureKey ?: getenv('ALTOMATIC_AZURE_KEY');
                if (!$ep || !$key) $errors[] = 'Azure endpoint/key are missing.';
                break;
            default: // openai
                $key = $s->openAiApiKey ?: getenv('ALTOMATIC_OPENAI_API_KEY');
                if (!$key) $errors[] = 'OpenAI API Key is missing (set in settings or ALTOMATIC_OPENAI_API_KEY).';
                break;
        }

        return empty($errors);
    }

    /** Quick stats for dashboard. */
    public function getStats(): array
    {
        $s = Altomatic::$plugin->getSettings();
        $total = Asset::find()->kind('image')->status(null)->count();

        // Count with ALT
        $with = 0;
        if ($total > 0) {
            $ids = Asset::find()->kind('image')->status(null)->ids();
            foreach ($ids as $id) {
                /** @var ?Asset $a */
                $a = Craft::$app->getElements()->getElementById($id, Asset::class);
                if (!$a) continue;

                $val = ($s->targetFieldHandle === 'title')
                    ? (string)$a->title
                    : (string)$a->getFieldValue($s->targetFieldHandle);

                if (trim($val) !== '') {
                    $with++;
                }
            }
        }

        $without = max(0, $total - $with);
        return ['total' => $total, 'withAlt' => $with, 'withoutAlt' => $without];
    }

    /** Lightweight action log. */
    public function logAction(string $action, ?int $assetId = null, ?int $count = null, ?string $notes = null): void
    {
        try {
            $this->ensureLogTable();
            $db = Craft::$app->getDb();
            $db->createCommand()->insert('{{%altomatic_log}}', [
                'userId'    => Craft::$app->getUser()->getIdentity()?->id,
                'action'    => $action,
                'assetId'   => $assetId,
                'count'     => $count,
                'notes'     => $notes,
                'createdAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ])->execute();
        } catch (\Throwable $e) {
            Craft::error('Altomatic logAction error: ' . $e->getMessage(), __METHOD__);
        }
    }

    /** Recent logs for dashboard. */
    public function getRecentLogs(int $limit = 50): array
    {
        try {
            $this->ensureLogTable();
            $db = Craft::$app->getDb();
            return $db->createCommand(
                'SELECT l.*, u.username, u.email FROM {{%altomatic_log}} l LEFT JOIN {{%users}} u ON u.id = l.userId ORDER BY l.id DESC LIMIT :lim',
                [':lim' => $limit]
            )->queryAll();
        } catch (\Throwable $e) {
            Craft::error('Altomatic getRecentLogs error: ' . $e->getMessage(), __METHOD__);
            return [];
        }
    }

    /** Ensure log table exists (very small, plugin-scoped). */
    public function ensureLogTable(): void
    {
        $db = Craft::$app->getDb();
        $schema = $db->getSchema()->getTableSchema('{{%altomatic_log}}', true);
        if ($schema) {
            return;
        }
        $db->createCommand()->createTable('{{%altomatic_log}}', [
            'id'        => $db->getSchema()->createColumnSchemaBuilder('pk'),
            'userId'    => $db->getSchema()->createColumnSchemaBuilder('integer')->null(),
            'action'    => $db->getSchema()->createColumnSchemaBuilder('string')->notNull(),
            'assetId'   => $db->getSchema()->createColumnSchemaBuilder('integer')->null(),
            'count'     => $db->getSchema()->createColumnSchemaBuilder('integer')->null(),
            'notes'     => $db->getSchema()->createColumnSchemaBuilder('text')->null(),
            'createdAt' => $db->getSchema()->createColumnSchemaBuilder('datetime')->notNull(),
        ])->execute();
        $db->createCommand()->createIndex(null, '{{%altomatic_log}}', ['createdAt'])->execute();
        $db->createCommand()->createIndex(null, '{{%altomatic_log}}', ['assetId'])->execute();
    }

    // --- Internals -----------------------------------------------------------

    private function getLocalFilePath(Asset $asset): ?string
    {
        try {
            $fs = $asset->getVolume()->getFs();
            $path = $asset->getPath();
            if (method_exists($fs, 'getRootPath') && $fs->getRootPath()) {
                return rtrim($fs->getRootPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
            }
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
        return null;
    }
}