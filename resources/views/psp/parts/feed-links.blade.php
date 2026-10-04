{{--
    The feed links of one album or playlist.

    A collection longer than the limit of the device is served as a sequence of
    feeds: the first one keeps the short URL, the others carry a part number.
--}}
<td class="feed">
    <a href="{{ $link->feedUrl($urls) }}">RSS</a>
@if ($link->parts > 1)
    <span>+{{ $link->parts - 1 }}</span>
@foreach (range(2, $link->parts) as $part)
    <a class="more" href="{{ $link->feedUrl($urls, $part) }}">{{ $part }}</a>
@endforeach
@endif
</td>