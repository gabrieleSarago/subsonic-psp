{{--
    RSS 2.0 feed for the PSP.

    This replaces the view shipped with spatie/laravel-feed, which does not emit
    an <enclosure> element. The PSP downloads the audio of an item from that
    element, so a feed without it is a feed with no music.

    Every field of a feed item is read through a null coalescing default.
    Spatie\Feed\FeedItem declares typed properties without a default value and
    throws when one that was never assigned is read, while "isset" and "??" stay
    safe. SongFeedItemFactory assigns all of them; the defaults here only keep a
    partial item from taking the whole page down.

    Dates are formatted by App\Support\RssDate instead of the timestamp() helper
    of the package, which renders the zone as "+0000" rather than "GMT".
--}}
@php
    $channelUpdated = $items
        ->map(fn ($item) => $item->updated ?? null)
        ->filter(fn ($date) => $date instanceof \Carbon\CarbonInterface)
        ->sortDesc()
        ->first() ?? now();
@endphp
<?= '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <atom:link href="{{ url($meta['link']) }}" rel="self" type="application/rss+xml" />
        <title>{{ $meta['title'] }}</title>
        <link>{{ url($meta['link']) }}</link>
        <description>{{ $meta['description'] }}</description>
        <language>{{ $meta['language'] }}</language>
        <generator>Laravel</generator>
        <lastBuildDate>{{ \App\Support\RssDate::rfc822($channelUpdated) }}</lastBuildDate>
@if (($meta['image'] ?? '') !== '')
        <image>
            <url>{{ $meta['image'] }}</url>
            <title>{{ $meta['title'] }}</title>
            <link>{{ url($meta['link']) }}</link>
        </image>
@endif
@foreach ($items as $item)
        <item>
            <title>{{ $item->title ?? '' }}</title>
            <link>{{ $item->link ?? '' }}</link>
            <guid isPermaLink="false">{{ $item->id ?? '' }}</guid>
            <description>{{ $item->summary ?? '' }}</description>
            <pubDate>{{ \App\Support\RssDate::rfc822($item->updated ?? $channelUpdated) }}</pubDate>
@if (($item->authorName ?? '') !== '')
            <author>{{ $item->authorName }}</author>
@endif
@foreach (($item->category ?? []) as $category)
            <category>{{ $category }}</category>
@endforeach
@if (($item->enclosure ?? '') !== '')
            <enclosure url="{{ $item->enclosure }}" length="{{ (int) ($item->enclosureLength ?? 0) }}" type="{{ $item->enclosureType ?? 'audio/mpeg' }}" />
@endif
        </item>
@endforeach
    </channel>
</rss>