<?php

namespace App\Services\Radio\Contracts;

use App\Data\Radio\ProbeResponse;

/**
 * The network access of the probe, kept behind an interface so the probe can
 * be exercised against canned responses.
 */
interface StreamProbeFetcher
{
    /**
     * Open a stream, read its headers and up to $maxBytes of body, then close
     * it. Null when the URL cannot be reached or answers with an error.
     */
    public function fetchStream(string $url, int $maxBytes): ?ProbeResponse;

    /**
     * Fetch a short document (a homepage or an API answer), capped in size.
     * Null when the URL cannot be reached or answers with an error.
     */
    public function fetchText(string $url, int $maxBytes): ?string;
}
