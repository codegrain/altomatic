<?php
namespace altomatic;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use yii\base\Event;

use altomatic\models\Settings;
use altomatic\assetbundles\cp\CpAssetBundle;
use altomatic\elements\actions\GenerateAltForAssets;
use altomatic\services\AltomaticService;
use altomatic\traits\LoggingTrait;

/**
 * @property-read AltomaticService $altomaticService
 */
class Altomatic extends Plugin
{
    use LoggingTrait;
    
    public bool $hasCpSettings = true;
    public bool $hasCpSection  = true;
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
            $event->rules['altomatic/generate/queue-all'] = 'altomatic/generate/queue-all';
        });

        // permissions
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function (RegisterUserPermissionsEvent $event) {
            $event->permissions['Altomatic'] = [
                'altomatic:generate' => ['label' => Craft::t('app', 'Generate Alt text')],
                'altomatic:settings' => ['label' => Craft::t('app', 'Manage Altomatic settings')],
            ];
        });

        // element actions
        Event::on(Asset::class, Element::EVENT_REGISTER_ACTIONS, function (RegisterElementActionsEvent $event) {
            $event->actions[] = GenerateAltForAssets::class;
        });

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getView()->registerAssetBundle(CpAssetBundle::class);
        }

        // make sure log table exists
        try {
            $this->getAltomaticService()->ensureLogTable();
        } catch (\Throwable $e) {
            $this->logError('Altomatic ensureLogTable error: ' . $e->getMessage());
        }
    }

    // Top-level nav with subnav
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Altomatic';
        $item['url']   = 'altomatic';
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
        $this->requireAdminOrPermission('altomatic:settings');
        return Craft::$app->controller->renderTemplate('altomatic/settings', [
            'settings' => $this->getSettings(),
            'fieldOptions' => [],
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