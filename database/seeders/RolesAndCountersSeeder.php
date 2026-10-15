<?php

namespace Database\Seeders;

use App\Models\Counter;
use App\Models\Location;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEMO ONLY — fictional staff/admin accounts and one counter per service.
 * Safe to re-run: uses firstOrCreate and never touches existing queue numbers.
 * Never run this against a production database (it creates known passwords).
 */
class RolesAndCountersSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Service::all() as $s) {
            Counter::firstOrCreate(
                ['location_id' => $s->location_id, 'number' => $s->counter],
                ['service_id' => $s->id, 'label' => 'Counter '.$s->counter, 'is_open' => true]
            );
        }

        $admin = User::firstOrCreate(['email' => 'admin@waitwise.np'], ['name' => 'Demo Admin', 'password' => 'Admin@1234']);
        $admin->forceFill(['role' => 'admin'])->save();

        $staff = User::firstOrCreate(['email' => 'staff@waitwise.np'], ['name' => 'Sita Staff (Transport Office)', 'password' => 'Staff@1234']);
        $staff->forceFill(['role' => 'staff'])->save();

        $hospitalStaff = User::firstOrCreate(['email' => 'hospital.staff@waitwise.np'], ['name' => 'Hari Staff (Hospital)', 'password' => 'Staff@1234']);
        $hospitalStaff->forceFill(['role' => 'staff'])->save();

        if ($transport = Location::where('name', 'Transport Management Office')->first()) {
            StaffAssignment::firstOrCreate(['user_id' => $staff->id, 'location_id' => $transport->id], ['is_approved' => true]);
        }
        if ($hospital = Location::where('name', 'like', '%Hospital%')->first()) {
            StaffAssignment::firstOrCreate(['user_id' => $hospitalStaff->id, 'location_id' => $hospital->id], ['is_approved' => true]);
        }
    }
}
