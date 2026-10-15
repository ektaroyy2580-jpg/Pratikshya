<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

/** Only the question id and the chosen option index are accepted — never a score or correctness flag. */
class AnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('play', $this->route('competition')) ?? false;
    }

    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', 'min:1'],
            'choice' => ['required', 'integer', 'between:0,3'],
        ];
    }
}
