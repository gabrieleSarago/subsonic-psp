<?php

namespace App\Http\Controllers;

use App\Http\Requests\RadioStreamRequest;
use App\Services\Radio\RadioStreamService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RadioStreamController extends Controller
{
    public function __construct(private readonly RadioStreamService $radio) {}

    /**
     * Stream a station to the PSP as MP3.
     */
    public function show(RadioStreamRequest $request): StreamedResponse
    {
        return $this->radio->stream($request->stationId());
    }
}
