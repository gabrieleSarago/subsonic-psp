<?php

use App\Support\Duration;
use App\Support\RssDate;
use Illuminate\Support\Carbon;

it('writes a length under an hour as minutes and seconds', function (): void {
    expect(Duration::human(0))->toBe('0:00')
        ->and(Duration::human(9))->toBe('0:09')
        ->and(Duration::human(60))->toBe('1:00')
        ->and(Duration::human(191))->toBe('3:11')
        ->and(Duration::human(3599))->toBe('59:59');
});

it('writes a length of an hour or more with hours', function (): void {
    expect(Duration::human(3600))->toBe('1:00:00')
        ->and(Duration::human(3725))->toBe('1:02:05')
        ->and(Duration::human(36000))->toBe('10:00:00');
});

it('writes a date as RFC 822 with the GMT designator', function (): void {
    // Carbon's own toRssString() renders the zone as "+0000"; the GMT
    // designator is what feed readers, and the device, expect.
    expect(RssDate::rfc822(Carbon::parse('2026-01-15T10:00:00Z')))
        ->toBe('Thu, 15 Jan 2026 10:00:00 GMT')
        ->and(Carbon::parse('2026-01-15T10:00:00Z')->toRssString())
        ->not->toBe('Thu, 15 Jan 2026 10:00:00 GMT');
});

it('converts a date to GMT before writing it', function (): void {
    $date = Carbon::parse('2026-01-15T12:00:00', 'Europe/Rome');

    expect(RssDate::rfc822($date))->toBe('Thu, 15 Jan 2026 11:00:00 GMT');
});

it('writes nothing for a missing date instead of failing', function (): void {
    expect(RssDate::rfc822(null))->toBe('');
});
