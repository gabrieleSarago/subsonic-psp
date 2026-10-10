<?php

namespace App\Providers;

use App\Repositories\Contracts\AlbumRepository;
use App\Repositories\Contracts\CoverRepository;
use App\Repositories\Contracts\FaviconRepository;
use App\Repositories\Contracts\PlaylistRepository;
use App\Repositories\Contracts\RadioStationRepository;
use App\Repositories\Contracts\SongRepository;
use App\Repositories\Radio\JsonRadioStationRepository;
use App\Repositories\Radio\RemoteFaviconRepository;
use App\Repositories\Subsonic\SubsonicAlbumRepository;
use App\Repositories\Subsonic\SubsonicCoverRepository;
use App\Repositories\Subsonic\SubsonicPlaylistRepository;
use App\Repositories\Subsonic\SubsonicSongRepository;
use App\Services\Feeds\RadioFeedItemFactory;
use App\Services\Feeds\SongFeedItemFactory;
use App\Services\PspFeedService;
use App\Services\Radio\Contracts\StreamProbeFetcher;
use App\Services\Radio\FfmpegStreamProcessRunner;
use App\Services\Radio\GuzzleStreamProbeFetcher;
use App\Services\Radio\RadioFeedService;
use App\Services\Radio\RadioListStore;
use App\Services\Radio\RadioStreamService;
use App\Services\Radio\StreamProbeService;
use App\Services\Radio\StreamProcessRunner;
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
        $this->app->bind(RadioStationRepository::class, JsonRadioStationRepository::class);
        $this->app->bind(FaviconRepository::class, RemoteFaviconRepository::class);

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

        $this->app->singleton(RadioListStore::class, fn ($app) => new RadioListStore(
            (string) config('radios.file'),
        ));

        $this->app->singleton(StreamProbeFetcher::class, fn ($app) => new GuzzleStreamProbeFetcher(
            (string) config('radios.user_agent'),
            (int) config('radios.probe_timeout'),
            (int) config('radios.probe_connect_timeout'),
        ));

        $this->app->singleton(StreamProbeService::class, fn ($app) => new StreamProbeService(
            $app->make(StreamProbeFetcher::class),
            (int) config('radios.probe_max_follows'),
            (int) config('radios.probe_bytes'),
        ));

        $this->app->singleton(StreamProcessRunner::class, fn ($app) => new FfmpegStreamProcessRunner(
            (string) config('radios.ffmpeg_bin'),
            (int) config('radios.bitrate'),
            (string) config('radios.user_agent'),
            (string) storage_path('logs/radio-ffmpeg.log'),
        ));

        $this->app->singleton(RadioStreamService::class, fn ($app) => new RadioStreamService(
            $app->make(RadioStationRepository::class),
            $app->make(StreamProcessRunner::class),
            (int) config('radios.first_chunk_timeout'),
            (int) config('radios.stream_chunk_size'),
            (int) config('radios.stream_max_bytes'),
        ));

        $this->app->singleton(RadioFeedItemFactory::class, fn ($app) => new RadioFeedItemFactory(
            $app->make(PspUrls::class),
        ));

        $this->app->singleton(RadioFeedService::class, fn ($app) => new RadioFeedService(
            $app->make(RadioStationRepository::class),
            $app->make(RadioFeedItemFactory::class),
            $app->make(PspUrls::class),
            (int) config('radios.feed_page_size'),
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
