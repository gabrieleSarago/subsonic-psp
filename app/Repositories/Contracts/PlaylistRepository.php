<?php

namespace App\Repositories\Contracts;

use App\Data\Subsonic\Playlist;
use Illuminate\Support\Collection;

/**
 * Read access to the playlists of the user.
 */
interface PlaylistRepository
{
    /**
     * Every playlist, ordered by name.
     *
     * @return Collection<int, Playlist>
     */
    public function all(): Collection;

    /**
     * A single playlist with its playable entries, or null when it no longer
     * exists.
     */
    public function find(string $playlistId): ?Playlist;

    /**
     * Drop the cached playlist lists so the next read hits the server.
     */
    public function forget(): void;
}
