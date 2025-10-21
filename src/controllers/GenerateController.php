<?php
namespace altomatic\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Asset;
use altomatic\Altomatic;
use altomatic\jobs\GenerateAltJob;
use altomatic\traits\LoggingTrait;
use yii\web\Response;

class GenerateController extends Controller
{
    use LoggingTrait;
    
    protected array|int|bool $allowAnonymous = false;

    public function actionQueueAll(): Response
    {
        $this->requirePermission('altomatic:generate');

        $errors = [];
        if (!Altomatic::$plugin->altomaticService->isConfigured($errors)) {
            Craft::$app->getResponse()->format = Response::FORMAT_JSON;
            return $this->asJson(['ok' => false, 'error' => 'Altomatic is not configured: ' . implode(' ', $errors)]);
        }

        $ids = Asset::find()->kind('image')->status(null)->ids();
        $chunks = array_chunk($ids, 200);
        $queue = Craft::$app->getQueue();

        foreach ($chunks as $i => $chunk) {
            $queue->push(new GenerateAltJob([
                'assetIds' => $chunk,
                'description' => "Altomatic: Generate Alt (batch ".($i+1)."/".count($chunks).")",
            ]));
        }

        Altomatic::$plugin->altomaticService->logAction('queue-all', null, count($ids));
        Craft::$app->getSession()->setNotice('Queued Alt generation for all images.');
        return $this->asJson(['ok' => true, 'queued' => count($ids)]);
    }
}