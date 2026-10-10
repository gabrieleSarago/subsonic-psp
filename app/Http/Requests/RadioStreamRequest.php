<?php

namespace App\Http\Requests;

class RadioStreamRequest extends RoutedRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'station' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * The station identifier, taken from the route.
     */
    public function stationId(): string
    {
        return (string) $this->route('station');
    }

    /**
     * The range requested by the client, or null when it asked for the whole
     * stream. The device reads the tail of an enclosure before playing it, so
     * the answer has to carry a range when one is asked for.
     */
    public function rangeHeader(): ?string
    {
        $range = $this->header('Range');

        return is_string($range) && trim($range) !== '' ? $range : null;
    }
}
