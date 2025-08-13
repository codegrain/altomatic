<?php
namespace altomatic\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public ?string $provider = 'openai'; // openai|google|aws|azure
    public bool $overwriteExisting = false;

    // OpenAI
    public ?string $openAiApiKey = null;
    public ?string $openAiModel = 'gpt-4o-mini';

    // Google
    public ?string $googleApiKey = null;

    // AWS
    public ?string $awsKey = null;
    public ?string $awsSecret = null;
    public ?string $awsRegion = 'us-east-1';

    // Azure
    public ?string $azureEndpoint = null;
    public ?string $azureKey = null;

    public function rules(): array
    {
        return [
            [['provider'], 'string'],
            [['overwriteExisting'], 'boolean'],
            [['openAiApiKey', 'openAiModel', 'googleApiKey', 'awsKey', 'awsSecret', 'awsRegion', 'azureEndpoint', 'azureKey'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'provider' => Craft::t('app', 'Provider'),
            'overwriteExisting' => Craft::t('app', 'Overwrite existing values'),
        ];
    }
}