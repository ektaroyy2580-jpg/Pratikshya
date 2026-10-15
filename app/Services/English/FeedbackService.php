<?php

namespace App\Services\English;

use App\Services\Ai\AiClient;

/**
 * Supportive feedback for one spoken (browser transcript) or typed answer.
 *
 * - Metrics (word count, filler words, speaking pace, vocabulary variety, sentence length,
 *   target vocabulary) are ALWAYS computed locally and deterministically. They are
 *   heuristics, labelled as such, never a grade.
 * - Corrections / vocabulary / fluency notes / summary / follow-up come from the configured
 *   AI provider when enabled AND its JSON passes the schema check; otherwise from the
 *   rule-based engine below.
 * - Pronunciation drills only compare the browser's transcript to the target phrase.
 *   That is a "transcript match hint", explicitly NOT a pronunciation score.
 */
class FeedbackService
{
    public const RULE_LABELS = [
        'capital_i' => 'Capital “I”',
        'capital_start' => 'Capital letters',
        'articles' => 'Articles: a / an',
        'subject_verb' => 'Subject–verb agreement',
        'double_negative' => 'Double negatives',
        'stative_having' => '“I am having” → “I have”',
        'needful' => 'Natural phrasing',
        'since_for' => '“since” vs “for”',
        'redundant' => 'Redundant words',
        'tense_after_aux' => 'Verb form after did / to',
        'perfect_participle' => 'Present perfect form',
        'question_aux' => 'Questions need do / does',
        'question_order' => 'Question word order',
        'comparatives' => 'Comparatives',
        'plural_after_one_of' => '“one of my …s”',
        'agree' => '“I agree”',
        'ai' => 'Tutor suggestion',
    ];

    public const FILLERS = ['um', 'umm', 'uh', 'uhh', 'uhm', 'er', 'erm', 'hmm', 'basically', 'you know', 'i mean'];

    private const STOPWORDS = ['the', 'and', 'that', 'this', 'with', 'have', 'from', 'they', 'your', 'about', 'there', 'their', 'what', 'when', 'were', 'will', 'would', 'which', 'been', 'into', 'also', 'very', 'because', 'some', 'then', 'than', 'just', 'like', 'want', 'here'];

    private const SIMPLE_WORDS = [
        'good' => 'helpful, reliable, excellent',
        'nice' => 'pleasant, friendly, lovely',
        'bad' => 'difficult, poor, disappointing',
        'big' => 'large, major, significant',
        'thing' => 'a more specific noun (task, issue, item)',
        'things' => 'more specific nouns (tasks, issues, items)',
        'very' => 'a stronger adjective (very good → excellent, very tired → exhausted)',
        'happy' => 'pleased, delighted, glad',
        'get' => 'receive, obtain, collect',
    ];

    private const MODAL_BEFORE_SUBJECT = ['does', 'did', 'do', 'will', 'would', 'can', 'could', 'should', 'may', 'might', 'must', 'to', 'let', 'make', 'help', 'see', 'watch', 'hear', "doesn't", "didn't", "won't", "can't", "don't", 'why', 'shall'];

    public function __construct(private AiClient $ai) {}

    /**
     * @param  array{prompt?:string,title?:string,type?:string,target_vocabulary?:array,target?:string,follow_up?:string,elapsed_seconds?:int|null,input_mode?:string}  $ctx
     */
    public function analyse(string $text, array $ctx = []): array
    {
        $text = $this->clean($text);
        $mode = ($ctx['input_mode'] ?? 'text') === 'voice' ? 'voice' : 'text';
        $metrics = $this->metrics($text, $ctx['target_vocabulary'] ?? [], $mode, $ctx['elapsed_seconds'] ?? null);

        $pron = null;
        if (($ctx['type'] ?? null) === 'pronunciation' && ! empty($ctx['target'])) {
            $pron = $this->pronunciationMatch($text, (string) $ctx['target'], $mode);
        }

        // Pronunciation drills are about the target phrase, not grammar → no AI call needed.
        $ai = $pron ? null : $this->aiFeedback($text, $ctx);

        $feedback = $ai ?? $this->ruleFeedback($text, $ctx, $metrics, $mode, $pron);
        $feedback['source'] = $ai ? 'ai' : 'rules';
        $feedback['metrics'] = $metrics;
        $feedback['pronunciation'] = $pron;

        return $feedback;
    }

    // ------------------------------------------------------------------ AI path

