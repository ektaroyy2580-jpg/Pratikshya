<?php

namespace Database\Seeders\Modules;

use App\Models\AdCampaign;
use App\Models\Advertiser;
use App\Models\AdvertiserUser;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fictional demo advertisers and campaigns (all marked is_demo). Safe to re-run: natural keys + firstOrCreate,
 * existing campaign statuses are never reset. NO ad events are seeded — reports only ever show real interactions.
 *
 * Demo advertiser login: ads.demo@waitwise.np / Demo@1234 (owner of Himalayan Brew Café and Sajilo Pharmacy).
 */
class AdsSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'ads.demo@waitwise.np'], ['name' => 'Asha Advertiser (Demo)', 'password' => 'Demo@1234']);

        $advertisers = [
            'Himalayan Brew Café' => ['cafe', 'Asha Gurung', 'hello@himalayanbrew.demo', true],
            'Sajilo Pharmacy' => ['pharmacy', 'Ramesh Shrestha', 'care@sajilopharmacy.demo', true],
            'Kathmandu Book House' => ['bookstore', 'Sita Maharjan', 'books@ktmbookhouse.demo', false],
            'Sahayatri Transport' => ['transport', 'Bikash Tamang', 'ride@sahayatri.demo', false],
        ];
        $ids = [];
        foreach ($advertisers as $name => [$category, $contact, $email, $linkDemoUser]) {
            $a = Advertiser::firstOrNew(['name' => $name]);
            if (! $a->exists) {
                $a->fill(['category' => $category, 'contact_name' => $contact, 'contact_email' => $email]);
                $a->status = 'approved';
                $a->reviewed_at = now();
            }
            $a->is_demo = true;
            $a->save();
            $ids[$name] = $a->id;
            if ($linkDemoUser) {
                AdvertiserUser::firstOrCreate(['user_id' => $user->id, 'advertiser_id' => $a->id], ['role' => 'owner']);
            }
        }

        $start = now()->subDays(3)->toDateString();
        $end = now()->addDays(90)->toDateString();
        $campaigns = [
            ['Himalayan Brew Café', 'Queue-side coffee', 'active', [
                'headline' => 'Waiting a while? Your flat white is 3 minutes away',
                'body' => 'Freshly roasted Ilam beans and quiet seating near the office. Show this card for 10% off.',
                'cta_label' => 'Find us nearby', 'cta_url' => '/find', 'creative_theme' => 'amber', 'target_category' => 'cafe',
                'placements' => ['explore', 'nearby_places', 'queue_page', 'game_hub'], 'allows_rewarded' => true,
            ]],
            ['Sajilo Pharmacy', 'Everyday pharmacy essentials', 'active', [
                'headline' => 'Open till 9 pm — prescriptions and first-aid basics',
                'body' => 'Licensed pharmacists, ORS, masks and everyday essentials close to your queue.',
                'cta_label' => 'See opening hours', 'cta_url' => '/find', 'creative_theme' => 'forest', 'target_category' => 'pharmacy',
                'placements' => ['explore', 'nearby_places'], 'allows_rewarded' => false,
                'healthcare_disclosure' => 'Paid promotion by a demo pharmacy. Not medical advice — follow your doctor\'s guidance.',
            ]],
            ['Kathmandu Book House', 'Read while you wait', 'active', [
                'headline' => 'New Nepali fiction and comics, 15% off this week',
                'body' => 'Pick up something to read before your number is called.',
                'cta_label' => 'Browse picks', 'cta_url' => '/find', 'creative_theme' => 'blue', 'target_category' => 'bookstore',
                'placements' => ['explore', 'game_hub', 'post_game_reward'], 'allows_rewarded' => true,
            ]],
            ['Kathmandu Book House', 'Exam season stationery', 'pending_review', [
                'headline' => 'Exam season? Notebooks, pens and guides in one stop',
                'body' => 'Student bundle from NPR 450.',
                'cta_label' => 'See bundles', 'cta_url' => '/find', 'creative_theme' => 'rose', 'target_category' => 'bookstore',
                'placements' => ['explore', 'nearby_places'], 'allows_rewarded' => false,
            ]],
            ['Sahayatri Transport', 'Ride home after your visit', 'active', [
                'headline' => 'Done here? Shared micro-bus seats every 10 minutes',
                'body' => 'Ring Road and Lalitpur routes. Fixed fares, no surge.',
                'cta_label' => 'See routes', 'cta_url' => '/find', 'creative_theme' => 'teal', 'target_category' => 'transport',
                'placements' => ['explore', 'nearby_places', 'post_game_reward'], 'allows_rewarded' => true,
            ]],
        ];

        foreach ($campaigns as [$advertiser, $name, $status, $fields]) {
            $c = AdCampaign::firstOrNew(['advertiser_id' => $ids[$advertiser], 'name' => $name]);
            if (! $c->exists) {
                $c->fill($fields + [
                    'starts_on' => $start, 'ends_on' => $end,
                    'budget_total' => 25000, 'daily_budget' => 1500, 'cpm_rate' => 150, 'cpc_rate' => 10,
                ]);
                $c->status = $status;
                $c->is_healthcare = ! empty($fields['healthcare_disclosure']);
                $c->submitted_at = now()->subDays(4);
                $c->approved_at = $status === 'active' ? now()->subDays(3) : null;
            } elseif ($c->status === 'active' && $c->ends_on->isPast()) {
                $c->ends_on = $end; // keep the demo running when re-seeded later
            }
            $c->is_demo = true;
            $c->save();
        }
    }
}
