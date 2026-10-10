<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Radio stations
    |--------------------------------------------------------------------------
    |
    | The curated list of stations lives in a JSON file edited by hand. The
    | fields that describe a station (name, tagline, favicon, direct stream
    | URL) are discovered by the radios:probe command by interrogating the
    | stream itself, so they are never typed in. The values below only tune
    | the probing and the realtime transcode.
    |
    | The file lives inside the container storage so that a bind mount keeps
    | it editable without a rebuild.
    |
    */

    'file' => env('RADIOS_FILE', storage_path('app/radios/stations.json')),

    /*
    | The MP3 bitrate every station is transcoded to, whatever the codec of
    | the source. The PSP only plays MP3.
    */
    'bitrate' => (int) env('RADIOS_PSP_BITRATE', 128),

    /*
    | The ffmpeg binary used for the realtime transcode.
    */
    'ffmpeg_bin' => env('RADIOS_FFMPEG_BIN', 'ffmpeg'),

    /*
    | The User-Agent sent while probing a stream and handed to ffmpeg. Some
    | CDNs refuse requests that do not look like a browser.
    */
    'user_agent' => env('RADIOS_USER_AGENT', 'PSP-RSS/1.0 (radio scanner)'),

    /*
    | How long the probe may take to reach a stream and read its first bytes,
    | in seconds. Probing runs only when radios:probe is invoked by hand.
    */
    'probe_timeout' => (int) env('RADIOS_PROBE_TIMEOUT', 8),
    'probe_connect_timeout' => (int) env('RADIOS_PROBE_CONNECT_TIMEOUT', 5),

    /*
    | How many playlist hops a probe follows (a .pls that points to another
    | playlist, a playlist that is a redirect target) before giving up.
    */
    'probe_max_follows' => 3,

    /*
    | How many bytes of the stream are read to sniff its kind (playlist, HLS),
    | enough to cover the largest master playlist.
    */
    'probe_bytes' => 16384,

    /*
    | How long the first transcoded chunk may take to appear, in seconds,
    | before the stream is reported as unavailable instead of dead air.
    */
    'first_chunk_timeout' => (int) env('RADIOS_FIRST_CHUNK_TIMEOUT', 15),

    /*
    | Size of the chunks copied from ffmpeg while streaming, in bytes.
    */
    'stream_chunk_size' => (int) env('RADIOS_STREAM_CHUNK_SIZE', 65536),

    /*
    | Maximum number of stations in a single feed. A list longer than this is
    | served as a sequence of parts, like an album or a playlist.
    */
    'feed_page_size' => (int) env('RADIOS_FEED_PAGE_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Cache lifetimes, in seconds
    |--------------------------------------------------------------------------
    |
    | The list is read from disk often enough that a station added by editing
    | the file shows up without a restart. A favicon is a stable resource, so
    | its bytes are kept for a week like the covers.
    |
    */

    'cache' => [
        'stations' => (int) env('RADIOS_CACHE_STATIONS', 300),
        'favicon' => 604800,
    ],

];
