<?php

namespace Database\Seeders\Modules;

use App\Models\ExplorePreference;
use App\Models\HospitalProfile;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Models\User;
use App\Services\Explore\TravelEstimator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Explore demo data — FICTIONAL places generated around every existing location so each
 * category is represented. Addresses say "Demo listing". Hours are unverified unless stated.
 * Idempotent: updateOrCreate on (location_id, name).
 */
class ExploreSeeder extends Seeder
{
    /** [category, name pattern, subcategory, emoji, tip, dLat, dLng, visit, rating, hours, verified, partner, accessibility] */
    private const TEMPLATES = [
        ['Cafe', '%s Chiya Ghar', 'Tea house', '☕', 'Milk tea, black tea and quick snacks', 0.0016, -0.0011, 12, 4.4, '07:00-19:00', false, true, 'Step-free entrance; small tables'],
        ['Restaurant', '%s Thakali Bhansa', 'Nepali set meals', '🍛', 'Dal-bhat sets — busy around lunch', -0.0021, 0.0017, 25, 4.3, '10:00-21:00', false, true, 'One step at entrance'],
        ['Bookstore', '%s Pustak Bhandar', 'Books & stationery', '📚', 'Nepali novels, exam guides and stationery', 0.0030, 0.0024, 15, 4.5, '09:00-19:00', false, false, 'Narrow aisles'],
        ['Shopping Centre', '%s City Arcade', 'Small shopping centre', '🛍️', 'Phone shops, clothing and a photocopy stall', -0.0038, -0.0030, 20, 4.1, '10:00-20:00', false, false, 'Ramp at front; lift not confirmed'],
        ['Park', '%s Community Garden', 'Public garden', '🌳', 'Shaded benches and a short loop path', 0.0042, -0.0035, 12, 4.2, '06:00-18:00', true, false, 'Paved main path; some uneven ground'],
        ['Pharmacy', '%s Aushadhi Pasal', 'Pharmacy', '💊', 'Licensed pharmacy counter — ask the pharmacist', -0.0009, -0.0014, 8, 4.2, '07:00-21:00', false, false, 'Counter service at street level'],
        ['ATM', '%s ATM Lobby', 'ATM (2 machines)', '🏧', 'Cash withdrawal — machines may run out on holidays', 0.0006, 0.0010, 5, 4.0, null, false, false, 'Step-free lobby'],
        ['Hospital', '%s Sewa Polyclinic', 'Private clinic', '🏥', 'Outpatient clinic — for emergencies call 102', 0.0052, 0.0041, 30, 4.0, null, false, false, 'Ramp at entrance'],
        ['Convenience Store', '%s Kirana & Daily Needs', 'Convenience store', '🏪', 'Water, snacks and phone top-up cards', -0.0013, 0.0007, 6, 4.1, '06:30-21:30', false, false, 'Street-level counter'],
        ['Transport', '%s Taxi & Ride Pickup Point', 'Pickup point', '🚕', 'Marked bay where taxis and ride apps can stop', 0.0004, -0.0006, 2, 4.0, null, false, false, 'Kerbside, no shelter'],
        ['Transport', '%s Bus & Micro Stop', 'Public transport stop', '🚌', 'Sajha and micro-bus routes stop here', -0.0024, -0.0008, 2, 3.9, null, false, false, 'Roadside stop; no seating'],
    ];

