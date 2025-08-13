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
use craft\helpers\Html as HtmlHelper; // added
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

        // per-asset sidebar button
        Event::on(Asset::class, Element::EVENT_DEFINE_SIDEBAR_HTML, static function (DefineHtmlEvent $event) {
            if (!Craft::$app->getUser()->checkPermission('altomatic:generate')) {
                return;
            }
            $asset = $event->sender;
            if (!$asset instanceof Asset || !$asset->id || $asset->kind !== Asset::KIND_IMAGE) {
                return;
            }

            // POST action with CSRF + **signed** redirect to avoid 400
            $postUrl  = UrlHelper::actionUrl('altomatic/generate/queue-asset');
            $redirect = $asset->getCpEditUrl();
            $signedRedirect = Craft::$app->getSecurity()->hashData($redirect); // <-- signed

            $label = Craft::t('app', 'Generate ALT with Altomatic');

            $form  = HtmlHelper::beginForm($postUrl, 'post');
            $form .= HtmlHelper::csrfInput();
            $form .= HtmlHelper::hiddenInput('assetId', (string)$asset->id);
            $form .= HtmlHelper::hiddenInput('redirect', $signedRedirect); // <-- use signed value
            $form .= HtmlHelper::tag('button', $label, ['class' => 'btn fullwidth']); // keep style neutral
            $form .= HtmlHelper::endForm();

            $event->html .= HtmlHelper::tag('div', $form, ['class' => 'meta']);
        });

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getView()->registerAssetBundle(CpAssetBundle::class);
        }
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