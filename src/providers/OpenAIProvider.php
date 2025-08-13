<?php
namespace altomatic\providers;

use Craft;
use GuzzleHttp\Client;
use craft\elements\Asset;
use altomatic\Altomatic;

class OpenAIProvider implements ProviderInterface
{
    public function generateAlt(Asset $asset, ?string $imageInput): ?string
    {
        $s = Altomatic::$plugin->getSettings();
        $apiKey = $s->openAiApiKey ?: getenv('ALTOMATIC_OPENAI_API_KEY');
        $model  = $s->openAiModel ?: 'gpt-4o-mini';

        if (!$apiKey || !$imageInput) {
            return null;
        }

        // If local path, convert to base64 data URL to send inline.
        $imagePayload = str_starts_with((string)$imageInput, 'http')
            ? ['type' => 'input_image', 'image_url' => ['url' => $imageInput]]
            : $this->encodeLocalAsInputImage($imageInput);

        $prompt = "Describe this image as concise ALT text (<= 125 characters), no emojis, no prefixes.";

        try {
            $client = new Client([
                'base_uri' => 'https://api.openai.com/v1/',
                'timeout'  => 60,
            ]);

            $res = $client->post('chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You write succinct, descriptive ALT attributes.'],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                $imagePayload,
                            ],
                        ],
                    ],
                    'temperature' => 0.2,
                    'max_tokens'  => 80,
                ],
            ]);

            $body = json_decode((string)$res->getBody(), true);
            return trim($body['choices'][0]['message']['content'] ?? '') ?: null;
        } catch (\Throwable $e) {
            Craft::error('OpenAI error: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    private function encodeLocalAsInputImage(string $path): array
    {
        if (!is_readable($path)) {
            return ['type' => 'input_image', 'image_url' => ['url' => '']];
        }
        $bytes = file_get_contents($path);
        $b64 = 'data:image/*;base64,' . base64_encode($bytes);
        return ['type' => 'input_image', 'image_url' => ['url' => $b64]];
    }
}
