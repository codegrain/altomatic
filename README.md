<p align="center">
  <img src="https://github.com/codegrain/altomatic/blob/main/src/icon.svg" width="96" height="96" alt="Altomatic icon">
</p>
<h1 align="center">Altomatic for Craft CMS</h1>

AI-powered Alt text generation for Craft CMS 5 using OpenAI, Google Vision, AWS Rekognition, or Azure Vision. Automatically generates accessible Alt text and saves it to the native Asset → Alternative Text field.

## Key Features

- Generate Alt text for all images or selected assets
- Works with OpenAI, Google Vision, AWS Rekognition, and Azure Vision
- View stats and recent activity in the dashboard
- Processes images in the background using queues
- Control which users can generate Alt text

## Installation

```bash
composer require codegrain/altomatic
```

Enable in **Control Panel → Settings → Plugins**.

## Quick Setup

1. Go to **Altomatic → Settings**
2. Choose provider and enter API credentials
3. Configure environment variables (recommended):

```bash
# OpenAI (default)
ALTOMATIC_OPENAI_API_KEY="your-key"

# Google Vision
ALTOMATIC_GOOGLE_API_KEY="your-key"

# AWS Rekognition  
ALTOMATIC_AWS_KEY="your-key"
ALTOMATIC_AWS_SECRET="your-secret"
ALTOMATIC_AWS_REGION="us-east-1"

# Azure Vision
ALTOMATIC_AZURE_ENDPOINT="https://your-endpoint.cognitiveservices.azure.com/"
ALTOMATIC_AZURE_KEY="your-key"
```

## Usage

- **Bulk**: Assets index → select images → "Generate Alt" action
- **All images**: Assets toolbar → "Generate Alt for All" button  
- **Dashboard**: View stats and activity at **Altomatic → Dashboard**

## Requirements

- PHP 8.2+
- Craft CMS 5.0+
- Guzzle 7.8+

Current version: **1.0.3** | [Changelog](./CHANGELOG.md) | [License](./LICENSE.md)
