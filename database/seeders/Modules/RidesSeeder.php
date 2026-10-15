<?php

namespace Database\Seeders\Modules;

use App\Models\Location;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\Rides\Providers\RideProviderRegistry;
use App\Services\Rides\TravelEstimator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent fictional demo data for Smart Explore & Rides. All trips are labelled demo/estimates.
 * Users: rides.demo@waitwise.np and rides.demo2@waitwise.np (password Demo@1234).
 */
class RidesSeeder extends Seeder
{
    public function run(): void
    {
        $a = User::firstOrCreate(['email' => 'rides.demo@waitwise.np'], ['name' => 'Rides Demo', 'password' => Hash::make('Demo@1234')]);
        $b = User::firstOrCreate(['email' => 'rides.demo2@waitwise.np'], ['name' => 'Rides Demo Two', 'password' => Hash::make('Demo@1234')]);

        $passport = Location::where('name', 'Department of Passports')->first() ?? Location::first();
        if (! $passport || ! $passport->lat) {
            $this->command?->warn('RidesSeeder: no location with coordinates found — skipped trips.');

            return;
        }

        $this->trip($a, 'Ratna Park', 27.7063, 85.3151, $passport, 'pathao', 'bike', 'handed_off', false);
        $this->trip($a, 'Kalanki', 27.6933, 85.2817, $passport, 'yango', 'car', 'demo_completed', true);
        // Second demo user opted in to carpool matching (so the anonymous count shows 1).
        $this->trip($b, 'Kalanki (demo)', 27.6950, 85.2840, $passport, 'indrive', 'car', 'quoted', false, true);
    }

    private function trip(User $u, string $from, float $lat, float $lng, Location $to, string $provider, string $vehicle, string $status, bool $demo, bool $consent = false): void
    {
        $travel = app(TravelEstimator::class);
        $route = $travel->route($lat, $lng, (float) $to->lat, (float) $to->lng);
        $minutes = array_map(fn ($m) => $m['minutes'], $travel->modes($route['road_km']));
        $quote = collect(app(RideProviderRegistry::class)->get($provider)->quote(['road_km' => $route['road_km'], 'minutes' => $minutes]))
            ->firstWhere('vehicle_type', $vehicle);

        $ride = RideRequest::firstOrNew(['user_id' => $u->id, 'pickup_label' => $from, 'destination_label' => $to->name, 'provider' => $provider]);
        $ride->forceFill([
            'user_id' => $u->id, 'provider' => $provider, 'vehicle_type' => $vehicle, 'mode' => $quote['mode'],
            'pickup_label' => $from, 'pickup_lat' => $lat, 'pickup_lng' => $lng,
            'destination_label' => $to->name, 'destination_lat' => $to->lat, 'destination_lng' => $to->lng,
            'location_id' => $to->id, 'distance_km' => $route['road_km'], 'est_minutes' => $quote['minutes'],
            'fare_low' => $quote['fare_low'], 'fare_high' => $quote['fare_high'], 'fare_source' => 'estimate',
            'status' => $status, 'is_demo' => $demo, 'consent_share' => $consent,
            'external_reference' => $demo ? ($ride->external_reference ?: 'DEMO-SEED01') : null,
            'handed_off_at' => $status === 'handed_off' ? now() : null,
            'demo_started_at' => $demo ? now()->subMinutes(10) : null,
        ])->save();
        // Keep the carpool example inside the matching window.
        if ($consent) {
            $ride->forceFill(['created_at' => now()])->save();
        }
    }
}