    public function run(): void
    {
        $travel = app(TravelEstimator::class);

        foreach (Location::orderBy('id')->get() as $loc) {
            $prefix = $this->prefix($loc);
            $templates = self::TEMPLATES;
            if (strcasecmp($loc->type, 'Hospital') === 0) {
                // Hospitals get a second pharmacy close to the gate.
                $templates[] = ['Pharmacy', '%s Sajilo Pharmacy', 'Pharmacy', '💊', 'Pharmacy opposite the main gate', 0.0011, 0.0019, 8, 4.3, '24 hours', false, false, 'Step-free counter'];
            }
            foreach ($templates as [$cat, $pattern, $sub, $emoji, $tip, $dLat, $dLng, $visit, $rating, $hours, $verified, $partner, $access]) {
                $lat = round((float) $loc->lat + $dLat, 6);
                $lng = round((float) $loc->lng + $dLng, 6);
                $walk = $travel->straight((float) $loc->lat, (float) $loc->lng, $lat, $lng)['walk_minutes'];
                NearbyPlace::updateOrCreate(
                    ['location_id' => $loc->id, 'name' => sprintf($pattern, $prefix)],
                    [
                        'category' => $cat, 'subcategory' => $sub, 'emoji' => $emoji, 'tip' => $tip,
                        'lat' => $lat, 'lng' => $lng, 'walk_minutes' => $walk, 'visit_minutes' => $visit, 'rating' => $rating,
                        'address' => 'Demo listing — fictional place near '.$loc->name,
                        'phone' => null,
                        'opening_hours' => $hours, 'hours_verified' => $verified,
                        'is_partner' => $partner, 'is_sponsored' => false,
                        'accessibility_notes' => $access,
                    ]
                );
            }

            if (strcasecmp($loc->type, 'Hospital') === 0) {
                $this->hospitalProfile($loc);
            }
        }

        $user = User::firstOrCreate(['email' => 'explore.demo@waitwise.np'], ['name' => 'Explore Demo', 'password' => 'Demo@1234']);
        if (! ExplorePreference::where('user_id', $user->id)->exists()) {
            $p = new ExplorePreference(['suggestions_enabled' => true, 'categories' => null]);
            $p->user_id = $user->id;
            $p->save();
        }
    }

    private function prefix(Location $loc): string
    {
        $area = trim(Str::before((string) $loc->area, ','));

        return $area !== '' ? $area : trim(Str::before($loc->name, ' '));
    }

    private function hospitalProfile(Location $loc): void
    {
        HospitalProfile::updateOrCreate(['location_id' => $loc->id], [
            'departments' => [
                ['name' => 'Emergency', 'where' => 'Separate emergency entrance', 'note' => 'Open 24 hours — go straight there in an emergency'],
                ['name' => 'General Medicine OPD', 'where' => 'OPD block, ground floor', 'note' => 'Token required — join the queue in this app or at the counter'],
                ['name' => 'Paediatrics', 'where' => 'OPD block, first floor', 'note' => null],
                ['name' => 'Obstetrics & Gynaecology', 'where' => 'OPD block, first floor', 'note' => null],
                ['name' => 'Orthopaedics', 'where' => 'OPD block, second floor', 'note' => null],
                ['name' => 'ENT', 'where' => 'OPD block, second floor', 'note' => null],
                ['name' => 'Eye', 'where' => 'OPD block, second floor', 'note' => null],
                ['name' => 'Dental', 'where' => 'Annex building', 'note' => null],
            ],
            'lab_services' => [
                ['name' => 'Blood sample collection', 'where' => 'Laboratory, ground floor', 'note' => 'Bring your lab request slip and receipt'],
                ['name' => 'Urine & stool sample drop-off', 'where' => 'Laboratory, ground floor', 'note' => null],
                ['name' => 'X-ray', 'where' => 'Radiology, ground floor', 'note' => null],
                ['name' => 'Ultrasound', 'where' => 'Radiology, ground floor', 'note' => 'Booking may be needed'],
                ['name' => 'ECG', 'where' => 'OPD block, ground floor', 'note' => null],
                ['name' => 'Lab report collection', 'where' => 'Report counter', 'note' => 'Use the Lab Report Collection queue where available'],
            ],
            'assistance_desks' => [
                ['name' => 'Information & Help Desk', 'where' => 'Main entrance', 'note' => 'Directions, registration help, lost & found'],
                ['name' => 'Patient Welfare / Social Service Unit', 'where' => 'Administration block', 'note' => 'Support for patients who need financial or social assistance'],
                ['name' => 'Health Insurance Desk', 'where' => 'Near OPD registration', 'note' => 'Bring your insurance card'],
                ['name' => 'Wheelchair & Porter Request', 'where' => 'Help desk at main entrance', 'note' => 'Ask any staff member'],
            ],
            'accessibility' => [
                'Ramp at the main entrance',
                'Wheelchairs available at the help desk (subject to availability)',
                'Lift in the OPD block',
                'Accessible toilet on the ground floor',
                'Priority seating for elderly, pregnant and disabled patients',
            ],
            'emergency_contacts' => [
                ['label' => 'Hospital emergency desk', 'number' => null, 'note' => 'Use the number displayed at the hospital entrance — not listed in this demo'],
            ],
            'emergency_desk' => 'Emergency department — separate entrance; ask any staff member or security guard',
            'official_source' => 'Demo data — confirm with hospital',
            'is_demo' => true,
        ]);
    }
}
