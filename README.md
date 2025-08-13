<p align="center">
  <img src="https://github.com/codegrain/altomatic/blob/main/src/icon.svg" width="96" height="96" alt="Altomatic icon">
</p>
<h1 align="center">Altomatic for Craft CMS</h1>

> [!IMPORTANT]
> Altomatic 1.0.2 for Craft CMS 5 writes directly to the native **Asset → Alternative Text** field (`$asset->alt`).  
> A top-level **Altomatic** section now appears in the Control Panel with **Dashboard** and **Settings**.

Altomatic generates accessible, high-quality ALT text for image assets using OpenAI, Google Vision, AWS Rekognition, or Azure Vision. It integrates into the Craft CP with a per-asset action, bulk tools, and a dashboard so editors can see coverage and activity at a glance.

## Features

- Per-asset button on the Asset edit screen that queues ALT generation for that image
- “Generate ALT for All” toolbar button on **Assets** index
- Bulk element action for selected assets
- Dashboard with totals: images, with ALT, without ALT
- Recent activity log: who queued what, for which assets
- Clear guardrails and error messages when provider credentials are missing
- Queue jobs with chunking for performance
- Fine-grained permissions:
  - `altomatic:generate`
  - `altomatic:settings`

## Requirements

- PHP 8.2+
- Craft CMS 5.0+
- Guzzle 7.8+

## Installation

### Via Composer (recommended)
```bash
composer require codegrain/altomatic
````

### Enable the plugin

1. Go to **Control Panel → Settings → Plugins** and enable **Altomatic**.
2. A new **Altomatic** item will appear in the left sidebar with **Dashboard** and **Settings**.

> \[!NOTE]
> If you’re installing from a private VCS repo, add it to your Composer repositories and then run the `composer require` command.

## Configuration

Open **Altomatic → Settings** and:

1. Choose a **Provider**: OpenAI, Google Vision, AWS Rekognition, or Azure Vision.
2. Enter credentials for the provider (or reference environment variables).
3. Decide whether to **Overwrite existing values**. When enabled, existing Alternative Text will be replaced.

### Environment variables

```bash
# OpenAI
ALTOMATIC_OPENAI_API_KEY="..."

# Google Vision
ALTOMATIC_GOOGLE_API_KEY="..."

# AWS Rekognition
ALTOMATIC_AWS_KEY="..."
ALTOMATIC_AWS_SECRET="..."
ALTOMATIC_AWS_REGION="us-east-1"

# Azure Vision
ALTOMATIC_AZURE_ENDPOINT="https://<your-endpoint>.cognitiveservices.azure.com/"
ALTOMATIC_AZURE_KEY="..."
```

> \[!IMPORTANT]
> If required credentials are missing, Altomatic will show a warning in the asset sidebar and block bulk actions, so editors aren’t misled by “success” messages when nothing can be generated.

## How it works

* Altomatic requests a concise description of the image from your selected provider.
* The result is trimmed and normalized, then saved to the native **Alternative Text** field (`$asset->alt`).
* If **Overwrite existing values** is off and Alternative Text already exists, the asset is skipped.

## Using Altomatic

### Per-asset

Open any image asset in the CP. In the sidebar’s **Altomatic** panel, click **Generate ALT with Altomatic**. This queues a job and refreshes the page once it’s queued.

### Bulk (selected assets)

From the **Assets** index, select images and choose **Generate ALT (Altomatic)** from element actions.

### Generate for all

On **/admin/assets**, use the **Generate ALT for All (Altomatic)** button in the toolbar. The plugin queues jobs in batches to avoid long-running requests.

### Dashboard

Go to **Altomatic → Dashboard** to see:

* Total images
* How many have Alternative Text
* How many are missing it
* A recent actions log with user, action, asset ID, counts, and timestamps

## Permissions

Grant these to the appropriate user groups or roles:

* `altomatic:generate` — allow per-asset, bulk, and “all images” actions
* `altomatic:settings` — allow editing provider settings and overwrite behavior

## Troubleshooting

* **“Altomatic is not configured” in the sidebar**
  Add the required API keys or env vars in **Altomatic → Settings**. The warning lists what’s missing.

* **No ALT is written after a generation job**
  Check the dashboard log for the action, confirm provider credentials, and verify the job queue is running.

* **Network or provider errors**
  Review Craft logs for provider error messages. Most exceptions are logged with helpful details.

## Security & Privacy

* Only the minimum data required to generate image descriptions is sent to the selected provider.
* If an image URL is not public, the plugin sends an inline base64 representation instead (when supported).

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for release notes. Current release: **1.0.2**.

## License

This plugin is distributed under a proprietary license. Contact the author for commercial terms or redistribution.
