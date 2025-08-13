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
            $event->rules['altomatic'] = 'altomatic/dashboard/index';                    // NEW: default to dashboard
            $event->rules['altomatic/dashboard'] = 'altomatic/dashboard/index';          // NEW: dashboard
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

        // per-asset sidebar section (nicer UI + config guard)
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
            $settingsUrl  = UrlHelper::cpUrl('settings/plugins/altomatic');

            // Container panel styled to match CP meta blocks
            $html = '<div class="meta">';
            $html .= '<div class="field">';
            $html .= '<div class="heading"><label>Altomatic</label></div>';

            // Small status line
            $settings = Altomatic::$plugin->getSettings();
            $providerLabel = [
                'openai' => 'OpenAI',
                'google' => 'Google Vision',
                'aws'    => 'AWS Rekognition',
                'azure'  => 'Azure Vision',
            ][$settings->provider ?? 'openai'] ?? 'OpenAI';

            $target = $settings->targetFieldHandle ?: 'title';
            $html .= '<div class="instructions"><p>Provider: <strong>' . HtmlHelper::encode($providerLabel) . '</strong> &nbsp;•&nbsp; Target: <code>' . HtmlHelper::encode($target) . '</code></p></div>';

            if ($isConfigured) {
                // Pretty primary button + helper links
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
                $html .= '<p class="light mt-1" style="margin-top:6px"><a href="' . HtmlHelper::encode($dashboardUrl) . '">Open Altomatic Dashboard</a></p>';
            } else {
                // Config warning with quick links
                $html .= '<div class="warning" style="margin-top:6px"><p><strong>Altomatic is not configured.</strong></p>';
                if ($errors) {
                    $html .= '<ul class="errors" style="margin:6px 0 0 1em;">';
                    foreach ($errors as $e) {
                        $html .= '<li>' . HtmlHelper::encode($e) . '</li>';
                    }
                    $html .= '</ul>';
                }
                $html .= '<p class="light" style="margin-top:6px"><a href="' . HtmlHelper::encode($settingsUrl) . '">Configure in Settings</a> &nbsp;•&nbsp; <a href="' . HtmlHelper::encode($dashboardUrl) . '">View Dashboard</a></p>';
                $html .= '</div>';
            }

            $html .= '</div></div>'; // field/meta
            $event->html .= $html;
        });

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getView()->registerAssetBundle(CpAssetBundle::class);
        }

        // Ensure the lightweight log table exists
        try {
            $this->getAltomaticService()->ensureLogTable();
        } catch (\Throwable $e) {
            Craft::error('Altomatic ensureLogTable error: ' . $e->getMessage(), __METHOD__);
        }
    }

    // Small CP nav so the dashboard is easy to find
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Altomatic';
        $item['url'] = 'altomatic';
        return $item;
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    public function getSettingsResponse(): mixed
    {
        $this->requireAdminOrPermission('altomatic:settings');

        $fieldsService = Craft::$app->getFields();
        $fieldOptions = [
            ['label' => '— Select field —', 'value' => ''],
            ['label' => 'Use Asset Title', 'value' => 'title'],
        ];
        
        foreach ($fieldsService->getAllFields() as $field) {
            if ($field instanceof \craft\fields\PlainText) {
                $fieldOptions[] = ['label' => $field->name . ' (' . $field->handle . ')', 'value' => $field->handle];
            }
        }

        return Craft::$app->controller->renderTemplate('altomatic/settings', [
            'settings' => $this->getSettings(),
            'fieldOptions' => $fieldOptions,
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