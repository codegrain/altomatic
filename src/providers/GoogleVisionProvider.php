<?php
namespace altomatic\providers;

use Craft;
use GuzzleHttp\Client;
use craft\elements\Asset;
use altomatic\Altomatic;

class GoogleVisionProvider implements ProviderInterface
{
    public function generateAlt(Asset $asset, ?string $imageInput): ?string
    {
        $s = Altomatic::$plugin->getSettings();
        $apiKey = $s->googleApiKey ?: getenv('ALTOMATIC_GOOGLE_API_KEY');
        if (!$apiKey || !$imageInput) {
            return null;
        }

        $image = $this->toImageSource($imageInput);

        try {
            $client = new Client(['timeout' => 30, 'base_uri' => 'https://vision.googleapis.com/']);
            $res = $client->post("v1/images:annotate?key={$apiKey}", [
                'json' => [
                    'requests' => [[
                        'image' => $image,
                        'features' => [
                            ['type' => 'LABEL_DETECTION', 'maxResults' => 5],
                        ],
                    ]],
                ],
            ]);
            $body = json_decode((string)$res->getBody(), true);
            $labels = $body['responses'][0]['labelAnnotations'] ?? [];
            if (!$labels) {
                return null;
            }
            // Compose a concise phrase
            $top = array_slice(array_map(fn($l) => $l['description'], $labels), 0, 3);
            $phrase = implode(', ', $top);
            return $this->toAlt($phrase);
        } catch (\Throwable $e) {
            Craft::error('Google Vision error: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    private function toImageSource(string $input): array
    {
        if (str_starts_with($input, 'http')) {
            return ['source' => ['imageUri' => $input]];
        }
        $bytes = @file_get_contents($input);
        return ['content' => $bytes ? base64_encode($bytes) : ''];
    }

    private function toAlt(string $labels): string
    {
        // Make it read nicely as ALT text
        $alt = $labels;
        // Capitalize first, remove trailing punctuation, limit length
        $alt = trim($alt, " \t\n\r\0\x0B,.");
        $alt = ucfirst($alt);
        return mb_substr($alt, 0, 180);
    }
}
