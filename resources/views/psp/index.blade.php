{{--
    The page the PSP browser opens.

    Constraints that shaped this template: no JavaScript, no build step, and a
    screen of 480x272 pixels. Everything is inline so the device needs nothing
    but its own parser, the layout is table based rather than a grid, and no
    font size is declared in pixels, because the PSP's own zoom applies to the
    page as a whole.
--}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=460">
    <title>{{ config('app.name', 'PSP RSS') }}</title>
    <style type="text/css">
        body {
            margin: 0;
            padding: 8px;
            background: #ffffff;
            color: #000000;
            font-family: sans-serif;
        }
        h1 {
            margin: 0 0 2px 0;
            font-size: 1.2em;
        }
        h2 {
            margin: 14px 0 4px 0;
            padding-bottom: 2px;
            border-bottom: 2px solid #000000;
            font-size: 1.1em;
        }
        p.count {
            margin: 0 0 8px 0;
            font-size: 0.8em;
            color: #555555;
        }
        ul.jump {
            margin: 0 0 8px 0;
            padding: 0;
            list-style: none;
        }
        ul.jump li {
            display: inline;
            margin-right: 6px;
            font-size: 0.9em;
        }
        table.list {
            width: 100%;
            border-collapse: collapse;
        }
        table.list td {
            padding: 3px 2px;
            border-bottom: 1px dotted #999999;
            vertical-align: top;
            font-size: 0.9em;
        }
        td.cover {
            width: 1%;
            padding-right: 6px;
        }
        td.cover img {
            width: 40px;
            height: 40px;
            border: 1px solid #000000;
        }
        td.name {
            width: 100%;
        }
        td.name .title {
            display: block;
            font-weight: bold;
        }
        td.name .sub {
            display: block;
            font-size: 0.85em;
            color: #555555;
        }
        td.feed {
            width: 1%;
            text-align: right;
            white-space: nowrap;
        }
        td.feed a {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #000000;
            background: #eeeeee;
            color: #000000;
            font-weight: bold;
            text-decoration: none;
        }
        td.feed a.more {
            margin-left: 3px;
            font-weight: normal;
        }
        p.empty {
            font-size: 0.9em;
            color: #555555;
        }
        p.footer {
            margin-top: 16px;
            padding-top: 4px;
            border-top: 1px solid #999999;
            font-size: 0.75em;
            color: #777777;
        }
    </style>
</head>
<body>

<h1>{{ config('app.name', 'PSP RSS') }}</h1>
<p class="count">{{ $playlistCount }} playlist, {{ $albumCount }} album</p>

@if ($playlists->isNotEmpty())
    <h2>Playlist</h2>
    <table class="list">
@foreach ($playlists as $link)
        <tr>
            <td class="name">
                <span class="title">{{ $link->title }}</span>
                <span class="sub">{{ $link->meta }}</span>
            </td>
@include('psp.parts.feed-links', ['link' => $link, 'urls' => $urls])
        </tr>
@endforeach
    </table>
@else
    <h2>Playlist</h2>
    <p class="empty">Nessuna playlist.</p>
@endif

<h2>Album</h2>

@if ($albums->isEmpty())
    <p class="empty">Nessun album.</p>
@else
    @if ($initials->count() > 1)
        <ul class="jump">
            <li>Salta a:</li>
@foreach ($initials as $initial)
            <li><a href="#lettera-{{ rawurlencode($initial) }}">{{ $initial }}</a></li>
@endforeach
        </ul>
    @endif

    <table class="list">
@foreach ($albums as $link)
@if ($loop->first || $link->initial !== $albums[$loop->index - 1]->initial)
        <tr>
            <td colspan="3"><strong id="lettera-{{ rawurlencode($link->initial) }}">{{ $link->initial }}</strong></td>
        </tr>
@endif
        <tr>
            <td class="cover">
@if ($urls->cover($link->coverArt, 100) !== null)
                <img src="{{ $urls->cover($link->coverArt, 100) }}" alt="">
@endif
            </td>
            <td class="name">
                <span class="title">{{ $link->title }}</span>
                <span class="sub">{{ $link->subtitle }} - {{ $link->meta }}</span>
            </td>
@include('psp.parts.feed-links', ['link' => $link, 'urls' => $urls])
        </tr>
@endforeach
    </table>
@endif

<p class="footer">
    Feed RSS serviti da {{ config('app.name', 'PSP RSS') }}.
    Ogni album e ogni playlist ha un feed proprio; le raccolte con oltre
    {{ config('navidrome.feed_page_size') }} brani sono divise in parti.
</p>

</body>
</html>