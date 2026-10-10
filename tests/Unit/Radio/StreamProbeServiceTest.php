<?php

use App\Services\Radio\StreamProbeService;
use Tests\Support\FakeStreamProbeFetcher;

function probeService(FakeStreamProbeFetcher $fetcher): StreamProbeService
{
    return new StreamProbeService($fetcher, maxFollows: 3, probeBytes: 16384);
}

it('reads the icy headers of a direct stream', function (): void {
    $fetcher = (new FakeStreamProbeFetcher)
        ->answerStream('http://stream.test/live', body: "\xff\xfb\x90\x00", icy: [
            'name' => 'Radio Prova',
            'description' => 'Musica tutto il giorno',
            'genre' => 'Pop',
            'url' => 'http://radio.test',
        ])
        ->answerText('http://radio.test', '<html><head><link rel="icon" href="/icon.png"></head></html>');

    $probe = probeService($fetcher)->probe('http://stream.test/live');

    expect($probe)->not->toBeNull()
        ->and($probe->resolvedUrl)->toBe('http://stream.test/live')
        ->and($probe->name)->toBe('Radio Prova')
        ->and($probe->tagline)->toBe('Musica tutto il giorno')
        ->and($probe->favicon)->toBe('http://radio.test/icon.png');
});

it('follows a pls playlist to the stream it points at', function (): void {
    $fetcher = (new FakeStreamProbeFetcher)
        ->answerStream('http://stream.test/;listen.pls', body: "[playlist]\nFile1=http://stream.test/live.mp3\nTitle1=Prova", contentType: 'audio/x-scpls')
        ->answerStream('http://stream.test/live.mp3', body: "\xff\xfb", icy: ['name' => 'Radio Prova']);

    $probe = probeService($fetcher)->probe('http://stream.test/;listen.pls');

    expect($probe->resolvedUrl)->toBe('http://stream.test/live.mp3')
        ->and($probe->name)->toBe('Radio Prova')
        ->and($fetcher->streamRequests)->toBe([
            'http://stream.test/;listen.pls',
            'http://stream.test/live.mp3',
        ]);
});

it('resolves a relative entry of an m3u against its own url', function (): void {
    $fetcher = (new FakeStreamProbeFetcher)
        ->answerStream('http://stream.test/dir/list.m3u', body: "#EXTM3U\n#EXTINF:-1,Radio\n/live.mp3\n", contentType: 'audio/x-mpegurl')
        ->answerStream('http://stream.test/live.mp3', body: "\xff\xfb");

    $probe = probeService($fetcher)->probe('http://stream.test/dir/list.m3u');

    expect($probe->resolvedUrl)->toBe('http://stream.test/live.mp3');
});

it('keeps an hls playlist as the resolved url', function (): void {
    $fetcher = (new FakeStreamProbeFetcher)->answerStream(
        'http://stream.test/hls.m3u8',
        body: "#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=128000\nvariant.m3u8\n",
        contentType: 'application/vnd.apple.mpegurl',
    );

    $probe = probeService($fetcher)->probe('http://stream.test/hls.m3u8');

    expect($probe->resolvedUrl)->toBe('http://stream.test/hls.m3u8')
        ->and($fetcher->streamRequests)->toHaveCount(1);
});

it('does not follow a playlist that points back at itself', function (): void {
    $fetcher = (new FakeStreamProbeFetcher)
        ->answerStream('http://stream.test/a.pls', body: "[playlist]\nFile1=http://stream.test/b.pls", contentType: 'audio/x-scpls')
        ->answerStream('http://stream.test/b.pls', body: "[playlist]\nFile1=http://stream.test/a.pls", contentType: 'audio/x-scpls');

    $probe = probeService($fetcher)->probe('http://stream.test/a.pls');

    expect($probe->resolvedUrl)->toBe('http://stream.test/b.pls')
        ->and($fetcher->streamRequests)->toHaveCount(2);
});

it('reports nothing when the stream cannot be reached', function (): void {
    $probe = probeService(new FakeStreamProbeFetcher)->probe('http://dead.test/live');

    expect($probe)->toBeNull();
});

it('refuses a description that is only the mount name', function (): void {
    $fetcher = (new FakeStreamProbeFetcher)->answerStream('http://stream.test/live', body: "\xff\xfb", icy: [
        'name' => 'FM_RMC_mp3_segrate',
        'description' => 'FM_RMC_mp3_segrate',
        'genre' => 'Various',
    ]);

    $probe = probeService($fetcher)->probe('http://stream.test/live', 'RMC');

    expect($probe->tagline)->toBe('Various');
});

it('falls back to the conventional favicon of the homepage', function (): void {
    $fetcher = (new FakeStreamProbeFetcher)
        ->answerStream('http://stream.test/live', body: "\xff\xfb", icy: ['url' => 'http://radio.test'])
        ->answerText('http://radio.test', '<html><head></head></html>');

    $probe = probeService($fetcher)->probe('http://stream.test/live');

    expect($probe->favicon)->toBe('http://radio.test/favicon.ico');
});

it('takes the favicon and the tagline from the directory as a last resort', function (): void {
    $fetcher = (new FakeStreamProbeFetcher)
        ->answerStream('http://stream.test/hls.m3u8', body: "#EXTM3U\n#EXT-X-TARGETDURATION:10\n", contentType: 'application/vnd.apple.mpegurl')
        ->answerText(
            'https://de1.api.radio-browser.info/json/stations/search',
            json_encode([['favicon' => 'https://cdn.test/logo.png', 'tags' => 'pop, hits']]),
        );

    $probe = probeService($fetcher)->probe('http://stream.test/hls.m3u8', 'Radio Deejay');

    expect($probe->favicon)->toBe('https://cdn.test/logo.png')
        ->and($probe->tagline)->toBe('pop, hits');
});
