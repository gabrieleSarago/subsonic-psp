<?php

use App\Data\Subsonic\Album;
use App\Data\Subsonic\Playlist;
use App\Data\Subsonic\Song;
use Tests\Support\SubsonicApi;

it('recognises an MP3 source as playable as it is', function (): void {
    expect(Song::fromArray(SubsonicApi::song(['suffix' => 'mp3']))->isMp3())->toBeTrue()
        ->and(Song::fromArray(SubsonicApi::song(['suffix' => 'MP3']))->isMp3())->toBeTrue()
        ->and(Song::fromArray(SubsonicApi::song(['suffix' => 'flac']))->isMp3())->toBeFalse()
        ->and(Song::fromArray(SubsonicApi::song(['suffix' => 'm4a']))->isMp3())->toBeFalse();
});

it('keeps the content fingerprint stable while the file does not change', function (): void {
    $song = Song::fromArray(SubsonicApi::song());

    expect($song->contentFingerprint())->toBe(
        Song::fromArray(SubsonicApi::song())->contentFingerprint(),
    );
});

it('changes the content fingerprint when the file is replaced', function (): void {
    $original = Song::fromArray(SubsonicApi::song(['id' => 'sg-1', 'size' => 1000]));

    expect(Song::fromArray(SubsonicApi::song(['id' => 'sg-1', 'size' => 2000]))->contentFingerprint())
        ->not->toBe($original->contentFingerprint())
        ->and(Song::fromArray(SubsonicApi::song(['id' => 'sg-2', 'size' => 1000]))->contentFingerprint())
        ->not->toBe($original->contentFingerprint())
        ->and(Song::fromArray(SubsonicApi::song(['id' => 'sg-1', 'suffix' => 'flac', 'size' => 1000]))->contentFingerprint())
        ->not->toBe($original->contentFingerprint());
});

it('falls back to usable values when the server omits the optional fields', function (): void {
    $song = Song::fromArray(['id' => 'sg-9']);

    expect($song->title)->toBe('Senza titolo')
        ->and($song->artist)->toBe('Artista sconosciuto')
        ->and($song->albumId)->toBeNull()
        ->and($song->coverArt)->toBeNull()
        ->and($song->duration)->toBe(0);
});

it('orders album tracks by disc, then by track number', function (): void {
    $album = Album::fromArray(SubsonicApi::album([
        SubsonicApi::song(['id' => 'c', 'discNumber' => 1, 'track' => 9]),
        SubsonicApi::song(['id' => 'a', 'discNumber' => 2, 'track' => 1]),
        SubsonicApi::song(['id' => 'b', 'discNumber' => 1, 'track' => 2]),
    ]));

    expect($album->songs->pluck('id')->all())->toBe(['b', 'c', 'a'])
        ->and($album->songCount)->toBe(3);
});

it('labels an album with its artist and a playlist with its name alone', function (): void {
    expect(Album::fromArray(SubsonicApi::album())->label())->toBe('Artista di prova - Album di prova')
        ->and(Album::fromArray(SubsonicApi::album([], ['artist' => '']))->label())->toBe('Album di prova')
        ->and(Playlist::fromArray(SubsonicApi::playlist())->label())->toBe('Playlist di prova');
});
