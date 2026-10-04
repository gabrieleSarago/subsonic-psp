<?php

namespace App\Providers;

use App\Repositories\Contracts\AlbumRepository;
use App\Repositories\Contracts\CoverRepository;
use App\Repositories\Contracts\PlaylistRepository;
use App\Repositories\Contracts\SongRepository;
use App\Repositories\Subsonic\SubsonicAlbumRepository;
use App\Repositories\Subsonic\SubsonicCoverRepository;
use App\Repositories\Subsonic\SubsonicPlaylistRepository;
use App\Repositories\Subsonic\SubsonicSongRepository;
use App\Services\Feeds\SongFeedItemFactory;
use App\Services\PspFeedService;
use App\Support\PspUrls;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * The controllers depend on the repository contracts, never on the Subsonic
     * implementations, so the data source can be swapped without touching them.
     */
    public function register(): void
    {
        $this->app->bind(AlbumRepository::class, SubsonicAlbumRepository::class);
        $this->app->bind(PlaylistRepository::class, SubsonicPlaylistRepository::class);
        $this->app->bind(SongRepository::class, SubsonicSongRepository::class);
        $this->app->bind(CoverRepository::class, SubsonicCoverRepository::class);

        // The tuning values live in configuration, so the services taking them
        // are built here rather than through constructor promotion.
        $this->app->singleton(SongFeedItemFactory::class, fn ($app) => new SongFeedItemFactory(
            $app->make(PspUrls::class),
            config('navidrome.psp_bitrate'),
        ));

        $this->app->singleton(PspFeedService::class, fn ($app) => new PspFeedService(
            $app->make(SongFeedItemFactory::class),
            $app->make(PspUrls::class),
            config('navidrome.feed_page_size'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
