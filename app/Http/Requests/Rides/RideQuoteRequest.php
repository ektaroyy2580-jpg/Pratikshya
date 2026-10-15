<?php

namespace App\Http\Requests\Rides;

use App\Services\Rides\Geo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RideQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $b = config('rides.bounds');
        $lat = ['required', 'numeric', 'between:'.$b['lat_min'].','.$b['lat_max']];
        $lng = ['required', 'numeric', 'between:'.$b['lng_min'].','.$b['lng_max']];

        return [
            'pickup_lat' => $lat,
            'pickup_lng' => $lng,
            'pickup_label' => ['nullable', 'string', 'max:120'],
            'destination_lat' => $lat,
            'destination_lng' => $lng,
            'destination_label' => ['nullable', 'string', 'max:120'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'nearby_place_id' => ['nullable', 'integer', 'exists:nearby_places,id'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.between' => 'Pick a point inside Nepal — that location is outside our service area.',
            '*.numeric' => 'That map point is not a valid coordinate.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $km = Geo::haversine((float) $this->input('pickup_lat'), (float) $this->input('pickup_lng'), (float) $this->input('destination_lat'), (float) $this->input('destination_lng'));
            if ($km < (float) config('rides.min_distance_km')) {
                $v->errors()->add('pickup_lat', 'Pickup and destination are the same place — move the pickup pin.');
            } elseif ($km > (float) config('rides.max_distance_km')) {
                $v->errors()->add('pickup_lat', 'That trip is longer than '.config('rides.max_distance_km').' km — the planner covers trips within the valley and nearby.');
            }
        }];
    }
}
