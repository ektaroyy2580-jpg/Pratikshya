<?php

namespace App\Services\English;

use App\Models\EnglishPracticeSession;
use App\Models\EnglishPracticeTurn;
use App\Models\EnglishScenario;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Rewards\RewardService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Session lifecycle for English practice. Practice never touches queue order;
 * the ticket is only read to size the session to the user's expected wait.
 */
class PracticeService
{
    public function __construct(private FeedbackService $feedback, private RewardService $rewards) {}

    /** The user's active (waiting/called/serving) ticket, if any. */
    public function activeTicket(User $user): ?Ticket
    {
        return $user->tickets()->whereIn('status', Ticket::ACTIVE)->with('service')->latest('id')->first();
    }

    /**
     * Session length suggestion from the conservative (low) end of the wait estimate.
     *
     * @return array{ticket:?Ticket, wait_low:?int, suggested:int, options:array<int,bool>, message:string}
     */
    public function suggestion(User $user, ?Ticket $ticket = null): array
    {
        $ticket ??= $this->activeTicket($user);
        $waitLow = null;
        if ($ticket && $ticket->isActive()) {
            try {
                $waitLow = (int) ($ticket->stateArray()['wait_low'] ?? 0);
            } catch (Throwable $e) {
                $waitLow = null;
            }
        }

        if ($waitLow === null) {
            return [
                'ticket' => null, 'wait_low' => null, 'suggested' => 5,
                'options' => array_fill_keys(EnglishPracticeSession::DURATIONS, true),
                'message' => 'Pick any length — a 5-minute session is a good start.',
            ];
        }

        $suggested = $waitLow >= 12 ? 10 : ($waitLow >= 6 ? 5 : 3);
        $options = [];
        foreach (EnglishPracticeSession::DURATIONS as $d) {
            $options[$d] = $d <= max(3, $waitLow);
        }
        $message = match (true) {
            $ticket->status !== 'waiting' => 'You are being called now — a quick 3-minute session later is better.',
            $waitLow <= 3 => 'Your turn may come soon, so keep it to a quick 3-minute session.',
            default => 'At least '.$waitLow.' minutes until your turn (conservative estimate) — a '.$suggested.'-minute session fits.',
        };

        return ['ticket' => $ticket, 'wait_low' => $waitLow, 'suggested' => $ticket->status !== 'waiting' ? 3 : $suggested, 'options' => $options, 'message' => $message];
    }

    public function start(User $user, EnglishScenario $scenario, int $minutes, string $mode, bool $consentAudio): EnglishPracticeSession
    {
        $perDuration = config('english.prompts_per_duration', [3 => 3, 5 => 5, 10 => 8]);
        $count = min($scenario->promptCount(), $perDuration[$minutes] ?? 5);
        $plan = range(0, max(0, $count - 1));

        $session = new EnglishPracticeSession([
            'english_scenario_id' => $scenario->id,
            'ticket_id' => $this->activeTicket($user)?->id,
            'duration_target_minutes' => $minutes,
            'mode' => $mode,
            'consent_audio' => $mode === 'voice' ? $consentAudio : false,
        ]);
        $session->user_id = $user->id;
        $session->status = 'active';
        $session->plan = $plan;
        $session->started_at = now();
        $session->save();

        return $session;
    }

    /**
     * Store an answer for the next planned prompt and return [turn, nextPrompt|null].
     * The prompt is chosen by the server, never by the client.
     */
    public function submitTurn(EnglishPracticeSession $session, string $text, string $inputMode, ?int $elapsed, ?string $sttEngine = null): array
    {
        $scenario = $session->scenario;

        return DB::transaction(function () use ($session, $scenario, $text, $inputMode, $elapsed, $sttEngine) {
            $answered = $session->turns()->count();
            $prompt = $session->nextPrompt($answered);
            if ($prompt === null) {
                throw new PracticeException('All prompts in this session are answered — finish the session to see your summary.');
            }

            $feedback = $this->feedback->analyse($text, [
                'prompt' => $prompt['prompt'] ?? '',
                'title' => $scenario->title,
                'type' => $scenario->type,
                'target_vocabulary' => $scenario->target_vocabulary ?? [],
                'target' => $prompt['target'] ?? null,
                'follow_up' => $prompt['follow_up'] ?? null,
                'elapsed_seconds' => $elapsed,
                'input_mode' => $inputMode,
                'vocab_already_used' => $session->turns()->get()->flatMap(fn ($t) => $t->feedback['metrics']['target_vocab_used'] ?? [])->unique()->values()->all(),
            ]);
            // Which speech-to-text engine produced a voice transcript (metadata only — never audio).
            if ($inputMode === 'voice' && in_array($sttEngine, ['ondevice', 'browser'], true)) {
                $feedback['stt_engine'] = $sttEngine;
            }

            try {
                $turn = $session->turns()->create([
                    'turn_index' => $answered,
                    'prompt' => (string) ($prompt['prompt'] ?? ''),
                    'user_text' => $text,
                    'input_mode' => $inputMode,
                    'elapsed_seconds' => $elapsed,
                    'feedback' => $feedback,
                ]);
            } catch (QueryException $e) {
                throw new PracticeException('That answer was already received — refresh to continue.');
            }

            return [$turn, $session->nextPrompt($answered + 1)];
        });
    }

