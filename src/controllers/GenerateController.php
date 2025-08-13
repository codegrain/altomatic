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

    public function actionGenerateForAsset(int $assetId): \yii\web\Response
    {
        $this->requirePermission('altomatic:generate');

        /** @var ?Asset $asset */
        $asset = Craft::$app->getElements()->getElementById($assetId, Asset::class);
        if (!$asset) {
            throw new BadRequestHttpException('Asset not found.');
        }

        Altomatic::$plugin->altomaticService->generateForAsset($asset);

        Craft::$app->getSession()->setNotice('ALT generated (if needed).');
        return $this->redirectToPostedUrl($asset) ?: $this->redirect(Craft::$app->getRequest()->getReferrer() ?? '/admin/assets');
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
