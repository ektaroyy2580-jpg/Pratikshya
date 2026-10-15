<?php

namespace App\Http\Requests\English;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One answer: typed text or a browser speech transcript. Text only — any audio field or
 * uploaded file is rejected so recordings can never reach the server through this endpoint.
 */
class SubmitTurnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('practice')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('user_text'))) {
            $this->merge(['user_text' => trim(preg_replace('/\s+/u', ' ', strip_tags($this->input('user_text'))) ?? '')]);
        }
    }

    public function rules(): array
    {
        return [
            'user_text' => ['required', 'string', 'min:1', 'max:'.(int) config('english.max_turn_chars', 1200), 'regex:/[A-Za-z]/'],
            'input_mode' => ['required', Rule::in(['text', 'voice'])],
            'elapsed_seconds' => ['nullable', 'integer', 'min:0', 'max:900'],
            // Speech-to-text engine label for voice turns: on-device (Vosk/WASM) or the browser's Web Speech API.
            'stt_engine' => ['nullable', 'string', Rule::in(['ondevice', 'browser']), 'prohibited_unless:input_mode,voice'],
            'audio' => ['prohibited'],
            'audio_data' => ['prohibited'],
            'recording' => ['prohibited'],
            'blob' => ['prohibited'],
            'pcm' => ['prohibited'],
            'samples' => ['prohibited'],
            'wav' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if (count($this->allFiles()) > 0) {
                $v->errors()->add('user_text', 'File uploads are not accepted — only text is sent.');
            }
            if ($this->input('input_mode') === 'voice' && ! $this->route('practice')->consent_audio) {
                $v->errors()->add('input_mode', 'Turn on voice mode and confirm the microphone notice first.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'user_text.required' => 'Say or type an answer first.',
            'user_text.regex' => 'Please answer in English words.',
            'user_text.max' => 'That answer is a little long — please keep it under :max characters.',
            '*.prohibited' => 'Audio is not accepted — only text is sent.',
            'stt_engine.prohibited_unless' => 'A speech engine can only be given for voice answers.',
            'stt_engine.in' => 'Unknown speech engine.',
        ];
    }
}
