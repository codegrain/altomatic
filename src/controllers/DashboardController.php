<?php
namespace altomatic\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use altomatic\Altomatic;

class DashboardController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionIndex(): Response
    {
        $this->requirePermission('altomatic:generate');

        $stats = Altomatic::$plugin->altomaticService->getStats();
        $logs  = Altomatic::$plugin->altomaticService->getRecentLogs(50);

        return $this->renderTemplate('altomatic/dashboard', [
            'title' => 'Altomatic Dashboard',
            'stats' => $stats,
            'logs'  => $logs,
        ]);
    }
}