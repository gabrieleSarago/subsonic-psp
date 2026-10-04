<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Date formatting for RSS.
 */
final class RssDate
{
    /**
     * Format a date as RFC 822 in UTC, the format RSS 2.0 requires for
     * pubDate.
     *
     * Carbon's own toRssString() renders the zone as "+0000". That is legal
     * but not what most feed readers, and the PSP among them, expect, so the
     * "GMT" designator is emitted explicitly.
     */
    public static function rfc822(?CarbonInterface $date): string
    {
        if (! $date instanceof CarbonInterface) {
            return '';
        }

        return $date->clone()->utc()->format('D, d M Y H:i:s').' GMT';
    }
}
