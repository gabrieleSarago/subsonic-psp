<?php

use App\Services\Subsonic\SubsonicClient;
use App\Services\Subsonic\SubsonicException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\SubsonicApi;

it('authenticates with a salted token instead of the password', function (): void {
    Http::fake([
        '*' => Http::response(['subsonic-response' => ['status' => 'ok']]),
    ]);

    app(SubsonicClient::class)->get('ping');

    $query = queryOfRequest(Http::recorded()[0]);

    expect($query)->not->toHaveKey('p')
        ->and($query['t'])->toBe(md5('s3cret'.$query['s']))
        ->and($query['u'])->toBe('tester')
        ->and($query['v'])->toBe(config('navidrome.api_version'))
        ->and($query['f'])->toBe('json');
});

it('never sends the password, not even as a query parameter', function (): void {
    Http::fake([
        '*' => Http::response(['subsonic-response' => ['status' => 'ok']]),
    ]);

    app(SubsonicClient::class)->get('ping');

    expect((string) Http::recorded()[0][0]->url())->not->toContain('s3cret');
});

it('uses a different salt on every request', function (): void {
    Http::fake([
        '*' => Http::response(['subsonic-response' => ['status' => 'ok']]),
    ]);

    $client = app(SubsonicClient::class);
    $client->get('ping');
    $client->get('ping');

    $salts = array_map(
        fn (array $recorded): string => queryOfRequest($recorded)['s'],
        Http::recorded()->all(),
    );

    expect($salts)->toHaveCount(2)->and($salts[0])->not->toBe($salts[1]);
});

it('turns an API level failure into an exception, though the status is 200', function (): void {
    Http::fake([
        '*' => Http::response([
            'subsonic-response' => [
                'status' => 'failed',
                'error' => ['code' => 40, 'message' => 'Wrong username or password'],
            ],
        ]),
    ]);

    expect(fn () => app(SubsonicClient::class)->get('ping'))
        ->toThrow(SubsonicException::class, '[40] Wrong username or password');
});

it('refuses to work without credentials', function (): void {
    config(['navidrome.password' => '']);

    Http::fake([
        '*' => Http::response(['subsonic-response' => ['status' => 'ok']]),
    ]);

    expect(fn () => app(SubsonicClient::class)->get('ping'))
        ->toThrow(SubsonicException::class, 'SUBSONIC_PASSWORD non configurata');
});

it('answers 503 to the device while Navidrome is unreachable', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->get('/feeds/albums/al-1')->assertStatus(503);
});

it('answers 503 for a cover while Navidrome is unreachable', function (): void {
    // Artwork is the one request the device makes without following a link, so
    // it must fail the same way as the rest rather than with a stack trace.
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->get('/covers/al-1/100')->assertStatus(503);
});

it('answers 503 for a track while Navidrome is unreachable', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->get('/audio/sg-1')->assertStatus(503);
});

it('answers 503 for the index page while Navidrome is unreachable', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->get('/')->assertStatus(503);
});

it('keeps the credentials out of the answer to the device', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $content = (string) $this->get('/feeds/albums/al-1')->getContent();

    expect($content)->toContain('Navidrome')->not->toContain('s3cret');
});

it('keeps the signed URL out of the answer to the device', function (): void {
    // A failed connection is reported with the URL that was requested, signed
    // and all, so the user name and the token have to be kept out of the answer.
    // They stay in the log, which is where a diagnosis belongs.
    Http::fake(fn (Request $request) => throw new ConnectionException(
        "cURL error 28: Timeout after 5001 ms for {$request->url()}",
    ));

    $content = (string) $this->get('/feeds/albums/al-1')->getContent();

    expect($content)->toContain('Navidrome')
        ->not->toContain('tester')
        ->not->toContain('t=')
        ->not->toContain('s=');
});

it('keeps the signed URL out of the answer while a track is being opened', function (): void {
    SubsonicApi::fake(albums: [SubsonicApi::album([SubsonicApi::song()])]);
    SubsonicApi::$audioConnectionLost = true;

    $response = $this->get('/audio/sg-1');

    expect((string) $response->getContent())->toContain('Navidrome')
        ->not->toContain('tester')
        ->not->toContain('t=')
        ->not->toContain('s=');

    // The device is playing, not browsing: an HTML page in the body of a track
    // is of no use to it.
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});

/**
 * @param  array{0: Request}  $recorded
 * @return array<string, mixed>
 */
function queryOfRequest(array $recorded): array
{
    parse_str((string) parse_url($recorded[0]->url(), PHP_URL_QUERY), $query);

    return $query;
}
