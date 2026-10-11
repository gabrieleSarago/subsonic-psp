{{--
    Discriminating probe feed for the PSP item icons.

    Every item emits one variant of the ways an image can be attached to an
    item, and the channel links each one to a solid colour JPEG served by
    /psp-test/img. Comparing those colours with the icons on the device tells
    which of the variants, if any, the PSP honours. Debug scaffolding: delete
    together with the routes once the item icon question is settled.
--}}
@php
    $channelUpdated = collect($items)
        ->pluck('updated')
        ->filter(fn ($date) => $date instanceof \Carbon\CarbonInterface)
        ->sortDesc()
        ->first() ?? now();
@endphp
<?= '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
    <channel>
        <atom:link href="{{ url($meta['link']) }}" rel="self" type="application/rss+xml" />
        <title>{{ $meta['title'] }}</title>
        <link>{{ url($meta['link']) }}</link>
        <description>{{ $meta['description'] }}</description>
        <language>it</language>
        <generator>Laravel</generator>
        <lastBuildDate>{{ \App\Support\RssDate::rfc822($channelUpdated) }}</lastBuildDate>
@foreach ($items as $item)
        <item>
            <title>{{ $item['title'] }}</title>
            <link>{{ $item['link'] ?? $item['enclosure'] }}</link>
            <guid isPermaLink="false">{{ $item['id'] }}</guid>
            <description>{{ $item['summary'] ?? '' }}</description>
            <pubDate>{{ \App\Support\RssDate::rfc822($item['updated']) }}</pubDate>
            <enclosure url="{{ $item['enclosure'] }}"@if (($item['enclosureLength'] ?? 0) > 0) length="{{ $item['enclosureLength'] }}"@endif type="{{ $item['enclosureType'] ?? 'audio/mpeg' }}" />
@if (isset($item['thumb']))
            <media:thumbnail url="{{ $item['thumb']['url'] }}" width="{{ $item['thumb']['width'] }}" height="{{ $item['thumb']['height'] }}" />
@endif
@if (isset($item['itunesImage']))
            <itunes:image href="{{ $item['itunesImage'] }}" />
@endif
@if (isset($item['mediaContent']))
            <media:content url="{{ $item['mediaContent']['url'] }}" type="{{ $item['mediaContent']['type'] }}">
                <media:thumbnail url="{{ $item['mediaContent']['thumb']['url'] }}" width="{{ $item['mediaContent']['thumb']['width'] }}" height="{{ $item['mediaContent']['thumb']['height'] }}" />
            </media:content>
@endif
        </item>
@endforeach
    </channel>
</rss>