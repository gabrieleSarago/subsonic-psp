<?php

namespace App\Http\Requests;

class RadioIconRequest extends RoutedRequest
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
}