    private function aiFeedback(string $text, array $ctx): ?array
    {
        if (! $this->ai->enabled() || $text === '') {
            return null;
        }

        $system = 'You are a warm, supportive English speaking coach for adult learners in Nepal practising while they wait in a queue. '
            .'Correct only real mistakes, explain each briefly in simple English, and suggest more natural phrasing. '
            .'Never score or judge pronunciation (you only see a text transcript). '
            .'Return a JSON object with exactly these keys: '
            .'"summary" (1-2 encouraging sentences), '
            .'"corrections" (array of at most 5 objects {"original","suggestion","explanation"}), '
            .'"vocabulary" (array of at most 4 objects {"suggestion","note"}), '
            .'"fluency" (array of at most 3 short strings), '
            .'"follow_up" (one short follow-up question that continues the conversation).';

        $prompt = 'Scenario: '.($ctx['title'] ?? 'English practice')."\n"
            .'Question the learner answered: '.($ctx['prompt'] ?? '')."\n"
            .'Target vocabulary: '.implode(', ', array_slice($ctx['target_vocabulary'] ?? [], 0, 12))."\n"
            .'Learner answer (browser speech transcript or typed text, may lack punctuation): "'.$this->redact($text).'"';

        $data = $this->ai->json($system, $prompt, fn ($d) => self::validAiPayload($d), 700);

        return $data ? $this->normaliseAi($data) : null;
    }

    /** Schema check for AI output. Anything that fails → local rule-based fallback. */
    public static function validAiPayload(mixed $d): bool
    {
        $str = fn ($v, $max, $required = true) => is_string($v) && mb_strlen($v) <= $max && (! $required || trim($v) !== '');
        $list = fn ($v, $max) => is_array($v) && array_is_list($v) && count($v) <= $max;

        if (! is_array($d) || ! $str($d['summary'] ?? null, 600) || ! $str($d['follow_up'] ?? null, 300)) {
            return false;
        }
        if (! $list($d['corrections'] ?? null, 8) || ! $list($d['vocabulary'] ?? null, 8) || ! $list($d['fluency'] ?? null, 6)) {
            return false;
        }
        foreach ($d['corrections'] as $c) {
            if (! is_array($c) || ! $str($c['original'] ?? null, 300) || ! $str($c['suggestion'] ?? null, 300) || ! $str($c['explanation'] ?? null, 400)) {
                return false;
            }
        }
        foreach ($d['vocabulary'] as $v) {
            if (! is_array($v) || ! $str($v['suggestion'] ?? null, 160) || ! $str($v['note'] ?? null, 300, false)) {
                return false;
            }
        }
        foreach ($d['fluency'] as $f) {
            if (! $str($f, 300)) {
                return false;
            }
        }

        return true;
    }

    private function normaliseAi(array $d): array
    {
        $s = fn ($v, $max) => mb_substr(trim(strip_tags((string) $v)), 0, $max);

        return [
            'summary' => $s($d['summary'], 600),
            'corrections' => array_map(fn ($c) => [
                'rule' => 'ai',
                'original' => $s($c['original'], 300),
                'suggestion' => $s($c['suggestion'], 300),
                'explanation' => $s($c['explanation'], 400),
            ], $d['corrections']),
            'vocabulary' => array_map(fn ($v) => ['suggestion' => $s($v['suggestion'], 160), 'note' => $s($v['note'] ?? '', 300)], $d['vocabulary']),
            'fluency' => array_map(fn ($f) => $s($f, 300), $d['fluency']),
            'follow_up' => $s($d['follow_up'], 300),
        ];
    }

    /** Remove obvious personal identifiers before anything leaves the server. */
    public function redact(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[email]', $text);

        return preg_replace('/\+?\d[\d\s-]{7,}\d/', '[number]', $text);
    }

    // ------------------------------------------------------------------ Rule-based path

    private function ruleFeedback(string $text, array $ctx, array $m, string $mode, ?array $pron): array
    {
        $corrections = $pron ? [] : $this->grammarIssues($text, $mode);
        $vocabulary = $pron ? [] : $this->vocabularySuggestions($text, $m, $ctx['vocab_already_used'] ?? []);
        $fluency = $pron ? [] : $this->fluencyNotes($m, $mode);

        return [
            'summary' => $this->summary($m, $corrections, $pron),
            'corrections' => $corrections,
            'vocabulary' => $vocabulary,
            'fluency' => $fluency,
            'follow_up' => $this->followUp($text, $ctx, $pron),
        ];
    }

