<?php

namespace App\Repositories\Contracts;

use App\Data\Radio\FaviconImage;

/**
 * Read access to station favicons.
 */
interface FaviconRepository
{
    /**
     * A favicon image proxied from the remote site, or null when the station
     * has none or it cannot be fetched.
     *
     * Favicons are small, so the bytes are buffered and cached instead of
     * being streamed.
     */
    public function find(string $stationId): ?FaviconImage;
}
