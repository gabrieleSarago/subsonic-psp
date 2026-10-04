<?php

namespace App\Repositories\Contracts;

use App\Data\Subsonic\Album;
use Illuminate\Support\Collection;

/**
 * Read access to the albums of the library.
 */
interface AlbumRepository
{
    /**
     * Every album, ordered alphabetically by name.
     *
     * @return Collection<int, Album>
     */
    public function all(): Collection;

    /**
     * A single album with its tracks, or null when it no longer exists.
     */
    public function find(string $albumId): ?Album;

    /**
     * Drop the cached album lists so the next read hits the server.
     */
    public function forget(): void;
}
