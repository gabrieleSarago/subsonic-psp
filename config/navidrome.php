<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Navidrome / Subsonic connection
    |--------------------------------------------------------------------------
    |
    | Credentials of the Subsonic API exposed by Navidrome. The host is the
    | base URL of the server, without the "/rest" suffix: the client appends
    | it while building endpoint URLs.
    |
    */

    'host' => env('SUBSONIC_HOST', 'http://navidrome.home.lan'),

    'username' => env('SUBSONIC_USERNAME'),

    'password' => env('SUBSONIC_PASSWORD'),

    /*
    | The Subsonic API version advertised by the server. Used as the "v"
    | query parameter on every request.
    */
    'api_version' => env('SUBSONIC_API_VERSION', '1.16.1'),

    /*
    | The "c" query parameter. Some servers keep per-client statistics, so a
    | dedicated name keeps the server-side logs readable.
    */
    'client_name' => env('SUBSONIC_CLIENT_NAME', 'psp-rss'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | Metadata calls are cheap; streaming a whole track is not. The stream
    | timeout is deliberately generous so that a long track is never cut off
    | mid-playback by the proxy.
    |
    */

    'connect_timeout' => (int) env('SUBSONIC_CONNECT_TIMEOUT', 5),

    'timeout' => (int) env('SUBSONIC_TIMEOUT', 30),

    'stream_timeout' => (int) env('SUBSONIC_STREAM_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | PSP tuning
    |--------------------------------------------------------------------------
    |
    | The PSP can only play MP3. Tracks that are already MP3 are streamed in
    | their original form (full Range support, no CPU cost); anything else is
    | transcoded on the fly by Navidrome to MP3 at this bitrate.
    |
    | 128 kbps is a good compromise: a 5 minute track becomes roughly 5 MB.
    |
    */

    'psp_bitrate' => (int) env('SUBSONIC_PSP_BITRATE', 128),

    /*
    | Maximum number of items in a single feed. The PSP stops reading after
    | roughly 100 entries, so longer albums and playlists are split in parts.
    */
    'feed_page_size' => (int) env('PSP_FEED_PAGE_SIZE', 100),

    /*
    | Size of the chunks copied from the upstream server while proxying audio.
    */
    'stream_chunk_size' => (int) env('SUBSONIC_STREAM_CHUNK_SIZE', 65536),

    /*
    | Number of albums requested per getAlbumList2 call. Navidrome caps the
    | "size" parameter at 500.
    */
    'album_page_size' => 500,

    /*
    |--------------------------------------------------------------------------
    | Cache lifetimes, in seconds
    |--------------------------------------------------------------------------
    |
    | Lists are refreshed often so that a playlist created in Navidrome shows
    | up on the index page without restarting anything. Single entities change
    | rarely, so they are cached for longer.
    |
    */

    'cache' => [
        'albums' => (int) env('SUBSONIC_CACHE_ALBUMS', 300),
        'playlists' => (int) env('SUBSONIC_CACHE_PLAYLISTS', 300),
        'album' => (int) env('SUBSONIC_CACHE_ALBUM', 3600),
        'playlist' => (int) env('SUBSONIC_CACHE_PLAYLIST', 3600),
        'song' => (int) env('SUBSONIC_CACHE_SONG', 3600),
        'cover' => (int) env('SUBSONIC_CACHE_COVER', 604800),
    ],

];
