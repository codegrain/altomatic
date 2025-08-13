<?php
namespace altomatic\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use GuzzleHttp\Client;
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

        $settings = Altomatic::$plugin->getSettings();

        $currentVal = $settings->targetFieldHandle === 'title' ? $asset->title : $asset->getFieldValue($settings->targetFieldHandle);
        if (!$settings->overwriteExisting && !empty($currentVal)) {
            return null;
        }

        $imgUrl = $asset->getUrl();
        if (!$imgUrl) {
            // fallback: local path (if accessible)
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
