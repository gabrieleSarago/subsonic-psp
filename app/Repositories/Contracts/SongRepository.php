<?php

namespace App\Repositories\Contracts;

use App\Data\Subsonic\Song;

/**
 * Read access to a single track.
 */
interface SongRepository
{
    /**
     * A single track, or null when it no longer exists.
     *
     * The audio proxy needs the suffix, the size and the duration to decide
     * between streaming the original file and asking for a live transcode.
     */
    public function find(string $songId): ?Song;
}
