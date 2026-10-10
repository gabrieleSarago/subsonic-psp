<?php

namespace App\Http\Requests;

class RadioFeedRequest extends RoutedRequest
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
            'part' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * The requested part, defaulting to the first one.
     */
    public function part(): int
    {
        return (int) ($this->route('part') ?? 1);
    }
}
