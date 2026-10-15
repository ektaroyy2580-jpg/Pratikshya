<?php

namespace Database\Seeders\Modules;

use App\Models\Location;
use App\Models\Organization;
use App\Models\RewardCatalogueItem;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Rewards\RewardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LOCAL DEMO ONLY — Platform module. Idempotent (firstOrCreate / updateOrCreate on natural keys).
 *  - Groups any location without an organization into demo organizations.
 *  - platform.demo@waitwise.np  / Demo@1234 → staff + organization admin of the Department of Transport Management (demo)
 *  - platform.user@waitwise.np  / Demo@1234 → normal user with demo WaitPoints
 *  - Demo partner reward catalogue (fictional partners).
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $this->organizations();
        $this->catalogue();

        $orgAdmin = User::firstOrCreate(['email' => 'platform.demo@waitwise.np'], ['name' => 'Bikash Org Admin (demo)', 'password' => 'Demo@1234']);
        $orgAdmin->forceFill(['role' => 'staff'])->save();
        $transportOrg = Organization::where('slug', 'department-of-transport-management-demo')->first();
        if ($transportOrg) {
            DB::table('organization_user')->updateOrInsert(
                ['organization_id' => $transportOrg->id, 'user_id' => $orgAdmin->id],
                ['role' => 'org_admin', 'created_at' => now(), 'updated_at' => now()]
            );
            foreach ($transportOrg->locations as $loc) {
                StaffAssignment::firstOrCreate(['user_id' => $orgAdmin->id, 'location_id' => $loc->id], ['is_approved' => true]);
            }
        }

        $user = User::firstOrCreate(['email' => 'platform.user@waitwise.np'], ['name' => 'Asha Demo User', 'password' => 'Demo@1234']);
        UserPreference::for($orgAdmin);
        UserPreference::for($user);

        $rewards = app(RewardService::class);
        $rewards->award($user, 220, 'demo_bonus', 'Welcome bonus (demo)', 'platform-demo:welcome:'.$user->id);
        $rewards->award($user, 60, 'queue_completed', 'Completed a queue visit (demo)', 'platform-demo:visit:'.$user->id);
    }

    private function organizations(): void
    {
        $make = fn (string $name, string $type, array $extra = []) => Organization::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'type' => $type, 'settings' => Organization::DEFAULT_SETTINGS, 'is_demo' => true] + $extra
        );

        foreach (Location::whereNull('organization_id')->get() as $loc) {
            $name = strtolower($loc->name);
            $type = strtolower((string) $loc->type);
            [$org, $orgType] = match (true) {
                str_contains($name, 'transport') => ['Department of Transport Management (demo)', 'government'],
                str_contains($name, 'passport') => ['Department of Passports (demo)', 'government'],
                str_contains($type, 'bank') => ['Sagarmatha Demo Bank (fictional)', 'bank'],
                str_contains($type, 'hospital') || str_contains($name, 'hospital') => ['Kathmandu City Hospital Network (demo)', 'hospital'],
                str_contains($type, 'ward') || str_contains($name, 'ward') => ['Kathmandu Metropolitan City (demo)', 'ward'],
                str_contains($type, 'government') => ['Government Service Offices (demo)', 'government'],
                default => ['Independent Service Providers (demo)', 'private'],
            };
            $loc->forceFill(['organization_id' => $make($org, $orgType)->id])->save();
        }

        // Demo contact details (fictional, .example domains).
        $contacts = [
            'department-of-transport-management-demo' => ['contact_email' => 'help@dotm.example', 'contact_phone' => '01-5550101'],
            'department-of-passports-demo' => ['contact_email' => 'info@passports.example', 'contact_phone' => '01-5550102'],
            'sagarmatha-demo-bank-fictional' => ['contact_email' => 'care@sagarmatha-bank.example', 'contact_phone' => '01-5550103'],
            'kathmandu-city-hospital-network-demo' => ['contact_email' => 'opd@kch.example', 'contact_phone' => '01-5550104'],
            'kathmandu-metropolitan-city-demo' => ['contact_email' => 'ward10@kmc.example', 'contact_phone' => '01-5550105'],
        ];
        foreach ($contacts as $slug => $c) {
            Organization::where('slug', $slug)->whereNull('contact_email')->update($c);
        }
    }

    private function catalogue(): void
    {
        $items = [
            ['free-chiya', 'Free chiya at partner café', 'Chiya Ghar Café (demo partner)', 'One cup of milk tea at any participating demo café.', 'bi-cup-hot', 150, null, 14],
            ['momo-discount', '20% off a momo plate', 'Ekantakuna Momo House (demo partner)', 'Discount on one plate. Demo partner — no real merchant is connected.', 'bi-basket', 300, null, 30],
            ['photocopy-pack', '10 free photocopies', 'Lalitpur Print Point (demo partner)', 'Handy for missing document copies near government offices.', 'bi-printer', 120, null, 30],
            ['ebook-week', 'One-week premium e-book pass', 'Pratikshya Library (demo)', 'Unlocks premium demo titles in the library for 7 days.', 'bi-book', 400, null, 7],
            ['ride-voucher', 'NPR 50 ride voucher', 'Demo transport partner', 'Illustrative voucher — no ride provider integration is connected.', 'bi-car-front', 600, 25, 30],
        ];
        foreach ($items as [$slug, $title, $partner, $desc, $icon, $cost, $stock, $days]) {
            RewardCatalogueItem::updateOrCreate(['slug' => $slug], [
                'title' => $title, 'partner' => $partner, 'description' => $desc, 'icon' => $icon,
                'points_cost' => $cost, 'valid_days' => $days, 'is_active' => true, 'is_demo' => true,
            ] + (RewardCatalogueItem::where('slug', $slug)->exists() ? [] : ['stock' => $stock]));
        }
    }
}
