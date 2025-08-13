<?php
namespace altomatic\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Asset;
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

        Craft::$app->getQueue()->push(new GenerateAltJob([
            'assetIds' => [$asset->id],
            'description' => "Altomatic: Generate ALT for asset {$asset->id}",
        ]));

        Craft::$app->getSession()->setNotice('Queued ALT generation.');
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

        Craft::$app->getQueue()->push(new GenerateAltJob([
            'assetIds' => [$asset->id],
            'description' => "Altomatic: Generate ALT for asset {$asset->id}",
        ]));

        Craft::$app->getSession()->setNotice('Queued ALT generation.');
        // honor (now signed) redirect, otherwise fall back
        return $this->redirectToPostedUrl($asset) ?: $this->redirect($asset->getCpEditUrl() ?? '/admin/assets');
    }

    public function actionQueueAll(): Response
    {
        $this->requirePermission('altomatic:generate');

        $ids = Asset::find()->kind('image')->status(null)->ids();
        $chunks = array_chunk($ids, 200);
        $queue = Craft::$app->getQueue();

        foreach ($chunks as $i => $chunk) {
            $queue->push(new GenerateAltJob([
                'assetIds' => $chunk,
                'description' => "Altomatic: Generate ALT (batch ".($i+1)."/".count($chunks).")",
            ]));
        }

        Craft::$app->getSession()->setNotice('Queued ALT generation for all images.');
        return $this->asJson(['ok' => true]);
    }
}