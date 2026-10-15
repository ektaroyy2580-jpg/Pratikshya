<?php

namespace Database\Seeders\Modules;

use App\Models\Counter;
use App\Models\IncidentUpdate;
use App\Models\Location;
use App\Models\OperationalIncident;
use App\Models\Service;
use App\Models\ServiceAnnouncement;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\Ops\QrCodeService;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEMO ONLY — fictional office incidents and notices (labelled "demo").
 * Idempotent: keyed on natural fields, safe to re-run. Sends NO notifications
 * (so real accounts holding tickets are never messaged by seeding).
 */
class OpsSeeder extends Seeder
{
    public function run(): void
    {
        $qr = app(QrCodeService::class);
        Service::whereNull('qr_code')->get()->each(fn ($s) => $qr->codeFor($s));
        Location::whereNull('qr_code')->get()->each(fn ($l) => $qr->codeFor($l));

        $transport = Location::where('name', 'Transport Management Office')->first();
        if (! $transport) {
            $this->command?->warn('OpsSeeder: Transport Management Office not found — run DatabaseSeeder first.');

            return;
        }

        $staff = User::where('email', 'staff@waitwise.np')->first();
        $admin = User::where('email', 'admin@waitwise.np')->first();

        // Extra demo supervisor who may publish location-wide notices at the Transport Office.
        $sup = User::firstOrCreate(['email' => 'ops.demo@waitwise.np'], ['name' => 'Bikash Supervisor (Ops demo)', 'password' => 'Demo@1234']);
        $sup->forceFill(['role' => 'staff'])->save();
        StaffAssignment::firstOrCreate(['user_id' => $sup->id, 'location_id' => $transport->id], ['is_approved' => true])
            ->forceFill(['can_publish_location_notices' => true])->save();

        $reporter = $staff ?? $sup;
        $licence = Service::where('location_id', $transport->id)->where('prefix', 'D')->first();
        $exam = Service::where('location_id', $transport->id)->where('prefix', 'E')->first();
        $c2 = Counter::where('location_id', $transport->id)->where('number', 2)->first();
        $c6 = Counter::where('location_id', $transport->id)->where('number', 6)->first();

        // 1) Printer outage at Counter 2 + public notice.
        $printer = $this->incident($reporter, [
            'location_id' => $transport->id, 'service_id' => $licence?->id, 'counter_id' => $c2?->id,
            'category' => 'printer', 'equipment' => 'Receipt & photocopy printer (demo)',
            'description' => 'DEMO: Printer at Counter 2 jammed — paper feed roller broken. IT support called; spare part ordered from Teku store.',
            'priority' => 'high', 'assigned_technician' => 'Ramesh K. — IT support (demo)', 'is_internal' => false,
        ], 'in_progress', now()->subMinutes(50));

        $notice = ServiceAnnouncement::firstOrNew(['operational_incident_id' => $printer->id, 'title' => 'Printer at Counter 2 unavailable']);
        if (! $notice->exists) {
            $notice->fill([
                'location_id' => $transport->id, 'service_id' => $licence?->id, 'counter_id' => $c2?->id,
                'public_message' => 'Printer at Counter 2 unavailable — please bring photocopies of your documents.',
                'severity' => 'warning', 'starts_at' => now()->subMinutes(45), 'ends_at' => null,
            ]);
            $notice->forceFill(['operational_incident_id' => $printer->id, 'status' => 'published', 'published_by' => $reporter->id])->save();
            IncidentUpdate::create(['operational_incident_id' => $printer->id, 'author_id' => $reporter->id, 'type' => 'published', 'body' => 'Public notice #'.$notice->id.' published']);
        }

        // 2) Internal security incident — must NEVER appear publicly.
        $this->incident($reporter, [
            'location_id' => $transport->id, 'category' => 'security', 'equipment' => 'Waiting hall entrance (demo)',
            'description' => 'DEMO CONFIDENTIAL: Unattended bag reported near the waiting hall entrance. Guard on duty informed; CCTV reviewed. Do not announce publicly.',
            'priority' => 'critical', 'assigned_technician' => 'Security desk (demo)', 'is_internal' => true,
        ], 'acknowledged', now()->subMinutes(20));

        // 3) Counter closed earlier today — resolved.
        $this->incident($sup, [
            'location_id' => $transport->id, 'service_id' => $exam?->id, 'counter_id' => $c6?->id,
            'category' => 'counter_closed', 'equipment' => null,
            'description' => 'DEMO: Counter 6 officer on short leave; exam queue redirected to the help desk for 40 minutes.',
            'priority' => 'medium', 'resolution_notes' => 'DEMO: Relief officer assigned at 11:20. Counter reopened.', 'is_internal' => false,
        ], 'resolved', now()->subHours(3));

        // 4) Other locations (seen by admin and that location's staff only).
        if ($passport = Location::where('name', 'Department of Passports')->first()) {
            $this->incident($admin ?? $reporter, [
                'location_id' => $passport->id, 'category' => 'projector', 'equipment' => 'Token display screen, hall B (demo)',
                'description' => 'DEMO: Token display flickering; HDMI cable replaced.', 'priority' => 'low', 'is_internal' => false,
                'resolution_notes' => 'DEMO: Cable replaced, display stable.',
            ], 'closed', now()->subDay());
        }
        if ($hospital = Location::where('name', 'like', '%Hospital%')->first()) {
            $hStaff = User::where('email', 'hospital.staff@waitwise.np')->first() ?? $admin ?? $reporter;
            $this->incident($hStaff, [
                'location_id' => $hospital->id, 'category' => 'network', 'equipment' => 'OPD registration PCs (demo)',
                'description' => 'DEMO: Intermittent network drops at OPD registration; ISP ticket raised.', 'priority' => 'medium', 'is_internal' => false,
            ], 'open', now()->subMinutes(35));
        }
    }

    private function incident(User $by, array $attrs, string $status, $at): OperationalIncident
    {
        $incident = OperationalIncident::firstOrNew([
            'location_id' => $attrs['location_id'], 'category' => $attrs['category'], 'description' => $attrs['description'],
        ]);
        if ($incident->exists) {
            return $incident;
        }
        $incident->fill($attrs);
        $incident->forceFill([
            'reported_by' => $by->id, 'reported_at' => $at, 'status' => $status,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? $at->copy()->addMinutes(40) : null,
        ])->save();

        IncidentUpdate::create(['operational_incident_id' => $incident->id, 'author_id' => $by->id, 'type' => 'created', 'to_status' => 'open']);
        if ($status !== 'open') {
            IncidentUpdate::create(['operational_incident_id' => $incident->id, 'author_id' => $by->id, 'type' => 'status', 'from_status' => 'open', 'to_status' => $status]);
        }

        return $incident;
    }
}
