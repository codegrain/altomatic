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
        return Craft::t('app', 'Generate Alt');
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
            'description' => 'Altomatic: Generate Alt for selection',
        ]));

        Altomatic::$plugin->altomaticService->logAction('queue-selected', $ids[0], count($ids));
        $this->setMessage('Queued Alt generation for selected assets.');
        return true;
    }
}