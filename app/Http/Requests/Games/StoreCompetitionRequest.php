<?php

namespace App\Http\Requests\Games;

use App\Models\GameQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::in(array_keys(GameQuestion::CATEGORIES))],
            'visibility' => ['required', Rule::in(['open', 'invite'])],
            'max_players' => ['required', 'integer', 'between:2,4'],
            'question_count' => ['required', 'integer', Rule::in([5, 8, 10])],
            'seconds_per_question' => ['nullable', 'integer', Rule::in([10, 15, 20])],
        ];
    }
}
