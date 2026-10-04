<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\AlbumRepository;
use App\Repositories\Contracts\PlaylistRepository;
use App\Services\Subsonic\SubsonicClient;
use App\Support\Duration;
use Illuminate\Console\Command;
use Throwable;

class DiagnoseNavidrome extends Command
{
    /**
     * @var string
     */
    protected $signature = 'navidrome:diagnose';

    /**
     * @var string
     */
    protected $description = 'Verifica la connessione a Navidrome e riassume la libreria';

    public function handle(
        SubsonicClient $client,
        AlbumRepository $albums,
        PlaylistRepository $playlists,
    ): int {
        $this->line('Host:    <info>'.config('navidrome.host').'</info>');
        $this->line('Utente:  <info>'.(config('navidrome.username') ?: 'non configurato').'</info>');
        $this->line('Bitrate: <info>'.config('navidrome.psp_bitrate').' kbps</info>');
        $this->newLine();

        try {
            $this->line('Server:  <info>'.$client->serverVersion().'</info>');
        } catch (Throwable $exception) {
            $this->error('Server non raggiungibile: '.$exception->getMessage());

            return self::FAILURE;
        }

        try {
            $albumList = $albums->all();
            $playlistList = $playlists->all();
        } catch (Throwable $exception) {
            $this->error('Libreria non leggibile: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Risorsa', 'Conteggio'],
            [
                ['Album', $albumList->count()],
                ['Playlist', $playlistList->count()],
            ],
        );

        $this->line('Primi album:');

        foreach ($albumList->take(5) as $album) {
            $this->line(sprintf(
                '  %-45s %3d brani  %s',
                $album->label(),
                $album->songCount,
                Duration::human($album->duration),
            ));
        }

        return self::SUCCESS;
    }
}
