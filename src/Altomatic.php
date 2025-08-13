<?php
namespace altomatic;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\helpers\Html as HtmlHelper;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use yii\base\Event;

use altomatic\models\Settings;
use altomatic\assetbundles\cp\CpAssetBundle;
use altomatic\elements\actions\GenerateAltForAssets;
use altomatic\services\AltomaticService;

/**
 * @property-read AltomaticService $altomaticService
 */
class Altomatic extends Plugin
{
    public bool $hasCpSettings = true;
    public static Altomatic $plugin;

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'altomaticService' => AltomaticService::class,
        ]);

        // routes
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['altomatic'] = 'altomatic/dashboard/index';
            $event->rules['altomatic/dashboard'] = 'altomatic/dashboard/index';
            $event->rules['altomatic/settings'] = 'altomatic/dashboard/settings';
            $event->rules['altomatic/generate/asset/<assetId:\d+>'] = 'altomatic/generate/generate-for-asset';
            $event->rules['altomatic/generate/asset'] = 'altomatic/generate/generate-for-asset';
            $event->rules['altomatic/generate/queue-all'] = 'altomatic/generate/queue-all';
        });

        // permissions
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function (RegisterUserPermissionsEvent $event) {
            $event->permissions['Altomatic'] = [
                'altomatic:generate' => ['label' => Craft::t('app', 'Generate ALT text')],
                'altomatic:settings' => ['label' => Craft::t('app', 'Manage Altomatic settings')],
            ];
        });

        // bulk action
        Event::on(Asset::class, Element::EVENT_REGISTER_ACTIONS, function (RegisterElementActionsEvent $event) {
            $event->actions[] = GenerateAltForAssets::class;
        });

        // per-asset sidebar section
        Event::on(Asset::class, Element::EVENT_DEFINE_SIDEBAR_HTML, static function (DefineHtmlEvent $event) {
            if (!Craft::$app->getUser()->checkPermission('altomatic:generate')) {
                return;
            }
            $asset = $event->sender;
            if (!$asset instanceof Asset || !$asset->id || $asset->kind !== Asset::KIND_IMAGE) {
                return;
            }

            $service = Altomatic::$plugin->altomaticService;
            $errors = [];
            $isConfigured = $service->isConfigured($errors);

            $dashboardUrl = UrlHelper::cpUrl('altomatic/dashboard');
            $settingsUrl  = UrlHelper::cpUrl('altomatic/settings');

            $settings = Altomatic::$plugin->getSettings();
            $providerLabel = [
                'openai' => 'OpenAI',
                'google' => 'Google Vision',
                'aws'    => 'AWS Rekognition',
                'azure'  => 'Azure Vision',
            ][$settings->provider ?? 'openai'] ?? 'OpenAI';

            $html = '<div class="meta"><div class="field"><div class="heading"><label>Altomatic</label></div>';
            $html .= '<div class="instructions"><p>Provider: <strong>' . HtmlHelper::encode($providerLabel) . '</strong> • Target: <code>alt</code></p></div>';

            if ($isConfigured) {
                $postUrl  = UrlHelper::actionUrl('altomatic/generate/queue-asset');
                $redirect = $asset->getCpEditUrl();
                $signedRedirect = Craft::$app->getSecurity()->hashData($redirect);
                $label    = Craft::t('app', 'Generate ALT with Altomatic');

                $form  = HtmlHelper::beginForm($postUrl, 'post', ['class' => 'mt-2']);
                $form .= HtmlHelper::csrfInput();
                $form .= HtmlHelper::hiddenInput('assetId', (string)$asset->id);
                $form .= HtmlHelper::hiddenInput('redirect', $signedRedirect);
                $form .= HtmlHelper::tag('button', $label, ['class' => 'btn submit fullwidth']);
                $form .= HtmlHelper::endForm();

                $html .= $form;
                $html .= '<p class="light" style="margin-top:6px"><a href="' . HtmlHelper::encode($dashboardUrl) . '">Open Altomatic Dashboard</a> • <a href="' . HtmlHelper::encode($settingsUrl) . '">Settings</a></p>';
            } else {
                $html .= '<div class="warning" style="margin-top:6px"><p><strong>Altomatic is not configured.</strong></p>';
                if ($errors) {
                    $html .= '<ul class="errors" style="margin:6px 0 0 1em;">';
                    foreach ($errors as $e) $html .= '<li>' . HtmlHelper::encode($e) . '</li>';
                    $html .= '</ul>';
                }
                $html .= '<p class="light" style="margin-top:6px"><a href="' . HtmlHelper::encode($settingsUrl) . '">Configure in Settings</a> • <a href="' . HtmlHelper::encode($dashboardUrl) . '">View Dashboard</a></p></div>';
            }

            $html .= '</div></div>';
            $event->html .= $html;
        });

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getView()->registerAssetBundle(CpAssetBundle::class);
        }

        // make sure log table exists
        try {
            $this->getAltomaticService()->ensureLogTable();
        } catch (\Throwable $e) {
            Craft::error('Altomatic ensureLogTable error: ' . $e->getMessage(), __METHOD__);
        }
    }

    // Top-level nav with subnav
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Altomatic';
        $item['url'] = 'altomatic/dashboard';
        $item['subnav'] = [
            'dashboard' => ['label' => 'Dashboard', 'url' => 'altomatic/dashboard'],
            'settings'  => ['label' => 'Settings',  'url' => 'altomatic/settings'],
        ];
        return $item;
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    public function getSettingsResponse(): mixed
    {
        // keep plugin settings page functional, but primary entry is our own nav item
        $this->requireAdminOrPermission('altomatic:settings');

        $fieldsService = Craft::$app->getFields(); // left intact in case you reintroduce fields later

        return Craft::$app->controller->renderTemplate('altomatic/settings', [
            'settings' => $this->getSettings(),
            'fieldOptions' => [],  // target field removed
            'title' => 'Altomatic Settings',
        ]);
    }

    private function requireAdminOrPermission(string $permission): void
    {
        $user = Craft::$app->getUser();
        if (!$user->getIsAdmin() && !$user->checkPermission($permission)) {
            throw new \yii\web\ForbiddenHttpException('Insufficient permissions.');
        }
    }

    public function getAltomaticService(): AltomaticService
    {
        return $this->get('altomaticService');
    }
}