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
    public function generateForAsset(Asset $asset): ?string
    {
        if ($asset->kind !== Asset::KIND_IMAGE) {
            return null;
        }

        $errors = [];
        if (!$this->isConfigured($errors)) {
            Craft::warning('Altomatic not configured: ' . implode('; ', $errors), __METHOD__);
            return null;
        }

        $settings = Altomatic::$plugin->getSettings();

        // always use native alt
        $currentVal = (string)$asset->alt;
        if (!$settings->overwriteExisting && trim($currentVal) !== '') {
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

        $alt = trim(mb_substr($alt, 0, 180));
        $asset->alt = $alt;

        Craft::$app->getElements()->saveElement($asset, true, true, false);
        return $alt;
    }

    public function getProvider(): ProviderInterface
    {
        $s = Altomatic::$plugin->getSettings();
        return match ($s->provider) {
            'google' => new GoogleVisionProvider(),
            'aws'    => new AwsRekognitionProvider(),
            'azure'  => new AzureVisionProvider(),
            default  => new OpenAIProvider(),
        };
    }

    public function isConfigured(?array &$errors = null): bool
    {
        $errors = $errors ?? [];
        $s = Altomatic::$plugin->getSettings();

        switch ($s->provider) {
            case 'google':
                $key = $s->googleApiKey ?: getenv('ALTOMATIC_GOOGLE_API_KEY');
                if (!$key) $errors[] = 'Google API Key is missing (ALTOMATIC_GOOGLE_API_KEY).';
                break;
            case 'aws':
                $key = $s->awsKey ?: getenv('ALTOMATIC_AWS_KEY');
                $sec = $s->awsSecret ?: getenv('ALTOMATIC_AWS_SECRET');
                $reg = $s->awsRegion ?: getenv('ALTOMATIC_AWS_REGION') ?: $s->awsRegion;
                if (!$key || !$sec) $errors[] = 'AWS credentials are missing.';
                if (!$reg) $errors[] = 'AWS region is missing.';
                break;
            case 'azure':
                $ep  = $s->azureEndpoint ?: getenv('ALTOMATIC_AZURE_ENDPOINT');
                $key = $s->azureKey ?: getenv('ALTOMATIC_AZURE_KEY');
                if (!$ep || !$key) $errors[] = 'Azure endpoint/key are missing.';
                break;
            default:
                $key = $s->openAiApiKey ?: getenv('ALTOMATIC_OPENAI_API_KEY');
                if (!$key) $errors[] = 'OpenAI API Key is missing (ALTOMATIC_OPENAI_API_KEY).';
                break;
        }

        return empty($errors);
    }

    public function getStats(): array
    {
        $total = Asset::find()->kind('image')->status(null)->count();

        $with = 0;
        if ($total > 0) {
            $ids = Asset::find()->kind('image')->status(null)->ids();
            foreach ($ids as $id) {
                /** @var ?Asset $a */
                $a = Craft::$app->getElements()->getElementById($id, Asset::class);
                if (!$a) continue;
                if (trim((string)$a->alt) !== '') $with++;
            }
        }
        $without = max(0, $total - $with);
        return ['total' => $total, 'withAlt' => $with, 'withoutAlt' => $without];
    }

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

    public function ensureLogTable(): void
    {
        $db = Craft::$app->getDb();
        $schema = $db->getSchema()->getTableSchema('{{%altomatic_log}}', true);
        if ($schema) return;

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