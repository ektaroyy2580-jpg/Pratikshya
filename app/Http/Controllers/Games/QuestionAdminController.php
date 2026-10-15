<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Http\Requests\Games\GenerateQuestionsRequest;
use App\Models\AuditLog;
use App\Models\GameQuestion;
use App\Services\Games\LeaderboardService;
use App\Services\Games\QuestionGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin-only (route middleware role:admin): review, approve, disable and AI-generate questions. */
class QuestionAdminController extends Controller
{
    public function index(Request $request, QuestionGenerator $generator, LeaderboardService $boards)
    {
        $status = in_array($request->query('status'), GameQuestion::STATUSES, true) ? $request->query('status') : null;
        $category = array_key_exists((string) $request->query('category'), GameQuestion::CATEGORIES) ? $request->query('category') : null;
        $source = in_array($request->query('source'), ['bank', 'ai'], true) ? $request->query('source') : null;

        $questions = GameQuestion::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($source, fn ($q) => $q->where('source', $source))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->latest('id')->paginate(20)->withQueryString();

        $counts = GameQuestion::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('games.admin.questions', [
            'questions' => $questions,
            'counts' => $counts,
            'filters' => compact('status', 'category', 'source'),
            'aiEnabled' => $generator->enabled(),
            'providerLabel' => $generator->providerLabel(),
            'stats' => $boards->stats(),
        ]);
    }

    public function status(Request $request, GameQuestion $question)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'disabled'])]]);
        $question->forceFill(['status' => $data['status']])->save();
        AuditLog::record('games.question_'.$data['status'], $question);

        return back()->with('ok', 'Question '.($data['status'] === 'approved' ? 'approved — it can now appear in battles.' : 'disabled.'));
    }

    public function generate(GenerateQuestionsRequest $request, QuestionGenerator $generator)
    {
        $v = $request->validated();
        $result = $generator->generate($v['category'], $v['difficulty'], (int) $v['count'], $request->user());

        return $result['ok']
            ? back()->with('ok', $result['message'])
            : back()->withErrors(['queue' => $result['message']]);
    }
}
