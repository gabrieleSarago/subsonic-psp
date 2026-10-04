<?php

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change this using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Feed helpers
|--------------------------------------------------------------------------
|
| A feed is asserted on its parsed shape rather than on its markup, so a change
| in whitespace or in attribute order cannot make a test fail on its own.
|
*/

/**
 * The channel element of a feed response.
 */
function feedChannel(TestResponse $response): SimpleXMLElement
{
    $xml = simplexml_load_string((string) $response->getContent());

    expect($xml)->not->toBeFalse('the feed is not well formed XML: '.$response->getContent());

    return $xml->channel;
}

/**
 * The items of a feed response.
 *
 * @return array<int, SimpleXMLElement>
 */
function feedItems(TestResponse $response): array
{
    $items = [];

    foreach (feedChannel($response)->item as $item) {
        $items[] = $item;
    }

    return $items;
}

/**
 * The enclosure of every item of a feed, as "url length type" triples.
 *
 * @return array<int, array<string, string>>
 */
function feedEnclosures(TestResponse $response): array
{
    return array_map(
        fn (SimpleXMLElement $item): array => [
            'url' => (string) $item->enclosure['url'],
            'length' => (string) $item->enclosure['length'],
            'type' => (string) $item->enclosure['type'],
        ],
        feedItems($response),
    );
}
