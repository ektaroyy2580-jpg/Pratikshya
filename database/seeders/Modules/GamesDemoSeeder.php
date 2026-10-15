<?php

namespace Database\Seeders\Modules;

use App\Models\Service;
use App\Models\User;
use App\Services\Games\CompetitionService;
use App\Services\Queue\QueueManager;
use Illuminate\Database\Seeder;

/**
 * DEMO ONLY: two fictional players waiting in the same queue + one open battle,
 * so the multiplayer room can be tried immediately.
 *
 *   games.demo@waitwise.np  / Demo@1234  (hosts the open battle)
 *   games.demo2@waitwise.np / Demo@1234  (can join it)
 *
 * Note: this issues two real queue tokens in "Driving Licence Renewal" (or the first active
 * service), so run it on demo databases only. Idempotent: re-running reuses existing tokens/battle.
 */
class GamesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GamesSeeder::class);

        $service = Service::where('name', 'Driving Licence Renewal')->where('is_active', true)->first()
            ?? Service::where('is_active', true)->orderBy('id')->first();
        if (! $service) {
            $this->command?->warn('Games demo: no active service found — skipped demo players.');

            return;
        }

        $queues = app(QueueManager::class);
        $users = [];
        foreach ([['games.demo@waitwise.np', 'Games Demo Host'], ['games.demo2@waitwise.np', 'Games Demo Guest']] as [$email, $name]) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name.' (demo)', 'password' => 'Demo@1234']);
            $docs = collect($service->documents ?? [])->mapWithKeys(fn ($d) => [$d => 'available'])->all();
            $queues->join($service->fresh(), $u, $docs); // returns the existing active ticket if any
            $users[] = $u;
        }

        $games = app(CompetitionService::class);
        if (! $games->activeSeat($users[0])) {
            $games->create($users[0], ['visibility' => 'open', 'max_players' => 4, 'question_count' => 5, 'category' => null]);
        }
        $this->command?->info('Games demo: '.$users[0]->email.' hosts an open battle in '.$service->name.'.');
    }
}
