<?php

namespace App\Http\Requests;

class AudioStreamRequest extends RoutedRequest
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
            'song' => ['required', 'string', 'max:255'],
            'v' => ['sometimes', 'string', 'max:64'],
        ];
    }

    /**
     * The track identifier, taken from the route.
     */
    public function songId(): string
    {
        return (string) $this->route('song');
    }

    /**
     * The range requested by the client, forwarded to Navidrome untouched.
     */
    public function rangeHeader(): ?string
    {
        $range = $this->header('Range');

        return is_string($range) && trim($range) !== '' ? $range : null;
    }
}
