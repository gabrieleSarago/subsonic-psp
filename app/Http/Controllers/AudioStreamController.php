<?php

namespace App\Http\Controllers;

use App\Http\Requests\AudioStreamRequest;
use App\Services\AudioStreamService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AudioStreamController extends Controller
{
    public function __construct(private readonly AudioStreamService $audio) {}

    /**
     * Proxy a track to the PSP as MP3.
     */
    public function show(AudioStreamRequest $request): StreamedResponse
    {
        return $this->audio->stream($request->songId(), $request->rangeHeader());
    }
}
