<?php

namespace App\Http\Requests\Arcade;

use Illuminate\Foundation\Http\FormRequest;

/** Shape-only validation for answers, moves and results; game rules are checked by the game class. */
class ArcadeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('play', $this->route('arcadeSession')) ?? false;
    }

    public function rules(): array
    {
        return [
            'index' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'choice' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'guess' => ['sometimes', 'nullable', 'string', 'max:30'],
            'action' => ['sometimes', 'string', 'in:play,draw,pass'],
            'card' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'color' => ['sometimes', 'nullable', 'string', 'in:R,G,B,Y'],
            'uno' => ['sometimes', 'boolean'],
            'moves' => ['sometimes', 'array', 'max:200'],
            'moves.*' => ['array', 'size:2'],
            'moves.*.*' => ['integer', 'min:0', 'max:63'],
            'times' => ['sometimes', 'array', 'max:10'],
            'times.*' => ['integer'],
            'grid' => ['sometimes', 'array', 'size:81'],
            'grid.*' => ['nullable', 'integer', 'min:0', 'max:9'],
            'forfeit' => ['sometimes', 'boolean'],
        ];
    }
}
