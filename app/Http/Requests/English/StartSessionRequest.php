<?php

namespace App\Http\Requests\English;

use App\Models\EnglishPracticeSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'scenario_id' => ['required', 'integer', Rule::exists('english_scenarios', 'id')->where('is_active', true)],
            'minutes' => ['required', 'integer', Rule::in(EnglishPracticeSession::DURATIONS)],
            'mode' => ['required', Rule::in(['text', 'voice'])],
            // The mic needs explicit acknowledgement of the privacy notice.
            'consent_audio' => ['nullable', 'boolean', 'accepted_if:mode,voice'],
            // Audio is never accepted by this module.
            'audio' => ['prohibited'],
            'recording' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent_audio.accepted_if' => 'Please confirm the microphone notice to use voice mode (or choose text mode).',
            'audio.prohibited' => 'Audio uploads are not accepted — only text is sent.',
            'recording.prohibited' => 'Audio uploads are not accepted — only text is sent.',
        ];
    }
}
