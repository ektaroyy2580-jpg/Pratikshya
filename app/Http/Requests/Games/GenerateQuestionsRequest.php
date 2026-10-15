<?php

namespace App\Http\Requests\Games;

use App\Models\GameQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(GameQuestion::CATEGORIES))],
            'difficulty' => ['required', Rule::in(GameQuestion::DIFFICULTIES)],
            'count' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
