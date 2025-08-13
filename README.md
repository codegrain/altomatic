# Altomatic

Altomatic generates ALT text for Craft CMS 5 image assets via OpenAI, Google Vision, AWS Rekognition, or Azure Vision.

## Features
- Per-Asset button on edit screen
- Toolbar button on /admin/assets to queue "all images"
- Bulk Element Action for selected assets
- Settings: provider, API credentials, mapping to target field, overwrite toggle
- Queue jobs + chunking for performance
- Permissions

## Install
1. Copy to `craft/plugins/altomatic` or install from VCS.
2. `composer install`
3. Enable the plugin in the Craft CP.
4. Configure **Settings → Altomatic**.

## Environment variables
```
ALTOMATIC_OPENAI_API_KEY="..."
ALTOMATIC_GOOGLE_API_KEY="..."
ALTOMATIC_AWS_KEY="..."
ALTOMATIC_AWS_SECRET="..."
ALTOMATIC_AWS_REGION="us-east-1"
ALTOMATIC_AZURE_ENDPOINT="https://<your-endpoint>.cognitiveservices.azure.com/"
ALTOMATIC_AZURE_KEY="..."
```

## Notes
- Choose the Asset field that should store ALT text (Plain Text recommended).
- If you prefer to store it in the Asset Title, select `title` in settings.