# PSP RSS

A small Laravel application that turns a [Navidrome](https://www.navidrome.org/)
music library and a hand-curated list of radio stations into RSS feeds a
**PlayStation Portable** can actually read and play.

The PSP's built-in RSS reader is from 2009: it only decodes MP3, stops reading a
feed after roughly a hundred items, refuses most TLS certificates issued since,
and silently ignores a feed item image unless the markup is exactly right. This
application speaks the one dialect that device understands, so the albums,
playlists and radio stations of a modern music server become a browsable list on
the console.

## Features

- **Browsable index page** — a single plain-HTML page lists the radio section,
  every album and every playlist, each with a link to its feed. Nothing is
  hardcoded: the lists are read from Navidrome on every request, so a playlist
  created on the server shows up without a restart.
- **One feed per album and per playlist** — generated on demand from the music
  server, with the track title, artist, album, a size hint and the artwork.
- **Feeds split into parts** — a collection longer than the page size (100 items
  by default) is served as `part 1`, `part 2`, … because the device stops reading
  after about a hundred entries.
- **Radio** — a curated station list is exposed as its own feed, and each station
  is transcoded to MP3 in real time with `ffmpeg` when played.
- **Audio proxying** — tracks are streamed through the application, which lets
  the app choose the per-song audio URL, hide the server credentials, and tell
  the device a size it can trust. MP3 files are passed through untouched; anything
  else is transcoded on the fly by Navidrome.
- **Artwork and favicons proxied** — covers and station icons are fetched by the
  application and re-encoded into the one format the device draws next to a feed
  item: a **JPEG no larger than 160×120**, served from a URL that ends in `.jpg`.
- **Downloads and range support** — the device can seek within a track and play a
  live radio stream, because the responses carry honest `Content-Length`,
  `Accept-Ranges` and `Content-Range` headers.

## How it is put together

```
app/
├── Http/
│   ├── Controllers/      index page, feeds, audio, covers, radio stream and icon
│   ├── Middleware/       pins generated URLs to APP_URL, fixes the feed headers
│   └── Requests/         one request object per route, resolving its route model
├── Repositories/
│   ├── Contracts/        the interfaces the controllers depend on
│   ├── Subsonic/         albums, playlists, songs, covers from Navidrome
│   └── Radio/            the station list from JSON, favicons from the web
├── Services/
│   ├── Feeds/            maps a song or station to a feed item
│   ├── Radio/            the list store, the stream probe, the ffmpeg transcode
│   ├── PspFeedService    builds album and playlist feeds, one part at a time
│   ├── AudioStreamService streams a track from the music server
│   └── Subsonic/         a thin, authenticated client for the Subsonic API
└── Support/              URL builder, thumbnail re-encoder, date and duration helpers
```

The controllers depend only on the repository **contracts**, never on the
Subsonic or JSON implementations, so the data source can be swapped without
touching them. The bindings live in `app/Providers/AppServiceProvider.php`.

## Requirements

- PHP **8.5** with `gd`, `intl`, `opcache`, `pcntl` and `zip`
- `ffmpeg` on the `PATH` (radio playback; not needed if you only use the music
  server)
- A reachable **Navidrome** (or any Subsonic-compatible) server
- No database and no Node build step: the page is plain HTML with an inline
  stylesheet and the feeds are XML

Docker bundles all of this already, so the recommended way to run it is with
`docker compose`.

## Quick start with Docker

```sh
cp .env.docker.example .env
# edit .env: set PSP_HOSTNAME and the SUBSONIC_* credentials
docker compose up -d --build
```

The container expects a Traefik instance on an external Docker network named
`proxy`. The application is not published directly: Traefik fronts it over plain
HTTP at `PSP_HOSTNAME`, which is also the address written into the feeds.

The port the server listens on is taken from `APP_PORT` on both sides, so the
published port and the listening port cannot drift apart. On first boot the
entrypoint generates the `.env`, the application key and the caches, then starts
the server.

> **HTTPS is deliberately not offered.** The PSP has a 2009 TLS stack and refuses
> most certificates issued since, which produces an error that reads like a
> broken feed. Run this on a home network over plain HTTP.

### Running without Traefik

For a quick test on the local machine, `docker compose` can be reduced to a
single published port:

```sh
docker run --rm -p 9000:9000 \
  -e APP_URL=http://localhost:9000 \
  -e APP_PORT=9000 \
  -e SUBSONIC_HOST=http://navidrome.home.lan \
  -e SUBSONIC_USERNAME=... -e SUBSONIC_PASSWORD=... \
  psp-rss:latest
```

## Local development

```sh
composer setup          # install dependencies, create .env, generate a key
composer dev            # serve on http://0.0.0.0:8000
```

Fill in `SUBSONIC_HOST`, `SUBSONIC_USERNAME` and `SUBSONIC_PASSWORD` in `.env`.
The application stores nothing of its own, so the file cache, file sessions and
the `sync` queue are enough and no database is needed to boot.

## Configuration

The settings that matter for the device. Everything has a sane default; only the
music-server credentials and `APP_URL` usually need changing.

| Variable | Default | Description |
| --- | --- | --- |
| `APP_URL` | `http://localhost:9000` | The address the PSP reaches this application at. It is embedded in the feeds as-is and forced on every request, so the `Host` header has no effect on the generated links. |
| `APP_PORT` | `9000` | The port the server listens on inside the container. |
| `SUBSONIC_HOST` | `http://navidrome.home.lan` | Base URL of the music server, **without** the `/rest` suffix. |
| `SUBSONIC_USERNAME` / `SUBSONIC_PASSWORD` | — | Credentials of the Subsonic API. Never sent in the clear: each request carries a token salted with a fresh random value. |
| `SUBSONIC_PSP_BITRATE` | `128` | Bitrate used to transcode non-MP3 tracks to MP3 on the fly. |
| `PSP_FEED_PAGE_SIZE` | `100` | Maximum items in a single feed; longer collections are split into parts. |
| `RADIOS_FILE` | `storage/app/radios/stations.json` | The hand-edited station list. |
| `RADIOS_FFMPEG_BIN` | `ffmpeg` | The binary used for the realtime radio transcode. |
| `RADIOS_PSP_BITRATE` | `128` | Bitrate every station is transcoded to. |
| `RADIOS_PROBE_TIMEOUT` | `8` | Seconds a probe may wait for a stream. |
| `RADIOS_FIRST_CHUNK_TIMEOUT` | `15` | Seconds the first transcoded bytes may take before a station is reported as unavailable. |
| `RADIOS_STREAM_MAX_BYTES` | `57600000` | The most audio one playback may carry (one hour at 128 kbps), so the live stream looks like a file of a known size. |

Timeouts and cache lifetimes are also configurable; see `config/navidrome.php`
and `config/radios.php` for the full annotated list.

## Artisan commands

| Command | What it does |
| --- | --- |
| `php artisan navidrome:diagnose` | Checks the connection to the music server and summarises the library. |
| `php artisan navidrome:sync` | Drops and reloads the cached album and playlist lists, so a new playlist is on the index page immediately. |
| `php artisan radios:probe` | Probes every station in the list, discovers its name, tagline, favicon and resolved stream URL from the stream itself, and writes them back to the file. Pass `--refresh` to rewrite fields that are already filled in. |

`navidrome:sync` is also scheduled every five minutes (`routes/console.php`) to
keep the album and playlist lists warm. This is an optimisation, not a
requirement: the index page reads the library on every request and the cache
expires on its own, so the schedule only saves the first visitor of each period
from waiting for the server. It needs a running scheduler (`php artisan
schedule:work`) to fire.

## The radio station list

`storage/app/radios/stations.json` is a hand-edited array of stations. Only
`id` and `streamUrl` really need to be typed in; the command above fills in the
rest:

```json
[
  {
    "id": "rmc-radio-monte-carlo",
    "name": "RMC Radio Monte Carlo",
    "tagline": "Various",
    "streamUrl": "http://icecast.unitedradio.it/RMC.mp3",
    "resolvedUrl": "http://icecast.unitedradio.it/RMC.mp3",
    "favicon": "https://example.org/logo.png"
  }
]
```

In Docker the file is a bind mount, so it can be edited on the host and is picked
up within the cache lifetime without a restart.

## Routes

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/` | `index` | The page the PSP browser opens. |
| GET | `/feeds/albums/{album}` | `feeds.albums.show` | First part of an album feed. |
| GET | `/feeds/albums/{album}/part/{part}` | `feeds.albums.part` | A later part of an album feed. |
| GET | `/feeds/playlists/{playlist}` | `feeds.playlists.show` | First part of a playlist feed. |
| GET | `/feeds/playlists/{playlist}/part/{part}` | `feeds.playlists.part` | A later part of a playlist feed. |
| GET | `/feeds/radios` | `feeds.radios.show` | The radio feed. |
| GET | `/feeds/radios/part/{part}` | `feeds.radios.part` | A later part of the radio feed. |
| GET | `/audio/{song}` | `audio.show` | Streams a track (the feed enclosure). |
| GET | `/covers/{cover}` | `covers.show` | A cover at its default size. |
| GET | `/covers/{cover}/{size}` | `covers.show.sized` | A cover at a requested size. |
| GET | `/covers/{cover}/thumb.jpg` | `covers.thumb` | A 160×120 JPEG thumbnail for a feed item. |
| GET | `/radios/{station}/stream` | `radios.stream.show` | The live MP3 transcode of a station. |
| GET | `/radios/{station}/icon.jpg` | `radios.icon.show` | A 160×120 JPEG thumbnail of a station icon. |
| GET | `/up` | — | Health check used by the image. |

## Notes on the device

The interesting engineering in this project comes from working around what the
PSP will and will not do.

- **Only MP3 plays.** MP3 sources are streamed untouched (`format=raw`, full
  range support, no CPU cost); everything else is transcoded to MP3 by Navidrome
  at the configured bitrate.
- **Feeds are capped at about a hundred items.** Longer albums, playlists and
  station lists are served as a sequence of parts, and a part that does not exist
  answers `404`.
- **A feed item image is drawn only from a JPEG** no larger than 160×120, linked
  from a URL that **ends in an image extension** — a valid JPEG behind an
  extensionless URL is ignored. The `…/thumb.jpg` and `…/icon.jpg` endpoints
  exist for exactly this reason.
- **A bad image answer means no image.** When the artwork is missing or cannot be
  decoded, those endpoints answer `404` rather than a blank placeholder, and the
  device falls back to its own default item icon. The `404` is not cached, so
  artwork that appears later is picked up.
- **A live stream has no length**, but the device treats whatever an enclosure
  points at as a file: it asks for the size, then reads the tail with a byte
  range. The radio endpoint therefore declares a finite length, answers the tail
  probe from the start of the same audio, and stops after a configurable amount
  so the device always sees a file it can read.
- **Remote TLS is refused.** Covers and favicons are never linked directly: the
  application fetches the bytes and hands out only its own address.
- **The music server is a separate machine.** When it is unreachable, the
  endpoints answer a `503` with a plain-text message instead of an HTML error
  page: a device that is playing has no use for markup, and the audio proxy is
  where an outage shows up first.

## Testing

The suite is written with [Pest](https://pestphp.com).

```sh
composer test                 # php artisan config:clear && php artisan test
php artisan test --compact    # the same, terser output
vendor/bin/pest tests/Feature/AlbumFeedTest.php
```

`tests/Support` contains fakes for the Subsonic API, the stream probe and the
`ffmpeg` process, so the suite runs without a music server or the binary.

## License

Released under the [MIT license](https://opensource.org/licenses/MIT).
