<?php

namespace App\Http\Requests\Arcade;

use Illuminate\Foundation\Http\FormRequest;

class StartArcadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['variant' => ['nullable', 'string', 'max:20', 'alpha_num']];
    }
}