    /** Finish a session: aggregate heuristic metrics, write the summary, award points once. Idempotent. */
    public function complete(EnglishPracticeSession $session): EnglishPracticeSession
    {
        if (! $session->isActive()) {
            return $session;
        }
        $turns = $session->turns()->get();
        $scenario = $session->scenario;
        $ended = now();

        $scores = $this->scores($turns, $scenario);
        $seconds = max(0, $session->started_at ? $session->started_at->diffInSeconds($ended) : 0);
        $minutes = round(min($seconds, $session->duration_target_minutes * 60 * 3) / 60, 1);
        if ($turns->isNotEmpty()) {
            $minutes = max(0.5, $minutes);
        }

        $ruleCounts = [];
        foreach ($turns as $t) {
            foreach ($t->feedback['corrections'] ?? [] as $c) {
                $ruleCounts[$c['rule'] ?? 'ai'] = ($ruleCounts[$c['rule'] ?? 'ai'] ?? 0) + 1;
            }
        }
        arsort($ruleCounts);
        $focus = [];
        foreach (array_slice($ruleCounts, 0, 3, true) as $rule => $n) {
            $example = $turns->flatMap(fn ($t) => $t->feedback['corrections'] ?? [])->firstWhere('rule', $rule);
            $focus[] = [
                'label' => FeedbackService::RULE_LABELS[$rule] ?? 'Grammar',
                'count' => $n,
                'example' => $example ? $example['original'].' → '.$example['suggestion'] : null,
                'tip' => $example['explanation'] ?? null,
            ];
        }

        $strengths = [];
        if ($scores['turns_answered'] >= $session->plannedCount() && $scores['turns_answered'] > 0) {
            $strengths[] = 'You answered every prompt in the session.';
        }
        if (($scores['target_vocabulary_used'] ?? 0) > 0) {
            $strengths[] = 'You used '.$scores['target_vocabulary_used'].' of '.$scores['target_vocabulary_total'].' target words.';
        }
        if ($scores['turns_answered'] > 0 && $scores['grammar_points_flagged'] === 0) {
            $strengths[] = 'No grammar points flagged by the checker.';
        }
        if (($scores['avg_words_per_answer'] ?? 0) >= 20) {
            $strengths[] = 'Detailed answers (about '.$scores['avg_words_per_answer'].' words each).';
        }
        if ($scores['turns_answered'] > 0 && $scores['filler_words_total'] === 0) {
            $strengths[] = 'No filler words detected.';
        }

        $sources = $turns->pluck('feedback.source')->filter()->unique()->values()->all();
        $summary = [
            'minutes_practised' => $minutes,
            'headline' => $this->headline($scores),
            'strengths' => $strengths,
            'focus_next' => $focus,
            'vocabulary_used' => $scores['vocabulary_used_list'],
            'vocabulary_to_try' => array_values(array_diff($scenario->target_vocabulary ?? [], $scores['vocabulary_used_list'])),
            'feedback_source' => count($sources) > 1 ? 'mixed' : ($sources[0] ?? 'rules'),
            'points_awarded' => 0,
        ];
        unset($scores['vocabulary_used_list']);

        DB::transaction(function () use ($session, $summary, $scores, $ended) {
            $fresh = EnglishPracticeSession::whereKey($session->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status !== 'active') {
                return;
            }
            $session->status = 'completed';
            $session->ended_at = $ended;
            $session->scores = $scores;
            $session->summary = $summary;
            $session->save();
        });

        if ($session->status === 'completed' && $scores['turns_answered'] >= (int) config('english.reward_min_turns', 2)) {
            $tx = $this->rewards->award($session->user, (int) config('english.reward_points', 15), 'english', 'Completed English practice: '.$scenario->title, 'english:session:'.$session->id, $session);
            if ($tx) {
                $summary['points_awarded'] = $tx->points;
                $session->summary = $summary;
                $session->save();
            }
        }

        return $session->fresh();
    }

