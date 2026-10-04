<?php

namespace App\Repositories\Contracts;

use App\Data\Subsonic\CoverImage;

/**
 * Read access to cover artwork.
 */
interface CoverRepository
{
    /**
     * A cover image, or null when the artwork is missing.
     *
     * Covers are small, so the bytes are buffered and cached instead of being
     * streamed.
     */
    public function find(string $coverArtId, int $size): ?CoverImage;
}
