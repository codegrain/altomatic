<?php
namespace altomatic\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Asset;
use altomatic\Altomatic;
use altomatic\jobs\GenerateAltJob;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class GenerateController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionGenerateForAsset(int $assetId): Response
    {
        $this->requirePermission('altomatic:generate');

        /** @var ?Asset $asset */
        $asset = Craft::$app->getElements()->getElementById($assetId, Asset::class);
        if (!$asset) {
            throw new BadRequestHttpException('Asset not found.');
        }

        $errors = [];
        if (!Altomatic::$plugin->altomaticService->isConfigured($errors)) {
            Craft::$app->getSession()->setError('Altomatic is not configured: ' . implode(' ', $errors));
            return $this->redirect($asset->getCpEditUrl() ?? '/admin/assets');
        }

        Craft::$app->getQueue()->push(new GenerateAltJob([
            'assetIds' => [$asset->id],
            'description' => "Altomatic: Generate ALT for asset {$asset->id}",
        ]));

        Altomatic::$plugin->altomaticService->logAction('queue-asset', $asset->id, 1);
        Craft::$app->getSession()->setNotice('Queued ALT generation (will populate the asset’s Alternative Text).');
        return $this->redirect($asset->getCpEditUrl() ?? '/admin/assets');
    }

    public function actionQueueAsset(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('altomatic:generate');

        $request = Craft::$app->getRequest();
        $assetId = (int)$request->getRequiredBodyParam('assetId');

        /** @var ?Asset $asset */
        $asset = Craft::$app->getElements()->getElementById($assetId, Asset::class);
        if (!$asset) {
            throw new BadRequestHttpException('Asset not found.');
        }

        $errors = [];
        if (!Altomatic::$plugin->altomaticService->isConfigured($errors)) {
            Craft::$app->getSession()->setError('Altomatic is not configured: ' . implode(' ', $errors));
            return $this->redirectToPostedUrl($asset) ?: $this->redirect($asset->getCpEditUrl() ?? '/admin/assets');
        }

        Craft::$app->getQueue()->push(new GenerateAltJob([
            'assetIds' => [$asset->id],
            'description' => "Altomatic: Generate ALT for asset {$asset->id}",
        ]));

        Altomatic::$plugin->altomaticService->logAction('queue-asset', $asset->id, 1);
        Craft::$app->getSession()->setNotice('Queued ALT generation (will populate the asset’s Alternative Text).');
        return $this->redirectToPostedUrl($asset) ?: $this->redirect($asset->getCpEditUrl() ?? '/admin/assets');
    }

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
                'description' => "Altomatic: Generate ALT (batch ".($i+1)."/".count($chunks).")",
            ]));
        }

        Altomatic::$plugin->altomaticService->logAction('queue-all', null, count($ids));
        Craft::$app->getSession()->setNotice('Queued ALT generation for all images.');
        return $this->asJson(['ok' => true, 'queued' => count($ids)]);
    }
}