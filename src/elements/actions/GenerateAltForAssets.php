<?php
namespace altomatic\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use altomatic\Altomatic;
use altomatic\jobs\GenerateAltJob;

class GenerateAltForAssets extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('app', 'Generate ALT (Altomatic)');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        if (!Craft::$app->getUser()->checkPermission('altomatic:generate')) {
            $this->setMessage('Permission denied.');
            return false;
        }

        $ids = $query->ids();
        if (!$ids) {
            $this->setMessage('No assets selected.');
            return true;
        }

        Craft::$app->getQueue()->push(new GenerateAltJob([
            'assetIds' => $ids,
            'description' => 'Altomatic: Generate ALT for selection',
        ]));

        Altomatic::$plugin->altomaticService->logAction('queue-selected', null, count($ids));
        $this->setMessage('Queued ALT generation for selected assets.');
        return true;
    }
}