<?php

namespace App\Repositories\Radio;

use App\Data\Radio\Station;
use App\Repositories\Contracts\RadioStationRepository;
use App\Services\Radio\RadioListStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reads the curated station list from its JSON file.
 */
final class JsonRadioStationRepository implements RadioStationRepository
{
    private const string LIST_CACHE_KEY = 'radios:stations:list';

    public function __construct(private readonly RadioListStore $store) {}

    public function all(): Collection
    {
        $stations = Cache::remember(
            self::LIST_CACHE_KEY,
            config('radios.cache.stations'),
            fn (): Collection => $this->read(),
        );

        if (! $stations instanceof Collection) {
            // The cache holds objects, and a copy written before a change to
            // their shape comes back unreadable. It is dropped and read again
            // rather than reported as a failure.
            Cache::forget(self::LIST_CACHE_KEY);

            return $this->all();
        }

        return $stations;
    }

    public function find(string $stationId): ?Station
    {
        if ($stationId === '') {
            return null;
        }

        return $this->all()
            ->first(fn (Station $station): bool => $station->id === $stationId);
    }

    public function forget(): void
    {
        Cache::forget(self::LIST_CACHE_KEY);
    }

    /**
     * @return Collection<int, Station>
     */
    private function read(): Collection
    {
        return collect($this->store->read())
            ->map(fn (array $row): Station => Station::fromArray($row))
            ->filter(fn (Station $station): bool => $station->streamUrl !== '')
            ->values();
    }
}
