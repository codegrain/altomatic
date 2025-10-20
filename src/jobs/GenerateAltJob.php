<?php
namespace altomatic\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Asset;
use altomatic\Altomatic;
use altomatic\traits\LoggingTrait;

class GenerateAltJob extends BaseJob
{
    use LoggingTrait;
    
    /** @var int[] */
    public array $assetIds = [];

    public function execute($queue): void
    {
        $total = count($this->assetIds);
        foreach ($this->assetIds as $i => $id) {
            $this->setProgress($queue, ($i + 1) / max(1, $total));
            /** @var ?Asset $asset */
            $asset = Craft::$app->getElements()->getElementById($id, Asset::class);
            if (!$asset || $asset->kind !== Asset::KIND_IMAGE) {
                continue;
            }
            try {
                Altomatic::$plugin->altomaticService->generateForAsset($asset);
            } catch (\Throwable $e) {
                $this->logError("Altomatic job error for asset {$id}: " . $e->getMessage());
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return $this->description ?: 'Altomatic: Generate ALT';
    }
}