    private function scores($turns, EnglishScenario $scenario): array
    {
        $n = $turns->count();
        $allText = $turns->pluck('user_text')->implode(' ');
        $words = $this->feedback->words($allText);
        $wordCount = count($words);
        $metrics = $turns->map(fn ($t) => $t->feedback['metrics'] ?? []);
        $fillers = (int) $metrics->sum('filler_count');
        $paces = $metrics->pluck('speaking_pace_wpm')->filter()->values();
        $matches = $turns->map(fn ($t) => $t->feedback['pronunciation']['match_pct'] ?? null)->filter(fn ($v) => $v !== null)->values();
        $used = $metrics->flatMap(fn ($m) => $m['target_vocab_used'] ?? [])->unique()->values()->all();
        $sentences = (int) $metrics->sum('sentence_count');

        return [
            'heuristic' => true,
            'turns_answered' => $n,
            'words_total' => $wordCount,
            'avg_words_per_answer' => $n ? (int) round($wordCount / $n) : 0,
            'vocabulary_variety_ttr' => $wordCount >= 8 ? round(count(array_unique(array_map('mb_strtolower', $words))) / $wordCount, 2) : null,
            'avg_sentence_length_words' => $sentences ? round($wordCount / $sentences, 1) : null,
            'filler_words_total' => $fillers,
            'filler_words_per_100' => $wordCount ? round($fillers / $wordCount * 100, 1) : 0,
            'grammar_points_flagged' => (int) $turns->sum(fn ($t) => count($t->feedback['corrections'] ?? [])),
            'target_vocabulary_used' => count($used),
            'target_vocabulary_total' => count($scenario->target_vocabulary ?? []),
            'avg_speaking_pace_wpm' => $paces->isNotEmpty() ? (int) round($paces->avg()) : null,
            'browser_transcript_match_pct' => $matches->isNotEmpty() ? (int) round($matches->avg()) : null,
            'vocabulary_used_list' => $used,
        ];
    }

    private function headline(array $s): string
    {
        if ($s['turns_answered'] === 0) {
            return 'No answers this time — that’s okay. Start a short session whenever you are ready.';
        }
        if ($s['grammar_points_flagged'] === 0) {
            return 'Excellent session — clear answers with nothing flagged. Keep the streak going!';
        }

        return 'Well done — '.$s['turns_answered'].' answer'.($s['turns_answered'] > 1 ? 's' : '').' practised. A few small points to polish below.';
    }

    /** Progress overview for the practice home page. */
    public function progress(User $user): array
    {
        $done = EnglishPracticeSession::where('user_id', $user->id)->where('status', 'completed');
        $completed = (clone $done)->get(['id', 'ended_at', 'summary']);

        $days = $completed->pluck('ended_at')->filter()->map(fn (Carbon $d) => $d->toDateString())->unique()->sortDesc()->values();
        $streak = 0;
        $cursor = now()->startOfDay();
        if ($days->isNotEmpty() && $days->first() !== $cursor->toDateString()) {
            $cursor = $cursor->subDay(); // a streak stays alive until the end of today
        }
        foreach ($days as $day) {
            if ($day === $cursor->toDateString()) {
                $streak++;
                $cursor = $cursor->subDay();
            } elseif ($day < $cursor->toDateString()) {
                break;
            }
        }

        $recentFeedback = EnglishPracticeTurn::query()
            ->whereHas('session', fn ($q) => $q->where('user_id', $user->id))
            ->with('session.scenario:id,title,type')
            ->latest('id')->limit(4)->get();

        return [
            'sessions' => $completed->count(),
            'minutes' => round($completed->sum(fn ($s) => (float) ($s->summary['minutes_practised'] ?? 0)), 1),
            'streak' => $streak,
            'answers' => EnglishPracticeTurn::whereHas('session', fn ($q) => $q->where('user_id', $user->id))->count(),
            'practised_today' => $days->first() === now()->toDateString(),
            'recent_feedback' => $recentFeedback,
            'recent_sessions' => EnglishPracticeSession::where('user_id', $user->id)->with('scenario:id,title,type,icon')
                ->latest('id')->limit(5)->get(),
        ];
    }
}