    /** Common grammar checks. Each issue: {rule, original, suggestion, explanation}. */
    public function grammarIssues(string $text, string $mode = 'text'): array
    {
        $issues = [];
        $add = function (string $rule, string $original, string $suggestion, string $explanation) use (&$issues) {
            $key = $rule.'|'.mb_strtolower(trim($original));
            if (! isset($issues[$key]) && trim($original) !== trim($suggestion)) {
                $issues[$key] = ['rule' => $rule, 'original' => trim($original), 'suggestion' => trim($suggestion), 'explanation' => $explanation];
            }
        };

        // 1. Lower-case pronoun "i".
        if (preg_match('/(?<![\w\'’.-])i(?=(?:[\'’](?:m|ve|ll|d))?(?:[\s,!?;:]|\.(?!\w)|$))/u', $text)) {
            $add('capital_i', 'i', 'I', 'The pronoun “I” is always written with a capital letter, even in the middle of a sentence.');
        }

        // 2. Sentence starts with a lower-case letter (typed text only; transcripts vary by browser).
        if ($mode === 'text' && preg_match('/(?:^|[.!?]\s+)([a-z][a-z\']*)/u', $text, $mm) && $mm[1] !== 'i') {
            $add('capital_start', $mm[1], ucfirst($mm[1]), 'Start each sentence with a capital letter.');
        }

        // 3. Articles a / an (by sound, with common exceptions).
        if (preg_match_all('/\b(a)\s+([aeiou][a-z]*)/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                if (! preg_match('/^(uni|use|usu|uti|ure|eu|one|once|ewe)/i', $x[2])) {
                    $add('articles', $x[0], ($x[1] === 'A' ? 'An' : 'an').' '.$x[2], 'Use “an” before a vowel sound (an apple, an interview, an hour) and “a” before a consonant sound (a bank, a university).');
                }
            }
        }
        if (preg_match_all('/\b(an)\s+([b-df-hj-np-tv-z][a-z]*)/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                if (! preg_match('/^(hour|honest|honou?r|heir)/i', $x[2]) && strtoupper($x[2]) !== $x[2]) {
                    $add('articles', $x[0], ($x[1] === 'An' ? 'A' : 'a').' '.$x[2], 'Use “a” before a consonant sound (a bank, a job, a university) and “an” before a vowel sound (an office, an hour).');
                }
            }
        }

        // 4. Subject–verb agreement (frequent patterns).
        if (preg_match_all('/\b(he|she|it|my (?:father|mother|brother|sister|friend|boss|manager|teacher|son|daughter|husband|wife))\s+(don\'t|do not)\b/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $add('subject_verb', $x[0], $x[1].' '.(stripos($x[2], 'not') !== false ? 'does not' : "doesn't"), 'With he / she / it we use “does not / doesn’t”, not “don’t”.');
            }
        }
        $verbs = 'have|go|do|want|like|work|live|need|say|make|know|think|come|study|play|teach|try|watch|speak|take|give|look|feel|help|use|stay';
        if (preg_match_all('/\b(he|she|it|my (?:father|mother|brother|sister|friend|boss|manager|teacher|son|daughter|husband|wife))\s+('.$verbs.')\b/i', $text, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($mm as $x) {
                if (in_array(mb_strtolower($this->previousWord($text, $x[0][1])), self::MODAL_BEFORE_SUBJECT, true)) {
                    continue; // "does he like", "let it go", "to make it work"
                }
                $add('subject_verb', $x[0][0], $x[1][0].' '.$this->thirdPerson($x[2][0]), 'With he / she / it (one person or thing) in the present simple, add -s or -es to the verb: he works, she goes, it has.');
            }
        }
        if (preg_match_all('/\b(I)\s+(is|are)\b/', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $add('subject_verb', $x[0], 'I am', 'With “I” we use “am”: I am, I’m.');
            }
        }
        if (preg_match_all('/\b(you|we|they)\s+(is|was)\b/i', $text, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($mm as $x) {
                if (in_array(mb_strtolower($this->previousWord($text, $x[0][1])), ['if', 'as'], true) && strtolower($x[2][0]) === 'was') {
                    continue;
                }
                $add('subject_verb', $x[0][0], $x[1][0].' '.(strtolower($x[2][0]) === 'is' ? 'are' : 'were'), 'With you / we / they we use “are” (present) and “were” (past).');
            }
        }
        if (preg_match_all('/\b(I|you|we|they)\s+(has|does|doesn\'t|goes)\b/i', $text, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            $map = ['has' => 'have', 'does' => 'do', "doesn't" => "don't", 'goes' => 'go'];
            foreach ($mm as $x) {
                if (in_array(mb_strtolower($this->previousWord($text, $x[0][1])), ['what', 'how', 'where', 'when', 'why', 'who'], true) && strtolower($x[2][0]) === 'does') {
                    continue;
                }
                $add('subject_verb', $x[0][0], $x[1][0].' '.$map[strtolower($x[2][0])], 'With I / you / we / they the verb has no -s: I have, they do, we go.');
            }
        }
        if (preg_match_all('/\bthere is\s+(many|several|two|three|four|five|six|lots of)\b/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $add('subject_verb', $x[0], 'there are '.$x[1], 'Use “there are” with plural nouns: there are many people, there are two counters.');
            }
        }

        // 5. Double negatives.
        if (preg_match_all('/\b(don\'t|doesn\'t|didn\'t|can\'t|cannot|won\'t|never|not|isn\'t|aren\'t|wasn\'t)\s+((?:[a-z]+\s+){0,2}?)(nothing|nobody|nowhere|none|no one|no(?=\s+[a-z]))/i', $text, $mm, PREG_SET_ORDER)) {
            $map = ['nothing' => 'anything', 'nobody' => 'anybody', 'nowhere' => 'anywhere', 'none' => 'any', 'no one' => 'anyone', 'no' => 'any'];
            foreach ($mm as $x) {
                $add('double_negative', $x[0], $x[1].' '.$x[2].$map[strtolower($x[3])], 'In standard English one negative is enough. “I don’t have nothing” becomes “I don’t have anything” (or “I have nothing”).');
            }
        }

        // 6. Common Nepali-English / South-Asian English patterns.
        if (preg_match_all('/\b(I am|I\'m|we are|we\'re|he is|she is|he\'s|she\'s|they are)\s+having\s+([a-z]+)(?:\s+([a-z]+))?/i', $text, $mm, PREG_SET_ORDER)) {
            $ok = '/^(lunch|dinner|breakfast|tea|coffee|fun|trouble|difficulty|problems?|a (meeting|party|good|great|bad|hard|tough|baby|shower|conversation|chat|look|rest|nap|break|wonderful|nice|lovely))$/i';
            foreach ($mm as $x) {
                $next = trim($x[2].' '.($x[3] ?? ''));
                if (preg_match($ok, $x[2]) || preg_match($ok, $next)) {
                    continue;
                }
                $subj = strtolower($x[1]);
                $have = in_array($subj, ['he is', 'she is', "he's", "she's"], true) ? explode(' ', str_replace("'s", ' is', $x[1]))[0].' has' : explode(' ', str_replace(["'m", "'re"], [' am', ' are'], $x[1]))[0].' have';
                $add('stative_having', $x[1].' having '.$x[2], preg_replace('/^i\b/', 'I', $have).' '.$x[2], '“Have” for possession, illness or experience is a state, so we don’t use -ing: “I have a fever”, “I have two years of experience”.');
            }
        }
        if (preg_match('/\bdo the needful\b/i', $text, $mm)) {
            $add('needful', $mm[0], 'please take care of this / please do what is necessary', '“Do the needful” sounds old-fashioned to most international listeners. Say exactly what you need, e.g. “Could you please update my form?”');
        }
        if (preg_match_all('/\bsince\s+(\d+|a|one|two|three|four|five|six|seven|eight|nine|ten|many|few|several)\s+(years?|months?|weeks?|days?|hours?|minutes?)\b/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $add('since_for', $x[0], 'for '.$x[1].' '.$x[2], 'Use “for” with a length of time (for 2 years) and “since” with a starting point (since 2022, since Monday).');
            }
        }
        // [pattern, rule, replacement (may use $1), explanation]
        foreach ([
            ['/\bdiscuss about\b/i', 'redundant', 'discuss', '“Discuss” already means “talk about”, so no “about” is needed: “Let’s discuss the plan.”'],
            ['/\brevert back\b/i', 'redundant', 'reply', '“Revert back” is redundant; in international English say “reply” or “get back to you”.'],
            ['/\breturn back\b/i', 'redundant', 'return', '“Return” already means “go back”, so “back” is not needed.'],
            ['/\brepeat again\b/i', 'redundant', 'repeat', '“Repeat” already means “say again”.'],
            ['/\bcousin (brother|sister)\b/i', 'needful', 'cousin', 'In English “cousin” is used for both male and female cousins.'],
            ['/\bwhat is your good name\b/i', 'needful', 'May I know your name?', '“Good name” is a direct translation; “May I know your name?” is polite and natural.'],
            ['/\bout of station\b/i', 'needful', 'out of town', '“Out of station” is not used internationally; say “out of town” or “travelling”.'],
            ['/\bpass(?:ed)? out from\b/i', 'needful', 'graduated from', 'Internationally “pass out” means “faint”. Say “I graduated from …”.'],
            ['/\btoday (morning|evening)\b/i', 'needful', 'this $1', 'Say “this morning / this evening”, not “today morning”.'],
            ['/\byesterday night\b/i', 'needful', 'last night', 'Say “last night”, not “yesterday night”.'],
        ] as [$re, $rule, $replacement, $why]) {
            if (preg_match($re, $text, $mm)) {
                $add($rule, $mm[0], preg_replace($re, $replacement, $mm[0]), $why);
            }
        }
        if (preg_match('/(?:^|[.!?]\s+)myself\s+(?!is\b)([a-z]+)/i', $text, $mm)) {
            $add('needful', trim($mm[0], " .!?\t\n"), 'My name is '.ucfirst($mm[1]).' / I’m '.ucfirst($mm[1]), 'To introduce yourself say “My name is …” or “I’m …”. “Myself …” is not used this way in English.');
        }
        if (preg_match_all('/\b(I am|I\'m) agree\b/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $add('agree', $x[0], 'I agree', '“Agree” is a verb, so we say “I agree”, not “I am agree”.');
            }
        }
        if (preg_match_all('/\bone of my (friend|brother|sister|colleague|teacher|relative|cousin|classmate)\b(?!s)/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $add('plural_after_one_of', $x[0], 'one of my '.$x[1].'s', 'After “one of my …” use a plural noun: one of my friends.');
            }
        }

        // 7. Verb form after did/does/do/to (base form) and after have/has (participle).
        $base = ['went' => 'go', 'came' => 'come', 'saw' => 'see', 'took' => 'take', 'gave' => 'give', 'got' => 'get', 'ate' => 'eat', 'wrote' => 'write', 'spoke' => 'speak', 'bought' => 'buy', 'told' => 'tell', 'made' => 'make', 'knew' => 'know', 'thought' => 'think', 'brought' => 'bring', 'paid' => 'pay', 'left' => 'leave'];
        if (preg_match_all('/\b(didn\'t|did not|doesn\'t|does not|don\'t|do not|can\'t|cannot|couldn\'t|could not|to)\s+('.implode('|', array_keys($base)).')\b/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                if (strtolower($x[1]) === 'to' && in_array(strtolower($x[2]), ['left'], true)) {
                    continue;
                }
                $add('tense_after_aux', $x[0], $x[1].' '.$base[strtolower($x[2])], 'After did / does / do / can / to, use the base form of the verb: “I didn’t go”, “I want to see”.');
            }
        }
        $pp = ['came' => 'come', 'went' => 'gone', 'ate' => 'eaten', 'wrote' => 'written', 'saw' => 'seen', 'took' => 'taken', 'gave' => 'given', 'spoke' => 'spoken', 'did' => 'done'];
        if (preg_match_all('/\b(have|has|I\'ve|we\'ve|they\'ve)\s+('.implode('|', array_keys($pp)).')\b/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $add('perfect_participle', $x[0], $x[1].' '.$pp[strtolower($x[2])], 'After have / has, use the past participle: “I have come”, “she has done it”.');
            }
        }

        // 8. Comparatives.
        if (preg_match_all('/\b(more|most)\s+(better|best|worse|worst|easier|bigger|faster|cheaper|happier)\b/i', $text, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $x) {
                $add('comparatives', $x[0], $x[2], '“Better”, “easier” etc. are already comparative, so “more” is not needed.');
            }
        }

        // 9. Questions: missing auxiliary / word order.
        foreach ($this->sentences($text, true) as $sentence) {
            $isQuestion = str_ends_with($sentence, '?') || ($mode === 'voice' && str_word_count($sentence) <= 8);
            if (! $isQuestion) {
                continue;
            }
            $aux = 'am|is|are|was|were|do|does|did|can|could|will|would|should|have|has|had|may|might|must|shall';
            if (preg_match('/^(what|where|when|why|how|how much|how many|how long|how often|which \w+)\s+(you|we|they|he|she|I)\s+(?!(?:'.$aux.'|don\'t|doesn\'t|didn\'t)\b)([a-z]+)/i', $sentence, $q)) {
                $third = in_array(strtolower($q[2]), ['he', 'she'], true);
                $verb = $third ? $this->baseForm($q[3]) : $q[3];
                $add('question_aux', $q[0], ucfirst(strtolower($q[1])).' '.($third ? 'does' : 'do').' '.$q[2].' '.$verb.'?', 'Present-simple questions need “do / does” before the subject: “Where do you work?”, “What does she study?”.');
            }
            if (preg_match('/\b(what time|where|what|who|how)\s+(it|this|that|he|she)\s+is\b\??/i', $sentence, $q) && preg_match('/^\s*'.preg_quote($q[1], '/').'/i', $sentence)) {
                $add('question_order', $q[0], ucfirst(strtolower($q[1])).' is '.$q[2].'?', 'In direct questions the verb comes before the subject: “What time is it?”, “Where is it?”.');
            }
        }

        return array_slice(array_values($issues), 0, 8);
    }

    // ------------------------------------------------------------------ Metrics

    public function metrics(string $text, array $targetVocabulary = [], string $mode = 'text', ?int $elapsed = null): array
    {
        $words = $this->words($text);
        $count = count($words);
        $sentences = $this->sentences($text);
        $hasPunctuation = (bool) preg_match('/[.!?]/', $text);

        [$fillerTotal, $fillers] = $this->countFillers($text);

        $pace = null;
        if ($mode === 'voice' && $elapsed !== null && $elapsed >= 3 && $count >= 3) {
            $pace = (int) round($count / ($elapsed / 60));
            $pace = $pace > 320 ? null : $pace; // implausible timing → don't report
        }

        $lower = array_map('mb_strtolower', $words);
        [$used, $missing] = $this->targetVocabulary($lower, $targetVocabulary);

        return [
            'word_count' => $count,
            'sentence_count' => max(1, count($sentences)),
            'avg_sentence_length' => $count ? round($count / max(1, count($sentences)), 1) : 0,
            'punctuation_available' => $hasPunctuation,
            'filler_count' => $fillerTotal,
            'fillers' => $fillers,
            'fillers_per_100_words' => $count ? round($fillerTotal / $count * 100, 1) : 0,
            'type_token_ratio' => $count >= 8 ? round(count(array_unique($lower)) / $count, 2) : null,
            'speaking_pace_wpm' => $pace,
            'target_vocab_used' => $used,
            'target_vocab_missing' => $missing,
        ];
    }

    public function countFillers(string $text): array
    {
        $lower = ' '.mb_strtolower($text).' ';
        $found = [];
        foreach (self::FILLERS as $f) {
            $n = preg_match_all('/(?<![a-z\'])'.preg_quote($f, '/').'(?![a-z\'])/u', $lower);
            if ($n) {
                $found[$f] = $n;
            }
        }
        // "like" only when it is used as a filler (next to a comma or at the very start).
        $like = preg_match_all('/(?:^\s*|[,.]\s*)like\b|\blike\s*,/u', $lower);
        if ($like) {
            $found['like'] = $like;
        }

        return [array_sum($found), $found];
    }

    private function targetVocabulary(array $lowerWords, array $targets): array
    {
        $joined = ' '.implode(' ', $lowerWords).' ';
        $used = $missing = [];
        foreach ($targets as $t) {
            $t = mb_strtolower(trim((string) $t));
            if ($t === '') {
                continue;
            }
            $stem = preg_replace('/(e|y)$/', '', $t);
            $hit = str_contains($t, ' ')
                ? str_contains($joined, ' '.$t.' ')
                : (bool) preg_match('/\s'.preg_quote(mb_strlen($stem) >= 4 ? $stem : $t, '/').'[a-z]*\s/u', $joined);
            $hit ? $used[] = $t : $missing[] = $t;
        }

        return [$used, $missing];
    }

    // ------------------------------------------------------------------ Pronunciation (transcript match only)

    public function pronunciationMatch(string $heard, string $target, string $mode = 'voice'): array
    {
        $norm = fn ($s) => array_values(array_filter(preg_split('/\s+/', preg_replace('/[^a-z\' ]+/', ' ', mb_strtolower($s)))));
        $t = $norm($target);
        $pool = $norm($heard);
        $missed = [];
        foreach ($t as $w) {
            $i = array_search($w, $pool, true);
            if ($i === false) {
                $missed[] = $w;
            } else {
                unset($pool[$i]);
            }
        }
        $matched = count($t) - count($missed);
        $pct = count($t) ? (int) round($matched / count($t) * 100) : 0;

        $tips = [];
        foreach (array_unique($missed) as $w) {
            if (str_contains($w, 'th')) {
                $tips['th'] = '“th”: put the tip of your tongue lightly between your teeth and blow air (think, three) — or add voice for “this, that”.';
            }
            if (str_starts_with($w, 'v') || str_contains($w, 'v')) {
                $tips['v'] = '“v”: touch your top teeth to your bottom lip and use your voice (very, van). It should buzz.';
            }
            if (str_starts_with($w, 'w')) {
                $tips['w'] = '“w”: round your lips like a small “o” without touching your teeth (water, window).';
            }
        }

        if ($mode !== 'voice') {
            $hint = 'You typed this phrase. Switch on the microphone to see how the browser recognises your speech.';
        } elseif ($pct === 100) {
            $hint = 'The browser recognised every word of the target phrase.';
        } elseif ($pct >= 60) {
            $hint = 'The browser recognised most words. Not recognised: '.implode(', ', array_unique($missed)).'.';
        } else {
            $hint = 'Several words were not recognised: '.implode(', ', array_unique($missed)).'. Try again slowly and clearly.';
        }

        return [
            'heard' => $heard,
            'target' => $target,
            'matched_words' => $matched,
            'total_words' => count($t),
            'match_pct' => $pct,
            'missed' => array_values(array_unique($missed)),
            'hint' => $hint,
            'tips' => array_values($tips),
            'disclaimer' => 'This compares the browser’s transcript with the target phrase. It is NOT a pronunciation score: recognition depends on your microphone, background noise and the browser’s speech engine, which may auto-correct words.',
        ];
    }

    // ------------------------------------------------------------------ Rule-based wording

    private function vocabularySuggestions(string $text, array $m, array $alreadyUsed = []): array
    {
        $out = [];
        // Greetings and fixed phrases ("good morning", "good luck") are fine as they are.
        $plain = preg_replace('/\bgood (morning|afternoon|evening|night|luck|bye)\b|\bnice to meet you\b/i', ' ', $text);
        $lower = array_map('mb_strtolower', $this->words($plain));
        $freq = array_count_values($lower);

        foreach (self::SIMPLE_WORDS as $w => $alt) {
            if (($freq[$w] ?? 0) >= 1 && count($out) < 2) {
                $out[] = ['suggestion' => 'Instead of “'.$w.'”, try: '.$alt, 'note' => 'More precise words make your answer sound confident.'];
            }
        }
        arsort($freq);
        foreach ($freq as $w => $n) {
            if ($n >= 3 && mb_strlen($w) >= 4 && ! in_array($w, self::STOPWORDS, true) && ! isset(self::SIMPLE_WORDS[$w])) {
                $out[] = ['suggestion' => 'You used “'.$w.'” '.$n.' times', 'note' => 'Try a synonym or a pronoun the second time to add variety.'];
                break;
            }
        }
        $toTry = array_values(array_diff($m['target_vocab_missing'], $alreadyUsed));
        if ($toTry) {
            $out[] = ['suggestion' => 'Try using: '.implode(', ', array_slice($toTry, 0, 3)), 'note' => 'These words fit this scenario well.'];
        }

        return array_slice($out, 0, 4);
    }

    private function fluencyNotes(array $m, string $mode): array
    {
        $notes = [];
        if ($m['word_count'] < 6) {
            $notes[] = 'Short answer — try 2–3 full sentences. Add a reason with “because …” or an example with “for example …”.';
        } elseif ($m['word_count'] >= 25) {
            $notes[] = 'Nice, detailed answer — '.$m['word_count'].' words.';
        }
        if ($m['punctuation_available'] && $m['avg_sentence_length'] > 28) {
            $notes[] = 'Some sentences are very long (about '.$m['avg_sentence_length'].' words). Shorter sentences are easier to follow when speaking.';
        }
        if ($m['filler_count'] > 0 && $m['fillers_per_100_words'] >= 5) {
            $notes[] = 'You used '.$m['filler_count'].' filler word'.($m['filler_count'] > 1 ? 's' : '').' ('.implode(', ', array_keys($m['fillers'])).'). A short silent pause is fine — it sounds calmer than “um”.';
        } elseif ($m['filler_count'] === 0 && $m['word_count'] >= 15) {
            $notes[] = 'No filler words detected — smooth delivery.';
        }
        if ($m['speaking_pace_wpm'] !== null) {
            $p = $m['speaking_pace_wpm'];
            $notes[] = 'Speaking pace (not a score): about '.$p.' words per minute — '.($p < 90 ? 'calm and unhurried; you can speak a little more continuously.' : ($p <= 165 ? 'a comfortable conversational pace.' : 'quite fast; slowing down a little helps clarity.'));
        }
        if ($m['type_token_ratio'] !== null && $m['word_count'] >= 20) {
            $notes[] = $m['type_token_ratio'] >= 0.65 ? 'Good variety of words.' : ($m['type_token_ratio'] < 0.45 ? 'Many words are repeated — try a few different ones.' : '');
        }
        if ($mode === 'voice' && ! $m['punctuation_available'] && $m['word_count'] > 12) {
            $notes[] = 'Browser transcripts often have no punctuation, so sentence length is approximate.';
        }

        return array_values(array_filter($notes));
    }

    private function summary(array $m, array $corrections, ?array $pron): string
    {
        if ($pron) {
            return $pron['match_pct'] === 100 ? 'Great — the browser heard the whole phrase. Try it once more a little faster.' : 'Good try! Look at the words the browser didn’t catch and say the phrase again slowly.';
        }
        if ($m['word_count'] === 0) {
            return 'I didn’t catch any words — try again when you are ready.';
        }
        $parts = [];
        $parts[] = $corrections ? 'Good effort — your meaning comes across clearly.' : ($m['word_count'] >= 8 ? 'Clear, well-formed answer — nice work!' : 'Correct so far — now try saying a bit more.');
        if ($m['target_vocab_used']) {
            $parts[] = 'You used useful words: '.implode(', ', array_slice($m['target_vocab_used'], 0, 3)).'.';
        }
        if ($corrections) {
            $first = $corrections[0];
            $parts[] = 'One thing to polish: “'.$first['original'].'” → “'.$first['suggestion'].'”.';
        }

        return implode(' ', $parts);
    }

    private function followUp(string $text, array $ctx, ?array $pron): string
    {
        if ($pron) {
            return 'Can you use one of these words in your own sentence?';
        }
        if (! empty($ctx['follow_up'])) {
            return (string) $ctx['follow_up'];
        }
        $generic = [
            'interview' => ['Can you give a specific example of that?', 'What did you learn from that experience?', 'Why does that matter for this job?'],
            'everyday' => ['What would you say if they couldn’t help you?', 'How would you say that more politely?', 'What would you ask next?'],
            'conversation' => ['Why do you think so?', 'Can you tell me a little more about that?', 'How did that make you feel?'],
            'speaking_prompt' => ['Can you add one more reason?', 'What is the opposite point of view?', 'How would you summarise that in one sentence?'],
        ];
        $list = $generic[$ctx['type'] ?? 'conversation'] ?? $generic['conversation'];

        return $list[crc32($text) % count($list)];
    }

    // ------------------------------------------------------------------ Helpers

    private function clean(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $text) ?? '';

        return trim(preg_replace('/[ \t]+/u', ' ', $text) ?? '');
    }

    public function words(string $text): array
    {
        preg_match_all("/[A-Za-z]+(?:['’][A-Za-z]+)?/u", $text, $m);

        return $m[0];
    }

    private function sentences(string $text, bool $keepPunctuation = false): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== '' && preg_match('/[A-Za-z]/', $s)));

        return $keepPunctuation ? $parts : array_map(fn ($s) => rtrim($s, '.!?'), $parts);
    }

    private function previousWord(string $text, int $offset): string
    {
        $before = substr($text, 0, $offset);
        preg_match("/([A-Za-z']+)\s*$/", $before, $m);

        return $m[1] ?? '';
    }

    private function thirdPerson(string $verb): string
    {
        $v = strtolower($verb);
        $irregular = ['have' => 'has', 'do' => 'does', 'go' => 'goes', 'study' => 'studies', 'try' => 'tries', 'teach' => 'teaches', 'watch' => 'watches'];

        return $irregular[$v] ?? $v.'s';
    }

    private function baseForm(string $verb): string
    {
        $v = strtolower($verb);
        $irregular = ['has' => 'have', 'does' => 'do', 'goes' => 'go', 'studies' => 'study', 'tries' => 'try', 'teaches' => 'teach', 'watches' => 'watch', 'is' => 'be'];
        if (isset($irregular[$v])) {
            return $irregular[$v];
        }

        return (strlen($v) > 3 && str_ends_with($v, 's') && ! str_ends_with($v, 'ss')) ? substr($v, 0, -1) : $v;
    }
}
