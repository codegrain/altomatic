<?php
namespace altomatic;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\DefineElementEditorSidebarHtmlEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\UserPermissions;
use craft\events\RegisterUserPermissionsEvent;
use craft\web\UrlManager;
use yii\base\Event;
use altomatic\models\Settings;
use altomatic\assetbundles\cp\CpAssetBundle;
use altomatic\elements\actions\GenerateAltForAssets;

class Altomatic extends Plugin
{
    public bool $hasCpSettings = true;

    public static Altomatic $plugin;

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // CP routes
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['altomatic/generate/asset/<assetId:\d+>'] = 'altomatic/generate/generate-for-asset';
                $event->rules['altomatic/generate/queue-all'] = 'altomatic/generate/queue-all';
            }
        );

        // Permissions
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions['Altomatic'] = [
                    'altomatic:generate' => ['label' => Craft::t('app', 'Generate ALT text')],
                    'altomatic:settings' => ['label' => Craft::t('app', 'Manage Altomatic settings')],
                ];
            }
        );

        // Register element action for Assets
        Event::on(Asset::class, Element::EVENT_REGISTER_ACTIONS,
            function (RegisterElementActionsEvent $event) {
                $event->actions[] = GenerateAltForAssets::class;
            }
        );

        // Add a sidebar button on each Asset edit page
        Event::on(Asset::class, Element::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineElementEditorSidebarHtmlEvent $event) {
                if (!Craft::$app->getUser()->checkPermission('altomatic:generate')) {
                    return;
                }
                /** @var Asset $asset */
                $asset = $event->sender;
                if (!$asset->getId() || !$asset->kind || $asset->kind !== Asset::KIND_IMAGE) {
                    return;
                }
                $url = Craft::$app->getUrlManager()->createUrl(['altomatic/generate/asset', 'assetId' => $asset->id]);
                $btn = '<div class="meta"><a class="btn submit fullwidth" href="'.$url.'">' .
                    Craft::t('app', 'Generate ALT with Altomatic') . '</a></div>';
                $event->html .= $btn;
            }
        );

        // Register a small CP asset to inject a “Generate All” button on /admin/assets
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
}