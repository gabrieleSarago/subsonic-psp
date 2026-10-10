<?php

namespace App\Services\Radio;

use App\Data\Radio\Station;
use App\Repositories\Contracts\RadioStationRepository;
use App\Services\Feeds\RadioFeedItemFactory;
use App\Support\PspUrls;
use Illuminate\Support\Collection;
use Spatie\Feed\Feed;

/**
 * Builds the RSS feed of the radio section.
 *
 * The PSP stops reading a feed after roughly one hundred items, so a list
 * longer than that is served as a sequence of parts, like an album or a
 * playlist.
 */
final class RadioFeedService
{
    private readonly int $pageSize;

    public function __construct(
        private readonly RadioStationRepository $stations,
        private readonly RadioFeedItemFactory $items,
        private readonly PspUrls $urls,
        int $pageSize,
    ) {
        $this->pageSize = max(1, $pageSize);
    }

    /**
     * Build one part of the radio feed.
     */
    public function feed(int $part = 1): Feed
    {
        $stations = $this->stations->all();
        $totalParts = $this->partCount($stations->count());
        [$page, $part] = $this->resolvePage($stations, $part, $totalParts);

        return new Feed(
            title: 'Radio'.($totalParts > 1 ? " (parte {$part} di {$totalParts})" : ''),
            items: $page->map(fn (Station $station) => $this->items->make($station)),
            url: request()->url(),
            view: 'feeds.rss',
            description: $this->describe($stations->count(), $part),
            language: 'it',
            image: '',
            format: 'rss',
        );
    }

    /**
     * How many parts a feed of the given length is split into.
     */
    public function partCount(int $stationCount): int
    {
        return (int) max(1, ceil($stationCount / $this->pageSize));
    }

    /**
     * Pick the requested slice, rejecting parts that do not exist.
     *
     * @param  Collection<int, Station>  $stations
     * @return array{0: Collection<int, Station>, 1: int}
     */
    private function resolvePage(Collection $stations, int $part, int $totalParts): array
    {
        $part = max(1, $part);

        if ($part > $totalParts) {
            abort(404, 'Questa parte del feed non esiste.');
        }

        return [$stations->forPage($part, $this->pageSize)->values(), $part];
    }

    private function describe(int $totalStations, int $part): string
    {
        if ($totalStations === 0) {
            return 'Nessuna stazione disponibile';
        }

        if ($totalStations <= $this->pageSize) {
            return $totalStations === 1 ? '1 stazione' : sprintf('%d stazioni', $totalStations);
        }

        $from = (($part - 1) * $this->pageSize) + 1;
        $to = min($totalStations, $from + $this->pageSize - 1);

        return sprintf('stazioni %d-%d di %d', $from, $to, $totalStations);
    }
}
