<?php
namespace altomatic\providers;

use Craft;
use GuzzleHttp\Client;
use craft\elements\Asset;
use altomatic\Altomatic;

class AwsRekognitionProvider implements ProviderInterface
{
    public function generateAlt(Asset $asset, ?string $imageInput): ?string
    {
        $s = Altomatic::$plugin->getSettings();
        $key = $s->awsKey ?: getenv('ALTOMATIC_AWS_KEY');
        $secret = $s->awsSecret ?: getenv('ALTOMATIC_AWS_SECRET');
        $region = $s->awsRegion ?: getenv('ALTOMATIC_AWS_REGION') ?: 'us-east-1';

        if (!$key || !$secret || !$region || !$imageInput) {
            return null;
        }

        // Simple SigV4 signer inline (minimal); for production you might prefer AWS SDK.
        try {
            $endpoint = "https://rekognition.{$region}.amazonaws.com";
            $target = 'RekognitionService.DetectLabels';
            $image = $this->toAwsImage($imageInput);

            $payload = json_encode([
                'Image' => $image,
                'MaxLabels' => 5,
                'MinConfidence' => 70,
            ]);

            [$headers, $body] = $this->signRequest($endpoint, $region, $key, $secret, $payload, $target);

            $client = new Client(['timeout' => 30]);
            $res = $client->post($endpoint, [
                'headers' => $headers,
                'body' => $body
            ]);

            $data = json_decode((string)$res->getBody(), true);
            $labels = $data['Labels'] ?? [];
            if (!$labels) {
                return null;
            }
            $top = array_slice(array_map(fn($l) => $l['Name'], $labels), 0, 3);
            $phrase = implode(', ', $top);
            return $this->toAlt($phrase);
        } catch (\Throwable $e) {
            Craft::error('AWS Rekognition error: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    private function toAwsImage(string $input): array
    {
        if (str_starts_with($input, 'http')) {
            // Rekognition needs S3 or bytes; we’ll fall back to bytes.
        }
        $bytes = @file_get_contents($input);
        return ['Bytes' => $bytes ?: ''];
    }

    private function toAlt(string $labels): string
    {
        $alt = trim($labels, " \t\n\r\0\x0B,.");
        $alt = ucfirst($alt);
        return mb_substr($alt, 0, 180);
    }

    private function signRequest(string $endpoint, string $region, string $key, string $secret, string $payload, string $target): array
    {
        $service = 'rekognition';
        $host = parse_url($endpoint, PHP_URL_HOST);
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        $canonicalUri = '/';
        $canonicalQueryString = '';
        $canonicalHeaders = "content-type:application/x-amz-json-1.1\nhost:{$host}\nx-amz-date:{$amzDate}\nx-amz-target:{$target}\n";
        $signedHeaders = 'content-type;host;x-amz-date;x-amz-target';
        $payloadHash = hash('sha256', $payload);
        $canonicalRequest = "POST\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "{$algorithm} Credential={$key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $headers = [
            'Content-Type' => 'application/x-amz-json-1.1',
            'Host' => $host,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Target' => $target,
            'Authorization' => $authorization
        ];

        return [$headers, $payload];
    }
}
