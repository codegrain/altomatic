<?php
namespace altomatic\providers;

use craft\elements\Asset;

interface ProviderInterface
{
    /**
     * Return a concise, human-readable ALT text for the given image.
     *
     * @param Asset $asset
     * @param string|null $imageInput URL or local file path
     * @return string|null
     */
    public function generateAlt(Asset $asset, ?string $imageInput): ?string;
}
