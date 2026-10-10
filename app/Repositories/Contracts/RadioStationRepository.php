<?php

namespace App\Repositories\Contracts;

use App\Data\Radio\Station;
use Illuminate\Support\Collection;

/**
 * Read access to the curated list of radio stations.
 */
interface RadioStationRepository
{
    /**
     * Every station, in the order they were written in the file.
     *
     * @return Collection<int, Station>
     */
    public function all(): Collection;

    /**
     * A single station, or null when it is not in the list.
     */
    public function find(string $stationId): ?Station;

    /**
     * Drop the cached list so the next read rereads the file.
     *
     * The radios:probe command writes the file, and a cache that still holds
     * the old stations would serve stale URLs and stale metadata.
     */
    public function forget(): void;
}
