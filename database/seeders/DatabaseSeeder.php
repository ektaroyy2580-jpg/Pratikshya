<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Safe to re-run: firstOrCreate never duplicates rows or resets live queue numbers.
        \App\Models\User::firstOrCreate(['email' => 'demo@waitwise.np'], ['name' => 'Demo User', 'password' => 'demo']);

        $data = [
            [
                'name' => 'Transport Management Office', 'area' => 'Ekantakuna, Lalitpur', 'type' => 'Government', 'icon' => 'bi-car-front-fill',
                'lat' => 27.666932, 'lng' => 85.307710,
                'services' => [
                    // Featured demo: joining gives token D-24, 8 ahead x 4 min = 32 minutes, Counter 2
                    ['Driving Licence Renewal', 'D', 2, 4, 15, 23, ['Old driving licence (original)', 'Citizenship certificate copy', 'Medical fitness report', 'Two passport-size photos', 'Online application receipt']],
                    ['Vehicle Bluebook Renewal', 'B', 4, 6, 41, 46, ['Original bluebook', 'Vehicle tax receipt', 'Insurance certificate', 'Citizenship copy']],
                    ['New Licence Written Exam', 'E', 6, 3, 102, 121, ['Exam admit card', 'Citizenship certificate', 'Photo ID']],
                ],
                'places' => [
                    ['Jhamsikhel Brew Café', 'Cafe', '☕', 'Grab a flat white and a quiet corner', 0.0025, 0.0018, 4, 15, 4.6],
                    ['Ekantakuna Momo House', 'Restaurant', '🥟', 'Buff momo plate ready in 10 min', -0.0014, 0.0022, 3, 18, 4.5],
                    ['Jhamsikhel Book Nook', 'Bookstore', '📚', 'Browse Nepali poetry & travel guides', 0.0042, -0.0016, 6, 12, 4.7],
                    ['Bhaisepati Green Park', 'Park', '🌳', 'Short shaded walk with benches', -0.0035, -0.0028, 6, 10, 4.3],
                    ['Lalitpur Fresh Juice Bar', 'Cafe', '🥤', 'Sugarcane & seasonal fruit juice', 0.0010, -0.0024, 3, 8, 4.4],
                    ['Patan Durbar Square', 'Heritage', '🛕', 'UNESCO site — needs a longer wait', 0.0130, 0.0150, 18, 40, 4.9],
                    ['Ekantakuna ATM & Print Shop', 'Errand', '🖨️', 'Print missing photocopies here!', -0.0008, 0.0009, 2, 5, 4.1],
                ],
            ],
            [
                'name' => 'Department of Passports', 'area' => 'Tripureshwor, Kathmandu', 'type' => 'Government', 'icon' => 'bi-passport-fill',
                'lat' => 27.694600, 'lng' => 85.313500,
                'services' => [
                    ['Passport Application (Normal)', 'P', 3, 5, 210, 228, ['Citizenship certificate (original)', 'Pre-enrolment receipt', 'Bank fee voucher', 'Old passport (if any)']],
                    ['Passport Collection', 'C', 7, 2, 88, 101, ['Collection slip', 'Citizenship certificate']],
                ],
                'places' => [
                    ['Tripureshwor Tea Stall', 'Cafe', '🍵', 'Classic chiya + sel roti', 0.0012, 0.0015, 3, 10, 4.4],
                    ['Kalmochan Ghat Walk', 'Park', '🌊', 'Riverside stroll along the Bagmati', -0.0030, 0.0010, 5, 15, 4.2],
                    ['Thapathali Thakali Kitchen', 'Restaurant', '🍛', 'Quick dal-bhat set', 0.0028, -0.0020, 5, 25, 4.6],
                    ['Tripureshwor Book Corner', 'Bookstore', '📚', 'Huge academic & fiction section', 0.0045, 0.0030, 7, 15, 4.5],
                ],
            ],
            [
                'name' => 'Sagarmatha Demo Bank — New Road', 'area' => 'New Road, Kathmandu', 'type' => 'Bank', 'icon' => 'bi-bank2',
                'lat' => 27.703600, 'lng' => 85.310900,
                'services' => [
                    ['Cash Deposit / Withdrawal', 'T', 1, 3, 57, 64, ['Deposit slip', 'Cheque or account number', 'Photo ID']],
                    ['Account Opening', 'A', 5, 12, 8, 11, ['Citizenship certificate', 'Two photos', 'Utility bill / address proof', 'Nominee details']],
                ],
                'places' => [
                    ['Indra Chowk Lassi Corner', 'Cafe', '🥛', 'Famous thick lassi', 0.0020, -0.0012, 4, 8, 4.7],
                    ['Basantapur Durbar Square', 'Heritage', '🛕', 'Hanuman Dhoka palace complex', -0.0025, -0.0040, 7, 25, 4.8],
                    ['New Road Page Turners', 'Bookstore', '📚', 'Maps, art books, postcards', 0.0040, 0.0010, 8, 15, 4.6],
                    ['Ratna Park', 'Park', '🌳', 'Green break in the city', 0.0010, 0.0050, 6, 10, 4.0],
                ],
            ],
            [
                'name' => 'Kathmandu City Hospital OPD', 'area' => 'Mahaboudha, Kathmandu', 'type' => 'Hospital', 'icon' => 'bi-hospital-fill',
                'lat' => 27.705100, 'lng' => 85.313900,
                'services' => [
                    ['General Medicine OPD', 'G', 3, 6, 130, 142, ['OPD ticket', 'Previous prescriptions', 'Health insurance card (if any)']],
                    ['Lab Report Collection', 'L', 8, 2, 300, 309, ['Lab receipt', 'Patient ID']],
                ],
                'places' => [
                    ['Mahaboudha Temple', 'Heritage', '🛕', 'Terracotta temple of a thousand Buddhas', 0.0015, 0.0010, 4, 10, 4.6],
                    ['Asan Bazaar', 'Market', '🧺', 'Spices, beads & tea leaves', -0.0020, 0.0018, 6, 20, 4.5],
                    ['Hospital Canteen Cafe', 'Cafe', '☕', 'Quick tea without leaving the area', 0.0004, -0.0006, 1, 10, 3.9],
                ],
            ],
            [
                'name' => 'Ward No. 10 Office', 'area' => 'Baneshwor, Kathmandu', 'type' => 'Ward Office', 'icon' => 'bi-building-fill',
                'lat' => 27.690800, 'lng' => 85.335100,
                'services' => [
                    ['Birth / Death Registration', 'R', 1, 7, 12, 16, ['Hospital birth certificate', 'Parents\' citizenship copies', 'Marriage registration']],
                    ['Recommendation Letter', 'S', 2, 5, 33, 38, ['Application letter', 'Citizenship copy', 'Land tax receipt']],
                ],
                'places' => [
                    ['Baneshwor Coffee Lab', 'Cafe', '☕', 'Specialty pour-over', 0.0018, 0.0012, 3, 15, 4.5],
                    ['Shankhamul Park', 'Park', '🌳', 'Riverside park & pond', -0.0045, -0.0020, 8, 15, 4.3],
                    ['Mandala Bookpoint', 'Bookstore', '📚', 'Nepali literature & stationery', 0.0025, -0.0018, 5, 12, 4.4],
                ],
            ],
        ];

        foreach ($data as $loc) {
            $location = Location::firstOrCreate(['name' => $loc['name']], [
                'area' => $loc['area'], 'type' => $loc['type'], 'icon' => $loc['icon'],
                'lat' => $loc['lat'], 'lng' => $loc['lng'],
            ]);
            foreach ($loc['services'] as [$name, $prefix, $counter, $avg, $cur, $last, $docs]) {
                $location->services()->firstOrCreate(['prefix' => $prefix], [
                    'name' => $name, 'prefix' => $prefix, 'counter' => $counter, 'avg_minutes' => $avg,
                    'current_number' => $cur, 'last_number' => $last, 'documents' => $docs,
                ]);
            }
            foreach ($loc['places'] as [$name, $cat, $emoji, $tip, $dLat, $dLng, $walk, $visit, $rating]) {
                $location->places()->firstOrCreate(['name' => $name], [
                    'category' => $cat, 'emoji' => $emoji, 'tip' => $tip,
                    'lat' => $loc['lat'] + $dLat, 'lng' => $loc['lng'] + $dLng,
                    'walk_minutes' => $walk, 'visit_minutes' => $visit, 'rating' => $rating,
                ]);
            }
        }

        $this->call(RolesAndCountersSeeder::class);

        // Feature modules (all idempotent; LOCAL DEMO DATA ONLY).
        // GamesDemoSeeder is deliberately excluded: it issues real queue tokens.
        $this->call([
            Modules\PlatformSeeder::class,
            Modules\ExploreSeeder::class,
            Modules\GamesSeeder::class,
            Modules\ArcadeSeeder::class,
            Modules\IntegritySeeder::class,
            Modules\LibrarySeeder::class,
            Modules\EnglishSeeder::class,
            Modules\PreorderSeeder::class,
            Modules\CafesSeeder::class,
            Modules\RidesSeeder::class,
            Modules\BillsSeeder::class,
            Modules\AdsSeeder::class,
            Modules\OpsSeeder::class,
        ]);
    }
}
