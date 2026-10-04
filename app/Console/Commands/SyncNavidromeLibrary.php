<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\AlbumRepository;
use App\Repositories\Contracts\PlaylistRepository;
use Illuminate\Console\Command;
use Throwable;

class SyncNavidromeLibrary extends Command
{
    /**
     * @var string
     */
    protected $signature = 'navidrome:sync';

    /**
     * @var string
     */
    protected $description = 'Riscalda la cache con gli album e le playlist correnti di Navidrome';

    /**
     * Drop the cached lists and read them again, so that a playlist created on
     * the server is visible on the index page as soon as this command runs.
     *
     * The individual albums and playlists are left alone: they change far less
     * often than the lists do.
     */
    public function handle(AlbumRepository $albums, PlaylistRepository $playlists): int
    {
        $albums->forget();
        $playlists->forget();

        try {
            $albumCount = $albums->all()->count();
            $playlistCount = $playlists->all()->count();
        } catch (Throwable $exception) {
            $this->error('Sincronizzazione fallita: '.$exception->getMessage());

            // Not fatal: whatever is cached stays valid until it expires, and the
            // next run tries again.
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Cache aggiornata: %d album, %d playlist.',
            $albumCount,
            $playlistCount,
        ));

        return self::SUCCESS;
    }
}
