<?php

namespace Database\Seeders\Modules;

use App\Models\EnglishScenario;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * English practice scenarios (demo content, Nepal context, all fictional).
 * Idempotent: updateOrCreate on slug. Safe to re-run.
 */
class EnglishSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->scenarios() as $i => $s) {
            EnglishScenario::updateOrCreate(['slug' => $s['slug']], $s + ['sort' => ($i + 1) * 10, 'is_active' => true]);
        }

        // Demo learner (fictional) — documented in the integration note.
        User::firstOrCreate(
            ['email' => 'english.demo@waitwise.np'],
            ['name' => 'Asha Demo (English)', 'password' => 'Demo@1234']
        );
    }

    private function scenarios(): array
    {
        $p = fn (string $prompt, ?string $hint = null, ?string $follow = null) => array_filter(['prompt' => $prompt, 'hint' => $hint, 'follow_up' => $follow]);
        $d = fn (string $target, string $hint) => ['prompt' => 'Say this phrase clearly: “'.$target.'”', 'target' => $target, 'hint' => $hint];

        return [
            [
                'slug' => 'interview-bank-officer', 'type' => 'interview', 'level' => 'intermediate', 'icon' => 'bi-bank',
                'title' => 'Job interview: bank officer',
                'description' => 'Practise a first-round interview for a junior officer role at a (fictional) commercial bank in Kathmandu.',
                'target_vocabulary' => ['experience', 'customer', 'responsible', 'accurate', 'teamwork', 'deadline', 'improve'],
                'turns' => [
                    $p('Good morning, please have a seat. Could you briefly introduce yourself?', 'Name, education, one strength. 3–4 sentences.', 'What made you choose banking as a career?'),
                    $p('Why do you want to work at our bank?', 'Mention customers, growth, or the bank’s reputation.'),
                    $p('Tell me about a time you dealt with a difficult customer.', 'Situation → what you did → result.'),
                    $p('How do you make sure your work is accurate when you handle cash or forms?', 'Use: accurate, double-check, careful.'),
                    $p('Describe a time you worked in a team to meet a deadline.', 'Use: teamwork, deadline.'),
                    $p('What is one weakness you are working to improve?', 'Be honest and show what you are doing about it.'),
                    $p('Where do you see yourself in five years?', 'Use “I would like to …” or “I hope to …”.'),
                    $p('Do you have any questions for us?', 'Ask about training, the team, or next steps.'),
                ],
            ],
            [
                'slug' => 'interview-it-developer', 'type' => 'interview', 'level' => 'advanced', 'icon' => 'bi-laptop',
                'title' => 'Job interview: software developer',
                'description' => 'A friendly technical-HR interview for a junior developer at an IT company in Lalitpur.',
                'target_vocabulary' => ['project', 'framework', 'debug', 'collaborate', 'deadline', 'learn', 'solution'],
                'turns' => [
                    $p('Thanks for coming in. Tell me a little about yourself and your background in IT.', 'Degree, a project, what you enjoy building.'),
                    $p('Describe a project you are proud of. What was your role?', 'Use: project, framework, role.'),
                    $p('Tell me about a bug that was hard to fix. How did you debug it?', 'Explain step by step: first, then, finally.'),
                    $p('How do you keep learning new technologies?', 'Courses, docs, side projects, communities.'),
                    $p('How do you collaborate with designers or testers?', 'Use: collaborate, feedback, communicate.'),
                    $p('What would you do if you could not finish a task before the deadline?', 'Talk early, explain, propose a solution.'),
                    $p('Why do you want to join our company rather than work abroad?', 'Growth in Nepal, team, products.'),
                ],
            ],
            [
                'slug' => 'interview-ngo-officer', 'type' => 'interview', 'level' => 'intermediate', 'icon' => 'bi-people',
                'title' => 'Job interview: NGO programme officer',
                'description' => 'Interview for a community programme role with a (fictional) development NGO working in rural Nepal.',
                'target_vocabulary' => ['community', 'programme', 'report', 'stakeholder', 'impact', 'volunteer', 'organise'],
                'turns' => [
                    $p('Welcome. Please introduce yourself and tell us why community work interests you.'),
                    $p('Tell us about any volunteer or community experience you have.', 'Use: volunteer, community, organise.'),
                    $p('How would you explain our programme to people in a village who have never heard of it?', 'Simple words, respect, local examples.'),
                    $p('How would you measure the impact of a training programme?', 'Use: impact, report, data.'),
                    $p('Describe how you would handle a disagreement with a local stakeholder.', 'Listen, understand, find common ground.'),
                    $p('Are you comfortable travelling to remote districts? Why?'),
                ],
            ],
            [
                'slug' => 'cafe-ordering', 'type' => 'everyday', 'level' => 'beginner', 'icon' => 'bi-cup-hot',
                'title' => 'Ordering at a café',
                'description' => 'Order drinks and snacks politely at a busy café in Thamel.',
                'target_vocabulary' => ['could', 'please', 'would', 'without', 'takeaway', 'bill'],
                'turns' => [
                    $p('Hi there! What can I get for you today?', 'Use “Could I have …, please?”'),
                    $p('Would you like that hot or iced? And what size?', 'Answer both questions.'),
                    $p('Anything to eat with that? We have momo, sandwiches and banana cake.'),
                    $p('Is that for here or takeaway?', 'Use: for here / takeaway.'),
                    $p('Sorry, we are out of banana cake. Would you like something else?', 'Stay polite and choose another item.'),
                    $p('That will be 640 rupees. How would you like to pay?', 'Cash, card or QR wallet.'),
                ],
            ],
            [
                'slug' => 'doctor-opd', 'type' => 'everyday', 'level' => 'intermediate', 'icon' => 'bi-heart-pulse',
                'title' => 'Talking to a doctor at OPD',
                'description' => 'Describe symptoms clearly to a doctor in a hospital outpatient department (demo, not medical advice).',
                'target_vocabulary' => ['symptom', 'pain', 'since', 'medicine', 'allergic', 'fever', 'prescription'],
                'turns' => [
                    $p('Namaste, please sit down. What brings you to the OPD today?', 'Say the main problem first.'),
                    $p('How long have you had these symptoms?', 'Use “for three days” or “since Monday”.'),
                    $p('Where exactly is the pain, and how bad is it from one to ten?'),
                    $p('Are you taking any medicine at the moment?'),
                    $p('Are you allergic to any medicines?', 'Use: allergic to …'),
                    $p('I will write a prescription. Do you have any questions about it?', 'Ask how often, before or after food.'),
                ],
            ],
            [
                'slug' => 'directions-kathmandu', 'type' => 'everyday', 'level' => 'beginner', 'icon' => 'bi-signpost-split',
                'title' => 'Asking directions in Kathmandu',
                'description' => 'Ask for and confirm directions — from Ratna Park to a government office.',
                'target_vocabulary' => ['excuse', 'straight', 'left', 'right', 'opposite', 'near', 'far'],
                'turns' => [
                    $p('You look lost. Can I help you?', 'Start with “Excuse me, …”'),
                    $p('The office is near the Bhadrakali temple. Do you know where that is?'),
                    $p('Go straight past the bus stop, then turn left. Can you repeat that back to me?', 'Repeat the directions to confirm.'),
                    $p('Are you walking or taking a micro bus?'),
                    $p('It is opposite a big bank. What else would you like to ask?', 'Ask about distance or time.'),
                ],
            ],
            [
                'slug' => 'phone-call-office', 'type' => 'everyday', 'level' => 'intermediate', 'icon' => 'bi-telephone',
                'title' => 'Phone call to an office',
                'description' => 'Call a municipal office to ask about a document and leave a clear message.',
                'target_vocabulary' => ['calling', 'regarding', 'appointment', 'available', 'message', 'reference'],
                'turns' => [
                    $p('Good afternoon, Ward Office number 4. How can I help you?', 'Use “I’m calling regarding …”'),
                    $p('Do you have a reference number for your application?', 'Spell or say numbers clearly.'),
                    $p('The officer is not available right now. Would you like to leave a message?'),
                    $p('What is the best time to call you back?'),
                    $p('Is there anything else I can help you with?', 'Close the call politely.'),
                ],
            ],
            [
                'slug' => 'small-talk-queue', 'type' => 'conversation', 'level' => 'beginner', 'icon' => 'bi-chat-heart',
                'title' => 'Small talk while waiting',
                'description' => 'Friendly, light conversation with someone in the queue next to you.',
                'target_vocabulary' => ['weather', 'weekend', 'actually', 'favourite', 'recommend', 'usually'],
                'turns' => [
                    $p('Long queue today, isn’t it? Have you been waiting long?'),
                    $p('Is this your first time at this office?'),
                    $p('What do you usually do at the weekend?', 'Use: usually, sometimes, often.'),
                    $p('Do you know any good places to eat around here?', 'Use: recommend.'),
                    $p('What kind of music or films do you like?', 'Use: favourite.'),
                    $p('The weather has been strange lately, don’t you think?'),
                ],
            ],
            [
                'slug' => 'presentation-practice', 'type' => 'speaking_prompt', 'level' => 'advanced', 'icon' => 'bi-easel',
                'title' => 'Presentation practice',
                'description' => 'Practise opening, structuring and closing a short work or class presentation.',
                'target_vocabulary' => ['firstly', 'moreover', 'however', 'for example', 'in conclusion', 'audience'],
                'turns' => [
                    $p('Open a presentation about a local business idea. Greet the audience and state your topic.', 'Good morning everyone. Today I’m going to talk about …'),
                    $p('Explain the problem your idea solves.', 'Use: firstly, for example.'),
                    $p('Give two benefits of your idea.', 'Use: moreover, in addition.'),
                    $p('Mention one risk and how you would handle it.', 'Use: however.'),
                    $p('Close your presentation and invite questions.', 'Use: in conclusion.'),
                ],
            ],
            [
                'slug' => 'describe-picture', 'type' => 'speaking_prompt', 'level' => 'intermediate', 'icon' => 'bi-image',
                'title' => 'Describe a picture (text scene)',
                'description' => 'A scene is described in words — describe what you imagine using location language.',
                'target_vocabulary' => ['foreground', 'background', 'on the left', 'on the right', 'seems', 'probably'],
                'turns' => [
                    $p('Picture this: a busy Asan market in the morning, with vegetable sellers, a temple and motorbikes. Describe the scene.', 'Use: in the foreground, in the background.'),
                    $p('What do you think the people in the scene are doing?', 'Use: seems, probably.'),
                    $p('How do you think the place sounds and smells?'),
                    $p('Would you like to visit this place? Why or why not?'),
                    $p('Now describe the place where you are waiting right now.', 'Use: on the left, on the right.'),
                ],
            ],
            [
                'slug' => 'opinion-prompt', 'type' => 'speaking_prompt', 'level' => 'intermediate', 'icon' => 'bi-lightbulb',
                'title' => 'Give your opinion',
                'description' => 'Speak for 30–60 seconds on everyday topics. Give reasons and examples.',
                'target_vocabulary' => ['in my opinion', 'because', 'for example', 'on the other hand', 'overall'],
                'turns' => [
                    $p('Should government offices in Nepal move all services online? Give your opinion.', 'Use: in my opinion, because.'),
                    $p('Is it better to study abroad or in Nepal?', 'Use: on the other hand.'),
                    $p('What is the best way to learn a new language?', 'Use: for example.'),
                    $p('Should mobile phones be allowed in schools?'),
                    $p('What would make waiting in queues less stressful?', 'Use: overall.'),
                ],
            ],
            [
                'slug' => 'pron-th-sounds', 'type' => 'pronunciation', 'level' => 'beginner', 'icon' => 'bi-soundwave',
                'title' => 'Pronunciation drill: th sounds',
                'description' => 'Practise “th” (think / this). The browser shows what it heard — a transcript match, not a pronunciation score.',
                'target_vocabulary' => ['think', 'three', 'this', 'that', 'thank'],
                'turns' => [
                    $d('Thank you for the three tickets.', 'Tongue lightly between the teeth for “th”.'),
                    $d('I think this is the right place.', '“think” (no voice) vs “this” (with voice).'),
                    $d('That theatre is on Thursday.', 'Do not change “th” to “t” or “d”.'),
                    $d('Both brothers thought about it.', 'Slow down at the end of “both”.'),
                    $d('There are thirty things on the list.', '“There” and “thirty” start differently.'),
                ],
            ],
            [
                'slug' => 'pron-v-w', 'type' => 'pronunciation', 'level' => 'beginner', 'icon' => 'bi-soundwave',
                'title' => 'Pronunciation drill: v and w',
                'description' => 'Separate “v” (teeth on lip) and “w” (round lips). Transcript match only — not a score.',
                'target_vocabulary' => ['very', 'west', 'village', 'window', 'van'],
                'turns' => [
                    $d('We visited a very windy village.', 'v: teeth on lip · w: round lips.'),
                    $d('The van is waiting by the window.', 'Feel the buzz on “van”.'),
                    $d('Vegetables are sold on the west side.', '“vegetables” starts with v.'),
                    $d('Will you wear the white vest?', 'Lips round for every w.'),
                    $d('Twelve voters waited for a while.', 'Mix of v and w — go slowly.'),
                ],
            ],
            [
                'slug' => 'pron-minimal-pairs', 'type' => 'pronunciation', 'level' => 'intermediate', 'icon' => 'bi-soundwave',
                'title' => 'Minimal pairs: ship / sheep, bed / bad',
                'description' => 'Words that differ by one sound. See whether the browser recognises the word you meant.',
                'target_vocabulary' => ['ship', 'sheep', 'bed', 'bad', 'full', 'fool'],
                'turns' => [
                    $d('The ship carried a sheep.', 'Short “i” in ship, long “ee” in sheep.'),
                    $d('This bed is not bad.', 'Open your mouth wider for “bad”.'),
                    $d('The cup is full, you fool.', 'Short “u” vs long “oo”.'),
                    $d('Please sit in this seat.', 'sit (short) · seat (long).'),
                    $d('I live here and I leave at five.', 'live (short) · leave (long).'),
                ],
            ],
        ];
    }
}
