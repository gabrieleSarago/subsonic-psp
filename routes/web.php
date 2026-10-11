<?php

use App\Http\Controllers\AlbumFeedController;
use App\Http\Controllers\AudioStreamController;
use App\Http\Controllers\CoverController;
use App\Http\Controllers\FeedIndexController;
use App\Http\Controllers\PlaylistFeedController;
use App\Http\Controllers\RadioFeedController;
use App\Http\Controllers\RadioIconController;
use App\Http\Controllers\RadioStreamController;
use App\Http\Middleware\ForcePspFeedHeaders;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Feed index
|--------------------------------------------------------------------------
|
| The single page the PSP browser opens. It lists every album and playlist
| found on the server, so it never needs to be regenerated.
|
*/

Route::get('/', FeedIndexController::class)->name('index');

/*
|--------------------------------------------------------------------------
| Feeds
|--------------------------------------------------------------------------
|
| One feed per album and one per playlist. A feed longer than what the device
| reads is served as a sequence of parts.
|
| The first part has a short URL and the following ones carry an explicit part
| number. The two shapes are declared as two routes on purpose: an optional
| segment after a literal one does not match, so a single route with
| "/part/{part?}" would answer 404 for the plain URL.
|
*/

Route::prefix('feeds')
    ->middleware(ForcePspFeedHeaders::class)
    ->group(function (): void {
        Route::get('albums/{album}', [AlbumFeedController::class, 'show'])->name('feeds.albums.show');
        Route::get('albums/{album}/part/{part}', [AlbumFeedController::class, 'show'])->name('feeds.albums.part');

        Route::get('playlists/{playlist}', [PlaylistFeedController::class, 'show'])->name('feeds.playlists.show');
        Route::get('playlists/{playlist}/part/{part}', [PlaylistFeedController::class, 'show'])->name('feeds.playlists.part');

        Route::get('radios', [RadioFeedController::class, 'show'])->name('feeds.radios.show');
        Route::get('radios/part/{part}', [RadioFeedController::class, 'show'])->name('feeds.radios.part');
    });

/*
|--------------------------------------------------------------------------
| Audio and artwork
|--------------------------------------------------------------------------
|
| The enclosures of the feeds point here. Serving audio through the application
| is what allows a track to be chosen per song, hidden credentials, and a size
| the device can trust.
|
*/

Route::get('audio/{song}', [AudioStreamController::class, 'show'])->name('audio.show');
Route::get('covers/{cover}', [CoverController::class, 'show'])->name('covers.show');
// The feed item thumbnail must end in an image extension: the PSP picks the
// codec of a <media:thumbnail> from the URL, and an extensionless one is
// ignored, however valid the JPEG behind it is.
Route::get('covers/{cover}/thumb.jpg', [CoverController::class, 'thumb'])->name('covers.thumb');
Route::get('covers/{cover}/{size}', [CoverController::class, 'show'])->where('size', '[0-9]+')->name('covers.show.sized');

/*
|--------------------------------------------------------------------------
| Radio
|--------------------------------------------------------------------------
|
| A station is streamed to the device as MP3 by the same enclosure mechanism
| as a track, and its favicon is proxied like a cover, because the device
| refuses the certificates most remote sites hand out.
|
*/

Route::get('radios/{station}/stream', [RadioStreamController::class, 'show'])->name('radios.stream.show');
// Also ends in .jpg for the same reason as the cover thumbnail: the PSP reads
// the item image only from a URL that names an image file.
Route::get('radios/{station}/icon.jpg', [RadioIconController::class, 'show'])->name('radios.icon.show');
