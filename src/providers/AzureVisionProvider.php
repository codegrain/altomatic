<?php
namespace altomatic\providers;

use Craft;
use GuzzleHttp\Client;
use craft\elements\Asset;
use altomatic\Altomatic;

class AzureVisionProvider implements ProviderInterface
{
    public function generateAlt(Asset $asset, ?string $imageInput): ?string
    {
        $s = Altomatic::$plugin->getSettings();
        $endpoint = rtrim($s->azureEndpoint ?: getenv('ALTOMATIC_AZURE_ENDPOINT') ?: '', '/') . '/vision/v3.2/describe';
        $key = $s->azureKey ?: getenv('ALTOMATIC_AZURE_KEY');

        if (!$endpoint || !$key || !$imageInput) {
            return null;
        }

        try {
            $client = new Client(['timeout' => 30]);
            $body = str_starts_with($imageInput, 'http')
                ? ['url' => $imageInput]
                : ['data' => base64_encode(@file_get_contents($imageInput) ?: '')]; // Some regions support binary body; safest is URL, else data

            $res = $client->post($endpoint, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'query' => ['maxCandidates' => 1, 'language' => 'en']
            ]);

            $data = json_decode((string)$res->getBody(), true);
            $caption = $data['description']['captions'][0]['text'] ?? null;
            return $caption ? mb_substr(trim($caption, " ."), 0, 180) : null;
        } catch (\Throwable $e) {
            Craft::error('Azure Vision error: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}
