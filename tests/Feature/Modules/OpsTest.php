<?php

namespace Tests\Feature\Modules;

use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\Location;
use App\Models\OperationalIncident;
use App\Models\Service;
use App\Models\ServiceAnnouncement;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Ops\OfficeAnnouncement;
use App\Services\Ops\AnnouncementService;
use App\Services\Ops\IncidentService;
use App\Services\Queue\QueueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private function service(?Location $loc = null, array $overrides = []): Service
    {
        $loc ??= Location::create(['name' => 'Test Office '.uniqid(), 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3]);
        $service = $loc->services()->create(array_merge([
            'name' => 'Licence Renewal '.uniqid(), 'prefix' => 'D', 'counter' => 2, 'avg_minutes' => 4,
            'current_number' => 15, 'last_number' => 18, 'documents' => ['Old licence', 'Citizenship copy'],
        ], $overrides));
        Counter::firstOrCreate(['location_id' => $loc->id, 'number' => $service->counter], ['service_id' => $service->id, 'label' => 'Counter '.$service->counter]);

        return $service;
    }

    private function staffFor(Location $loc, bool $locationWide = false): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'staff'])->save();
        StaffAssignment::create(['user_id' => $u->id, 'location_id' => $loc->id])
            ->forceFill(['can_publish_location_notices' => $locationWide])->save();

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    private function incident(User $by, Service $s, array $attrs = []): OperationalIncident
    {
        $this->actingAs($by);

        return app(IncidentService::class)->report($by, array_merge([
            'location_id' => $s->location_id, 'service_id' => $s->id,
            'counter_id' => Counter::where('location_id', $s->location_id)->value('id'),
            'category' => 'printer', 'description' => 'Printer jammed at the counter', 'priority' => 'high',
        ], $attrs));
    }

    private function notice(Service $s, array $attrs = [], string $status = 'published', ?int $incidentId = null): ServiceAnnouncement
    {
        $a = new ServiceAnnouncement(array_merge([
            'location_id' => $s->location_id, 'service_id' => $s->id, 'title' => 'Notice', 'public_message' => 'Public text',
            'severity' => 'info', 'starts_at' => now()->subMinute(),
        ], $attrs));
        $a->forceFill(['status' => $status, 'operational_incident_id' => $incidentId])->save();

        return $a;
    }

    private function feed(User $u, Service $s): array
    {
        return $this->actingAs($u)->getJson(route('ops.api.announcements', ['service' => $s->id]))
            ->assertOk()->assertJsonStructure(['message', 'data'])->json('data');
    }

    // ---------------- Incident access control ----------------

    public function test_staff_of_other_location_and_plain_users_cannot_access_incidents(): void
    {
        $s = $this->service();
        $mine = $this->staffFor($s->location);
        $other = $this->staffFor($this->service()->location);
        $user = User::factory()->create();
        $inc = $this->incident($mine, $s);

        $this->actingAs($mine)->get(route('ops.incidents.show', $inc))->assertOk()->assertSee('Printer jammed at the counter');
        $this->actingAs($other)->get(route('ops.incidents.show', $inc))->assertForbidden();
        $this->actingAs($other)->post(route('ops.incidents.update', $inc), ['status' => 'closed'])->assertForbidden();
        $this->actingAs($other)->post(route('ops.incidents.publish', $inc), ['title' => 'x', 'public_message' => 'y', 'severity' => 'info', 'scope' => 'affected'])->assertForbidden();
        $this->actingAs($other)->get(route('ops.incidents.index', ['status' => 'all']))->assertOk()->assertDontSee('Printer jammed at the counter');

        $this->actingAs($user)->get(route('ops.incidents.index'))->assertForbidden();
        $this->actingAs($user)->get(route('ops.incidents.show', $inc))->assertForbidden();
        $this->actingAs($user)->post(route('ops.incidents.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('ops.overview'))->assertForbidden();
        $this->actingAs($mine)->get(route('ops.overview'))->assertForbidden(); // admin only

        $this->actingAs($this->admin())->get(route('ops.incidents.show', $inc))->assertOk();
        $this->assertSame('open', $inc->fresh()->status);
    }

    public function test_staff_can_report_only_for_assigned_locations_and_changes_are_audited(): void
    {
        $s = $this->service();
        $foreign = $this->service();
        $staff = $this->staffFor($s->location);

        $this->actingAs($staff)->post(route('ops.incidents.store'), [
            'location_id' => $foreign->location_id, 'category' => 'network', 'description' => 'Not my office', 'priority' => 'low',
        ])->assertSessionHasErrors('location_id');
        // A service from another location cannot be smuggled in either.
        $this->actingAs($staff)->post(route('ops.incidents.store'), [
            'location_id' => $s->location_id, 'service_id' => $foreign->id, 'category' => 'network', 'description' => 'Cross-location id', 'priority' => 'low',
        ])->assertSessionHasErrors('service_id');
        $this->assertSame(0, OperationalIncident::count());

        $this->actingAs($staff)->post(route('ops.incidents.store'), [
            'location_id' => $s->location_id, 'service_id' => $s->id, 'category' => 'network', 'description' => 'Wifi down at counter', 'priority' => 'medium',
        ])->assertRedirect();
        $inc = OperationalIncident::firstOrFail();
        $this->assertSame($staff->id, $inc->reported_by);
        $this->assertSame('open', $inc->status);

        $this->actingAs($staff)->post(route('ops.incidents.update', $inc), ['status' => 'resolved', 'resolution_notes' => 'Router rebooted', 'comment' => 'Fixed'])->assertRedirect();
        $inc->refresh();
        $this->assertSame('resolved', $inc->status);
        $this->assertNotNull($inc->resolved_at);
        $this->assertSame(['created', 'status', 'comment'], $inc->updates()->reorder('id')->pluck('type')->all());

        // Illegal transition is rejected.
        $this->actingAs($staff)->post(route('ops.incidents.update', $inc), ['status' => 'acknowledged'])->assertSessionHasErrors('status');
        $this->assertSame('resolved', $inc->fresh()->status);

        $this->assertTrue(AuditLog::where('action', 'ops.incident.reported')->where('subject_id', $inc->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'ops.incident.updated')->where('subject_id', $inc->id)->exists());
    }

    // ---------------- Confidentiality ----------------

    public function test_internal_and_security_incidents_never_reach_public_endpoint_or_banner(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location, true);
        $customer = User::factory()->create();

        $sec = $this->incident($staff, $s, ['category' => 'security', 'description' => 'SECRET-BAG near entrance']);
        $this->assertTrue($sec->is_internal, 'security incidents are forced internal');
        $internal = $this->incident($staff, $s, ['category' => 'repair', 'is_internal' => true, 'description' => 'SECRET-REPAIR vendor contract']);

        foreach ([$sec, $internal] as $inc) {
            $this->actingAs($staff)->post(route('ops.incidents.publish', $inc), [
                'title' => 'Leak', 'public_message' => 'Should never publish', 'severity' => 'critical', 'scope' => 'affected',
            ])->assertForbidden();
        }
        $this->assertSame(0, ServiceAnnouncement::count());

        // Defence in depth: even a notice row linked to an internal incident is filtered out.
        $this->notice($s, ['title' => 'LEAKED-TITLE', 'public_message' => 'LEAKED-MESSAGE'], 'published', $sec->id);
        $this->notice($s, ['title' => 'Safe title', 'public_message' => 'Safe message']);

        $data = $this->feed($customer, $s);
        $this->assertCount(1, $data);
        $this->assertSame('Safe message', $data[0]['message']);
        $json = json_encode($data);
        $this->assertStringNotContainsString('SECRET', $json);
        $this->assertStringNotContainsString('LEAKED', $json);

        $banner = view('ops.partials.announcement-banner', ['service' => $s])->render();
        $this->assertStringContainsString('Safe message', $banner);
        $this->assertStringNotContainsString('LEAKED', $banner);
        $this->assertStringNotContainsString('SECRET', $banner);

        $this->get(route('ops.qr.landing', $s->fresh()->qr_code ?? app(\App\Services\Ops\QrCodeService::class)->codeFor($s)))
            ->assertOk()->assertDontSee('SECRET')->assertDontSee('LEAKED')->assertSee('Safe message');
    }

    public function test_publishing_from_incident_never_copies_internal_notes(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $customer = User::factory()->create();
        app(QueueManager::class)->join($s, $customer, ['Old licence' => 'available', 'Citizenship copy' => 'available']);

        $inc = $this->incident($staff, $s, ['description' => 'INTERNAL-DESC paper roller', 'assigned_technician' => 'INTERNAL-TECH']);
        $this->actingAs($staff)->post(route('ops.incidents.update', $inc), ['comment' => 'INTERNAL-COMMENT', 'resolution_notes' => 'INTERNAL-RES']);

        $this->actingAs($staff)->post(route('ops.incidents.publish', $inc), [
            'title' => 'Printer unavailable', 'public_message' => 'Printer at Counter 2 unavailable — please bring photocopies.',
            'severity' => 'warning', 'scope' => 'affected',
        ])->assertRedirect(route('ops.incidents.show', $inc));

        $a = ServiceAnnouncement::firstOrFail();
        $this->assertSame('published', $a->status);
        $this->assertSame($inc->id, $a->operational_incident_id);
        $this->assertSame($s->id, $a->service_id);
        $all = json_encode($a->toArray()).json_encode($this->feed($customer, $s)).json_encode($customer->notifications()->get()->toArray());
        $this->assertStringNotContainsString('INTERNAL', $all);
        $this->assertStringContainsString('please bring photocopies', $all);
    }

    public function test_notice_text_is_sanitised_and_personal_data_rejected(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $base = ['location_id' => $s->location_id, 'service_id' => $s->id, 'title' => 'Counter update', 'severity' => 'info', 'publish' => 1];

        foreach (['Call Ram on 9841234567', 'Email ram@example.com for help', 'Citizenship 12-01-75-12345 found'] as $bad) {
            $this->actingAs($staff)->post(route('ops.notices.store'), $base + ['public_message' => $bad])->assertSessionHasErrors('public_message');
        }
        $this->assertSame(0, ServiceAnnouncement::count());

        $this->actingAs($staff)->post(route('ops.notices.store'), $base + ['public_message' => '<script>alert(1)</script><b>Counter 3</b> is   closed.'])->assertRedirect();
        $this->assertSame('alert(1)Counter 3 is closed.', ServiceAnnouncement::firstOrFail()->public_message);
    }

    // ---------------- Permissions for scope ----------------

    public function test_staff_cannot_publish_location_wide_without_permission(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $supervisor = $this->staffFor($s->location, true);
        $wide = ['location_id' => $s->location_id, 'title' => 'Office closing early', 'public_message' => 'The office closes at 3 pm today.', 'severity' => 'warning', 'publish' => 1];

        $this->actingAs($staff)->post(route('ops.notices.store'), $wide)->assertForbidden();
        $this->assertSame(0, ServiceAnnouncement::count());

        // Service-scoped is fine for ordinary staff.
        $this->actingAs($staff)->post(route('ops.notices.store'), $wide + ['service_id' => $s->id])->assertRedirect();
        $this->assertSame(1, ServiceAnnouncement::count());

        // Flagged staff and admins may go location-wide.
        $this->actingAs($supervisor)->post(route('ops.notices.store'), $wide)->assertRedirect();
        $this->actingAs($this->admin())->post(route('ops.notices.store'), $wide)->assertRedirect();
        $this->assertSame(2, ServiceAnnouncement::whereNull('service_id')->count());

        // Ordinary staff cannot take down a location-wide notice either.
        $locWide = ServiceAnnouncement::whereNull('service_id')->first();
        $this->actingAs($staff)->post(route('ops.notices.expire', $locWide))->assertForbidden();
        // Staff of another location cannot publish here at all.
        $outsider = $this->staffFor($this->service()->location);
        $this->actingAs($outsider)->post(route('ops.notices.store'), $wide + ['service_id' => $s->id])->assertSessionHasErrors('location_id');
    }

    // ---------------- Public feed ----------------

    public function test_announcement_endpoint_is_scoped_and_time_windowed(): void
    {
        $s = $this->service();
        $sibling = $this->service($s->location, ['prefix' => 'B', 'counter' => 4]);
        $elsewhere = $this->service();
        $user = User::factory()->create();

        $this->notice($s, ['title' => 'MINE']);
        $this->notice($s, ['title' => 'LOCATION-WIDE', 'service_id' => null]);
        $this->notice($sibling, ['title' => 'SIBLING']);
        $this->notice($elsewhere, ['title' => 'ELSEWHERE']);
        $this->notice($s, ['title' => 'FUTURE', 'starts_at' => now()->addHour()]);
        $this->notice($s, ['title' => 'EXPIRED', 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
        $this->notice($s, ['title' => 'DRAFT'], 'draft');
        $this->notice($s, ['title' => 'TAKEN-DOWN'], 'expired');

        $titles = collect($this->feed($user, $s))->pluck('title')->sort()->values()->all();
        $this->assertSame(['LOCATION-WIDE', 'MINE'], $titles);

        $this->travel(2)->hours();
        $titles = collect($this->feed($user, $s))->pluck('title')->sort()->values()->all();
        $this->assertSame(['FUTURE', 'LOCATION-WIDE', 'MINE'], $titles);

        $this->actingAs($user)->getJson(route('ops.api.announcements'))->assertUnprocessable();
        $this->actingAs($user)->getJson(route('ops.api.announcements', ['service' => 999999]))->assertNotFound();
        auth()->logout();
        $this->getJson(route('ops.api.announcements', ['service' => $s->id]))->assertUnauthorized();
    }

    public function test_notifications_are_deduplicated_per_announcement_and_user(): void
    {
        $s = $this->service();
        $sibling = $this->service($s->location, ['prefix' => 'B', 'counter' => 4]);
        $admin = $this->admin();
        $holder = User::factory()->create();
        $bystander = User::factory()->create();
        $qm = app(QueueManager::class);
        $docs = ['Old licence' => 'available', 'Citizenship copy' => 'available'];
        $qm->join($s, $holder, $docs);
        $qm->join($sibling->fresh(), $holder, $docs);   // same user, two queues at the location

        $this->actingAs($admin);
        $svc = app(AnnouncementService::class);
        $wide = $svc->create($admin, ['location_id' => $s->location_id, 'title' => 'Network slow', 'public_message' => 'Service may be slower than usual.', 'severity' => 'info', 'publish' => true]);

        $this->assertSame(1, $holder->notifications()->count());
        $this->assertSame(0, $bystander->notifications()->count());
        $this->assertSame(0, $svc->notifyAffected($wide), 'repeat delivery is a no-op');
        $svc->publish($wide, $admin);
        $this->assertSame(1, $holder->fresh()->notifications()->count());
        $n = $holder->notifications()->first();
        $this->assertSame(OfficeAnnouncement::class, $n->type);
        $this->assertSame('office_announcement', $n->data['kind']);
        $this->assertSame($wide->id, $n->data['announcement_id']);

        // A second, different announcement is a new notification.
        $svc->create($admin, ['location_id' => $s->location_id, 'service_id' => $s->id, 'title' => 'Counter 2 closed', 'public_message' => 'Please proceed to Counter 4.', 'severity' => 'warning', 'publish' => true]);
        $this->assertSame(2, $holder->notifications()->count());
    }

    // ---------------- Photos ----------------

    public function test_photo_upload_is_validated_and_served_privately_to_authorised_staff_only(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $other = $this->staffFor($this->service()->location);
        $base = ['location_id' => $s->location_id, 'category' => 'printer', 'description' => 'Printer broken', 'priority' => 'high'];

        // Text pretending to be an image, wrong type, and oversize are all rejected.
        $this->actingAs($staff)->post(route('ops.incidents.store'), $base + ['photo' => UploadedFile::fake()->createWithContent('evil.png', '<?php echo 1; ?>')])->assertSessionHasErrors('photo');
        $this->actingAs($staff)->post(route('ops.incidents.store'), $base + ['photo' => UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 fake')])->assertSessionHasErrors('photo');
        $big = UploadedFile::fake()->createWithContent('big.png', base64_decode(self::PNG).str_repeat("\0", 2100 * 1024));
        $this->actingAs($staff)->post(route('ops.incidents.store'), $base + ['photo' => $big])->assertSessionHasErrors('photo');
        $this->assertSame(0, OperationalIncident::count());

        $this->actingAs($staff)->post(route('ops.incidents.store'), $base + ['photo' => UploadedFile::fake()->createWithContent('jam.png', base64_decode(self::PNG))])->assertRedirect();
        $inc = OperationalIncident::firstOrFail();
        $this->assertNotNull($inc->photo_path);
        $this->assertStringEndsWith('.png', $inc->photo_path);
        $this->assertStringNotContainsString('jam', $inc->photo_path, 'client filename is never used');
        Storage::disk('local')->assertExists($inc->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $res = $this->actingAs($staff)->get(route('ops.incidents.photo', $inc))->assertOk();
        $this->assertSame('image/png', $res->headers->get('Content-Type'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->actingAs($other)->get(route('ops.incidents.photo', $inc))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('ops.incidents.photo', $inc))->assertForbidden();
        auth()->logout();
        $this->get(route('ops.incidents.photo', $inc))->assertRedirect(route('login'));
    }

    // ---------------- QR ----------------

    public function test_qr_landing_shows_public_service_data_and_no_secrets(): void
    {
        $s = $this->service(null, ['name' => 'Smart Licence Renewal']);
        $staff = $this->staffFor($s->location);
        $code = app(\App\Services\Ops\QrCodeService::class)->codeFor($s);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $code);
        $this->incident($staff, $s, ['description' => 'STAFF-ONLY-NOTE']);
        auth()->logout();

        $html = $this->get(route('ops.qr.landing', $code))->assertOk()
            ->assertSee('Smart Licence Renewal')
            ->assertSee($s->location->name)
            ->assertSee('Old licence')->assertSee('Citizenship copy')
            ->assertSee('Counter 2')
            ->assertSee(route('service', $s), false)
            ->assertDontSee('STAFF-ONLY-NOTE')
            ->assertDontSee($staff->email)
            ->assertSee('<div class="v">'.$s->waitingCount().' <small>people</small>', false)
            ->getContent();
        $this->assertStringNotContainsString((string) config('app.key'), $html);

        $this->get('/q/doesnotexist')->assertNotFound();
        $this->get(route('ops.qr.location', app(\App\Services\Ops\QrCodeService::class)->codeFor($s->location)))->assertOk()->assertSee('Smart Licence Renewal');
    }

    public function test_admin_can_rotate_qr_codes_and_old_code_stops_working(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $qr = app(\App\Services\Ops\QrCodeService::class);
        $old = $qr->codeFor($s);

        $this->actingAs($staff)->post(route('ops.qr.rotate', $s))->assertForbidden();
        $this->assertSame($old, $s->fresh()->qr_code);

        $this->actingAs($this->admin())->post(route('ops.qr.rotate', $s))->assertRedirect();
        $new = $s->fresh()->qr_code;
        $this->assertNotSame($old, $new);
        $this->get(route('ops.qr.landing', $old))->assertNotFound();
        $this->get(route('ops.qr.landing', $new))->assertOk();
        $this->assertTrue(AuditLog::where('action', 'ops.qr.rotated')->where('subject_id', $s->id)->exists());

        // Posters: own location only, and they encode the public URL.
        $this->actingAs($staff)->get(route('ops.qr.poster', $s))->assertOk()->assertSee(route('ops.qr.landing', $new), false);
        $this->actingAs($this->staffFor($this->service()->location))->get(route('ops.qr.poster', $s))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('ops.qr.index'))->assertForbidden();
    }

    public function test_joining_from_a_qr_code_still_requires_login_and_uses_the_normal_join_flow(): void
    {
        $s = $this->service();
        $code = app(\App\Services\Ops\QrCodeService::class)->codeFor($s);

        $this->get(route('ops.qr.landing', $code))->assertOk()->assertSee('Log in to join');
        $this->get(route('service', $s))->assertRedirect(route('login'));
        $this->post(route('join', $s), ['docs' => ['Old licence' => 'available', 'Citizenship copy' => 'available']])->assertRedirect(route('login'));
        $this->assertSame(0, Ticket::count());

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('ops.qr.landing', $code))->assertOk()->assertSee('Continue to join');
        $this->actingAs($user)->post(route('join', $s), ['docs' => ['Old licence' => 'available', 'Citizenship copy' => 'available']])->assertRedirect();
        $this->assertSame(1, Ticket::where('user_id', $user->id)->count());
        $this->assertSame(19, $s->fresh()->last_number);
    }

    // ---------------- Pages render ----------------

    public function test_console_pages_render_for_staff_and_admin(): void
    {
        $s = $this->service();
        $staff = $this->staffFor($s->location);
        $this->incident($staff, $s);
        $this->notice($s, ['title' => 'Live one']);

        foreach (['ops.incidents.index', 'ops.incidents.create', 'ops.notices.index', 'ops.qr.index'] as $r) {
            $this->actingAs($staff)->get(route($r))->assertOk();
        }
        $this->actingAs($this->admin())->get(route('ops.overview'))->assertOk()->assertSee('Operations overview');
        $card = view('ops.partials.staff-card', ['service' => $s])->render();
        $this->assertStringContainsString('Report incident', $card);
        $this->actingAs(User::factory()->create());
        $this->assertSame('', trim(view('ops.partials.staff-card', ['service' => $s])->render()));
    }
}
