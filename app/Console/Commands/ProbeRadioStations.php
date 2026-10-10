<?php

namespace App\Console\Commands;

use App\Data\Radio\Station;
use App\Repositories\Contracts\RadioStationRepository;
use App\Services\Radio\RadioListStore;
use App\Services\Radio\StreamProbeService;
use Illuminate\Console\Command;

class ProbeRadioStations extends Command
{
    /**
     * @var string
     */
    protected $signature = 'radios:probe
        {--refresh : riscrive anche i campi gia compilati}';

    /**
     * @var string
     */
    protected $description = 'Sonda ogni stazione e scrive nel file i metadati scoperti dallo stream';

    public function handle(
        RadioListStore $store,
        StreamProbeService $probe,
        RadioStationRepository $stations,
    ): int {
        $rows = $store->read();

        if ($rows === []) {
            $this->warn('Nessuna stazione nel file '.config('radios.file').'.');

            return self::FAILURE;
        }

        $refresh = (bool) $this->option('refresh');
        $touched = false;

        foreach ($rows as $key => $row) {
            $streamUrl = trim((string) ($row['streamUrl'] ?? ''));

            if ($streamUrl === '') {
                $this->warn('Riga senza streamUrl ignorata.');

                continue;
            }

            // The identifier is derived from what was written by hand, before
            // any discovered field is merged in: a station without an id or a
            // name would otherwise be renamed by its own probe, and the URLs
            // the device already holds would stop resolving.
            $row['id'] = Station::fromArray($row)->id;

            $this->line('Sondo '.$streamUrl);
            $result = $probe->probe($streamUrl, (string) ($row['name'] ?? ''));

            if ($result === null) {
                $this->warn('  irraggiungibile: riga lasciata invariata');

                continue;
            }

            $row['resolvedUrl'] = $result->resolvedUrl;

            // The discovered fields only fill what is missing, so a name, a
            // tagline or a favicon written by hand survives a probe. --refresh
            // replaces them with the discovery.
            $row['name'] = $this->merge((string) ($row['name'] ?? ''), $result->name, $refresh);
            $row['tagline'] = $this->merge((string) ($row['tagline'] ?? ''), $result->tagline, $refresh);
            $row['favicon'] = $this->merge((string) ($row['favicon'] ?? ''), $result->favicon, $refresh);

            $rows[$key] = $this->ordered($row);
            $touched = true;

            $this->line('  risolta in '.$result->resolvedUrl);
        }

        if (! $touched) {
            $this->warn('Nessuna stazione aggiornata.');

            return self::SUCCESS;
        }

        $store->write($rows);
        $stations->forget();
        $this->info('File aggiornato: '.config('radios.file'));

        return self::SUCCESS;
    }

    /**
     * A discovered field fills an empty one, and with --refresh replaces it.
     */
    private function merge(string $existing, ?string $discovered, bool $refresh): string
    {
        if ($existing !== '' && ! $refresh) {
            return $existing;
        }

        return $discovered ?? '';
    }

    /**
     * Reorder the row in a predictable shape, dropping empty fields.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function ordered(array $row): array
    {
        $ordered = [];

        foreach (['id', 'name', 'tagline', 'streamUrl', 'resolvedUrl', 'favicon'] as $field) {
            if (isset($row[$field]) && $row[$field] !== null && $row[$field] !== '') {
                $ordered[$field] = (string) $row[$field];
            }
        }

        return $ordered;
    }
}
