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
        $this->requirePermission('altomatic:generate');

        $ids = $query->ids();
        if (!$ids) {
            $this->setMessage('No assets selected.');
            return true;
        }

        Craft::$app->getQueue()->push(new GenerateAltJob([
            'assetIds' => $ids,
            'description' => 'Altomatic: Generate ALT for selection',
        ]));

        $this->setMessage('Queued ALT generation for selected assets.');
        return true;
    }
}
