<?php

use Tests\Support\Radios;

/**
 * A station list of the given length, named and numbered in order.
 *
 * @return array<int, array<string, string>>
 */
function manyStations(int $count): array
{
    return collect(range(1, $count))
        ->map(fn (int $number): array => [
            'id' => 's'.$number,
            'name' => 'Stazione '.$number,
            'streamUrl' => 'http://stream.test/'.$number,
        ])
        ->all();
}

it('serves the stations as a feed with a live enclosure each', function (): void {
    Radios::file([
        ['id' => 'rmc', 'name' => 'RMC Radio Monte Carlo', 'streamUrl' => 'http://stream.test/rmc', 'tagline' => 'Musica e notizie'],
        ['id' => 'kiss', 'name' => 'Kiss Kiss', 'streamUrl' => 'http://stream.test/kiss'],
    ]);

    $response = $this->get('/feeds/radios');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    $items = feedItems($response);

    expect($items)->toHaveCount(2)
        ->and((string) feedChannel($response)->title)->toBe('Radio')
        ->and((string) feedChannel($response)->description)->toBe('2 stazioni');

    $enclosures = feedEnclosures($response);

    expect((string) $items[0]->title)->toBe('RMC Radio Monte Carlo')
        ->and((string) $items[0]->description)->toBe('Musica e notizie')
        ->and((string) $items[0]->guid)->toBe('radio:rmc')
        ->and($enclosures[0]['url'])->toBe(route('radios.stream.show', ['station' => 'rmc']))
        // A live stream has no size, so no length is promised: the attribute is
        // left out rather than being set to zero.
        ->and($enclosures[0]['length'])->toBe('')
        ->and($enclosures[0]['type'])->toBe('audio/mpeg')
        ->and((string) $items[1]->description)->toBe('');
});

it('names a station without a name after its host', function (): void {
    Radios::file([['streamUrl' => 'http://icecast.test/RMC.mp3']]);

    expect((string) feedItems($this->get('/feeds/radios'))[0]->title)->toBe('icecast.test');
});

it('splits a long list into parts of one hundred stations', function (): void {
    Radios::file(manyStations(250));

    expect(feedItems($this->get('/feeds/radios')))->toHaveCount(100)
        ->and((string) feedChannel($this->get('/feeds/radios'))->title)->toBe('Radio (parte 1 di 3)')
        ->and(feedItems($this->get('/feeds/radios/part/2')))->toHaveCount(100)
        ->and(feedItems($this->get('/feeds/radios/part/3')))->toHaveCount(50);
});

it('describes the range each part covers', function (): void {
    Radios::file(manyStations(250));

    expect((string) feedChannel($this->get('/feeds/radios'))->description)->toBe('stazioni 1-100 di 250')
        ->and((string) feedChannel($this->get('/feeds/radios/part/2'))->description)->toBe('stazioni 101-200 di 250')
        ->and((string) feedChannel($this->get('/feeds/radios/part/3'))->description)->toBe('stazioni 201-250 di 250');
});

it('answers 404 for a part beyond the last one', function (): void {
    Radios::file(manyStations(250));

    $this->get('/feeds/radios/part/4')->assertNotFound();
});

it('answers 404 for a part of a list that is not split', function (): void {
    Radios::file([['id' => 'a', 'name' => 'Alfa', 'streamUrl' => 'http://a.test/1']]);

    $this->get('/feeds/radios/part/2')->assertNotFound();
});

it('rejects a part number that is not a number', function (): void {
    Radios::file([['id' => 'a', 'name' => 'Alfa', 'streamUrl' => 'http://a.test/1']]);

    $this->get('/feeds/radios/part/second')->assertNotFound();
});

it('serves an empty feed when there are no stations', function (): void {
    Radios::file([]);

    $response = $this->get('/feeds/radios');

    $response->assertOk();

    expect(feedItems($response))->toBeEmpty()
        ->and((string) feedChannel($response)->description)->toBe('Nessuna stazione disponibile');
});
