<?php

namespace App\Http\Requests\Ads;

use App\Models\AdCampaign;
use App\Models\Advertiser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create / edit an ad campaign from the advertiser portal. Authorization is enforced by AdCampaignPolicy in the controller. */
class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'allows_rewarded' => $this->boolean('allows_rewarded'),
            'remove_image' => $this->boolean('remove_image'),
            'target_location_id' => $this->input('target_location_id') ?: null,
            'target_category' => $this->input('target_category') ?: null,
            'cta_url' => is_string($this->input('cta_url')) ? trim($this->input('cta_url')) : $this->input('cta_url'),
        ]);
    }

    public function rules(): array
    {
        $creating = ! $this->route('campaign');
        $mimes = config('ads.creative.mimes');

        return [
            'advertiser_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::in(Advertiser::idsFor($this->user()))],
            'name' => ['required', 'string', 'max:120'],
            'headline' => ['required', 'string', 'max:80'],
            'body' => ['nullable', 'string', 'max:200'],
            'cta_label' => ['required', 'string', 'max:30'],
            'cta_url' => ['required', 'string', 'max:255', function ($attr, $value, $fail) {
                if (! AdCampaign::safeDestination($value)) {
                    $fail('The link must be an internal page (starting with /) or a secure https:// address.');
                }
            }],
            'creative_theme' => ['required', Rule::in(array_keys(config('ads.themes')))],
            'creative_image' => ['nullable', 'file', 'mimes:'.implode(',', $mimes), 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.(int) config('ads.creative.max_kb')],
            'remove_image' => ['boolean'],
            'target_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'target_category' => ['nullable', Rule::in(array_keys(config('ads.categories')))],
            'placements' => ['required', 'array', 'min:1'],
            'placements.*' => ['distinct', Rule::in(array_keys(config('ads.placements')))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'after_or_equal:today'],
            'budget_total' => ['required', 'numeric', 'decimal:0,2', 'min:100', 'max:10000000'],
            'daily_budget' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'lte:budget_total'],
            'cpm_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100000'],
            'cpc_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100000'],
            'allows_rewarded' => ['boolean'],
            'healthcare_disclosure' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', Rule::in(['draft', 'submit'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            // Real image content check (not just the extension / claimed MIME type).
            $file = $this->file('creative_image');
            if ($file instanceof UploadedFile && $file->isValid() && ! $v->errors()->has('creative_image')) {
                $info = @getimagesize($file->getRealPath());
                if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    $v->errors()->add('creative_image', 'The creative must be a real JPG, PNG or WebP image.');
                }
            }
            if ($this->isHealthcare() && trim((string) $this->input('healthcare_disclosure')) === '') {
                $v->errors()->add('healthcare_disclosure', 'Healthcare ads need a disclosure, e.g. "Sponsored by a pharmacy. Not medical advice; consult a doctor."');
            }
            if (in_array('queue_page', (array) $this->input('placements'), true) && $this->isHealthcare()) {
                $v->errors()->add('placements', 'Healthcare ads cannot run on queue pages, where they could be mistaken for medical guidance.');
            }
        }];
    }

    public function advertiser(): ?Advertiser
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof AdCampaign ? $campaign->advertiser : Advertiser::find($this->input('advertiser_id'));
    }

    public function isHealthcare(): bool
    {
        $healthcare = config('ads.healthcare_categories');

        return in_array($this->input('target_category'), $healthcare, true) || (bool) $this->advertiser()?->isHealthcare();
    }

    public function messages(): array
    {
        return [
            'creative_image.max' => 'The creative image may not be larger than 1 MB.',
            'creative_image.mimes' => 'The creative must be a JPG, PNG or WebP image.',
            'creative_image.mimetypes' => 'The creative must be a JPG, PNG or WebP image.',
            'advertiser_id.in' => 'You can only create campaigns for your own business.',
            'daily_budget.lte' => 'The daily budget cannot exceed the total budget.',
        ];
    }
}
