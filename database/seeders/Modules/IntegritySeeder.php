<?php

namespace Database\Seeders\Modules;

use App\Models\FraudAlert;
use App\Models\IntegritySignal;
use App\Models\Service;
use App\Models\User;
use App\Services\Integrity\DalalEngine;
use App\Services\Integrity\ExplanationService;
use App\Services\Queue\QueueManager;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEMO ONLY: fictional accounts and integrity signals for Dalal AI + Queue Exchange.
 * Safe to re-run: users are firstOrCreate, queue joins are idempotent, and the sample
 * alerts use fixed "demo:" fingerprints (a reviewed sample keeps its review).
 *
 *   integrity.demo@waitwise.np   / Demo@1234  (exchange requester)
 *   integrity.demo2@waitwise.np  / Demo@1234  (exchange recipient)
 *   integrity.sample@waitwise.np / Demo@1234  (subject of the sample alert; fictional)
 */
class IntegritySeeder extends Seeder
{
    public function run(): void
    {
        $queues = app(QueueManager::class);
        $service = Service::where('prefix', 'B')->where('is_active', true)->first()
            ?? Service::where('is_active', true)->where('allow_exchange', true)->first();
        if (! $service) {
            $this->command?->warn('IntegritySeeder: no active service found; run DatabaseSeeder first.');

            return;
        }

        // Two fictional people waiting in the same queue, for the exchange demo.
        $a = User::firstOrCreate(['email' => 'integrity.demo@waitwise.np'], ['name' => 'Aarati Demo (Exchange)', 'password' => 'Demo@1234']);
        $b = User::firstOrCreate(['email' => 'integrity.demo2@waitwise.np'], ['name' => 'Bikash Demo (Exchange)', 'password' => 'Demo@1234']);
        $ta = $queues->activeTicketFor($service, $a) ?? $queues->join($service, $a);
        $queues->activeTicketFor($service, $b) ?? $queues->join($service->fresh(), $b);

        // Sample alert 1: repeated duplicate-token attempts by a fictional visitor (medium).
        $sample = User::firstOrCreate(['email' => 'integrity.sample@waitwise.np'], ['name' => 'Demo Visitor (Dalal AI sample)', 'password' => 'Demo@1234']);
        $licence = Service::where('prefix', 'D')->where('is_active', true)->first() ?? $service;
        $ticket = $queues->activeTicketFor($licence, $sample) ?? $queues->join($licence, $sample);

        if (! FraudAlert::where('fingerprint', 'demo:duplicate_token_attempts:sample')->exists()) {
            $ids = [];
            foreach ([9, 7, 6, 4] as $minsAgo) {
                $s = IntegritySignal::create([
                    'type' => 'duplicate_join', 'user_id' => $sample->id, 'actor_id' => $sample->id, 'ticket_id' => $ticket->id,
                    'service_id' => $licence->id, 'location_id' => $licence->location_id, 'actor_role' => 'user',
                    'network_hash' => substr(hash('sha256', 'demo-network-1'), 0, 32), 'ua_family' => 'Chrome · mobile', 'meta' => ['demo' => true],
                ]);
                $s->forceFill(['created_at' => now()->subMinutes($minsAgo)])->save();
                $ids[] = $s->id;
            }
            $this->alert('demo:duplicate_token_attempts:sample', 'duplicate_tokens', 'duplicate_token_attempts', 50, 'medium',
                ['count' => 4, 'window_minutes' => 10, 'threshold' => 3, 'idempotent_retries' => 1], $ids,
                $sample->id, $licence->id, $ticket->id, now()->subMinutes(9), now()->subMinutes(4));
        }

        // Sample alert 2: new accounts on one shared network (always LOW, never conclusive).
        if (! FraudAlert::where('fingerprint', 'demo:new_account_burst:sample')->exists()) {
            $hash = substr(hash('sha256', 'demo-network-2'), 0, 32);
            $ids = [];
            foreach ([28, 17, 6] as $minsAgo) {
                $s = IntegritySignal::create([
                    'type' => 'joined', 'service_id' => $service->id, 'location_id' => $service->location_id, 'actor_role' => 'user',
                    'network_hash' => $hash, 'ua_family' => 'Chrome · mobile', 'meta' => ['demo' => true],
                ]);
                $s->forceFill(['created_at' => now()->subMinutes($minsAgo)])->save();
                $ids[] = $s->id;
            }
            $this->alert('demo:new_account_burst:sample', 'account_burst', 'new_account_burst_shared_network', 15, 'low',
                ['count' => 3, 'window_minutes' => 30, 'threshold' => 3, 'account_age_hours' => 24], $ids,
                null, $service->id, null, now()->subMinutes(28), now()->subMinutes(6),
                ['network_ref' => substr($hash, 0, 8).'…', 'browser_families' => ['Chrome · mobile']]);
        }

        $this->command?->info("Integrity demo ready: {$a->email} holds {$ta->fresh()->token()} in {$service->name}; sample alerts are labelled Demo.");
    }

    private function alert(string $fp, string $type, string $rule, int $score, string $severity, array $facts, array $signalIds,
        ?int $userId, ?int $serviceId, ?int $ticketId, $first, $last, array $extra = []): void
    {
        $alert = new FraudAlert([
            'type' => $type, 'rule' => $rule, 'risk_score' => $score, 'severity' => $severity, 'status' => 'open',
            'subject_user_id' => $userId, 'subject_service_id' => $serviceId, 'subject_ticket_id' => $ticketId,
            'evidence' => ['facts' => $facts, 'signal_ids' => $signalIds, 'demo' => true] + $extra,
            'recommended_actions' => app(DalalEngine::class)->actions($rule),
            'fingerprint' => $fp, 'signal_count' => count($signalIds), 'first_seen_at' => $first, 'last_seen_at' => $last,
        ]);
        $alert->forceFill([
            'explanation' => app(ExplanationService::class)->template($alert),
            'explanation_provider' => ExplanationService::TEMPLATE_PROVIDER,
        ])->save();
    }
}
