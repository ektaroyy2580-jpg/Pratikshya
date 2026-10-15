<?php

namespace App\Services\Games;

use App\Models\GameQuestion;

/**
 * Local, curated question bank (works with AI switched off).
 * Format per item: [category, difficulty, question, [4 options], answer index, explanation].
 */
class QuestionBank
{
    public const ITEMS = [
        // ---------------- Nepal GK ----------------
        ['nepal_gk', 'easy', 'What is the capital city of Nepal?', ['Pokhara', 'Kathmandu', 'Lalitpur', 'Biratnagar'], 1, 'Kathmandu is the capital and largest city of Nepal, located in the Kathmandu Valley.'],
        ['nepal_gk', 'easy', 'What is the national flower of Nepal?', ['Lotus', 'Marigold', 'Rhododendron (Lali Gurans)', 'Jasmine'], 2, 'The rhododendron, known locally as Lali Gurans, is Nepal\'s national flower and blooms across the hills in spring.'],
        ['nepal_gk', 'easy', 'Which bird is the national bird of Nepal?', ['Danphe (Himalayan monal)', 'Peacock', 'Kalij pheasant', 'Sarus crane'], 0, 'The Danphe, or Himalayan monal, is Nepal\'s national bird, known for its iridescent feathers.'],
        ['nepal_gk', 'easy', 'How many provinces does Nepal have under the 2015 constitution?', ['5', '6', '7', '8'], 2, 'The Constitution of Nepal (2015) created seven provinces.'],
        ['nepal_gk', 'easy', 'What is the official currency of Nepal?', ['Indian Rupee', 'Nepalese Rupee', 'Taka', 'Ngultrum'], 1, 'Nepal uses the Nepalese Rupee (NPR), issued by Nepal Rastra Bank.'],
        ['nepal_gk', 'medium', 'What is the officially announced height of Mount Everest (Sagarmatha) since 2020?', ['8,848.86 m', '8,611 m', '8,586 m', '8,850.12 m'], 0, 'Nepal and China jointly announced the height of 8,848.86 m in December 2020.'],
        ['nepal_gk', 'medium', 'What makes the national flag of Nepal unique in the world?', ['It has three colours', 'It is the only non-rectangular national flag', 'It has no symbols', 'It is square'], 1, 'Nepal\'s flag is made of two stacked pennants; it is the only national flag that is not a rectangle or square.'],
        ['nepal_gk', 'medium', 'Lumbini, the birthplace of Gautama Buddha, lies in which province?', ['Gandaki', 'Madhesh', 'Koshi', 'Lumbini'], 3, 'Lumbini is in Rupandehi district, Lumbini Province, and is a UNESCO World Heritage Site.'],
        ['nepal_gk', 'medium', 'Pashupatinath Temple stands on the bank of which river?', ['Bagmati', 'Bishnumati', 'Koshi', 'Trishuli'], 0, 'Pashupatinath Temple is on the banks of the Bagmati River in Kathmandu.'],
        ['nepal_gk', 'medium', 'Which is the longest river flowing within Nepal?', ['Koshi', 'Gandaki', 'Karnali', 'Mahakali'], 2, 'The Karnali is generally recognised as the longest river in Nepal.'],
        ['nepal_gk', 'medium', 'Which is the largest lake in Nepal?', ['Phewa Lake', 'Rara Lake', 'Tilicho Lake', 'Begnas Lake'], 1, 'Rara Lake in Mugu district is the largest lake in Nepal.'],
        ['nepal_gk', 'hard', 'Which was the first national park established in Nepal (1973)?', ['Sagarmatha National Park', 'Bardiya National Park', 'Langtang National Park', 'Chitwan National Park'], 3, 'Chitwan National Park was established in 1973 as Nepal\'s first national park.'],
        ['nepal_gk', 'hard', 'In which year was the current Constitution of Nepal promulgated?', ['2008', '2012', '2015', '2018'], 2, 'The Constitution of Nepal was promulgated on 20 September 2015 (2072 BS).'],

        // ---------------- General knowledge ----------------
        ['general', 'easy', 'Which planet is known as the Red Planet?', ['Venus', 'Mars', 'Jupiter', 'Mercury'], 1, 'Mars looks red because of iron oxide (rust) on its surface.'],
        ['general', 'easy', 'Which is the largest ocean on Earth?', ['Atlantic', 'Indian', 'Arctic', 'Pacific'], 3, 'The Pacific Ocean is the largest and deepest ocean.'],
        ['general', 'easy', 'At sea level, water boils at what temperature?', ['90 °C', '100 °C', '110 °C', '120 °C'], 1, 'At standard sea-level pressure, water boils at 100 °C. At high altitude, like in the Himalaya, it boils at a lower temperature.'],
        ['general', 'easy', 'Which gas do plants absorb from the air for photosynthesis?', ['Oxygen', 'Nitrogen', 'Carbon dioxide', 'Hydrogen'], 2, 'Plants take in carbon dioxide and, using sunlight, turn it into sugars while releasing oxygen.'],
        ['general', 'easy', 'Which is the largest planet in our solar system?', ['Saturn', 'Jupiter', 'Neptune', 'Earth'], 1, 'Jupiter is the largest planet; more than 1,300 Earths could fit inside it.'],
        ['general', 'easy', 'How many continents are there on Earth (common model)?', ['5', '6', '7', '8'], 2, 'The most widely taught model has seven continents.'],
        ['general', 'medium', 'What is the chemical symbol for gold?', ['Go', 'Gd', 'Au', 'Ag'], 2, 'Au comes from the Latin word "aurum". Ag is silver.'],
        ['general', 'medium', 'How many bones are in a typical adult human body?', ['186', '206', '226', '256'], 1, 'An adult has about 206 bones; babies are born with more, which fuse over time.'],
        ['general', 'medium', 'Which is the hardest natural substance?', ['Quartz', 'Iron', 'Granite', 'Diamond'], 3, 'Diamond, a form of carbon, is the hardest naturally occurring material.'],
        ['general', 'medium', 'Roughly how fast does light travel in a vacuum?', ['3,000 km/s', '30,000 km/s', '300,000 km/s', '3,000,000 km/s'], 2, 'Light travels at about 299,792 km per second, usually rounded to 300,000 km/s.'],
        ['general', 'hard', 'Who proposed the theory of general relativity?', ['Isaac Newton', 'Albert Einstein', 'Niels Bohr', 'Galileo Galilei'], 1, 'Albert Einstein published the theory of general relativity in 1915.'],
        ['general', 'hard', 'Which organ in the human body produces insulin?', ['Liver', 'Kidney', 'Pancreas', 'Stomach'], 2, 'Insulin is produced by beta cells in the pancreas and helps regulate blood sugar.'],

        // ---------------- Cybersecurity ----------------
        ['cybersecurity', 'easy', 'What is a phishing message trying to do?', ['Speed up your phone', 'Trick you into sharing passwords or money', 'Update your apps', 'Back up your photos'], 1, 'Phishing messages pretend to be trusted senders so you reveal credentials, OTPs or payment details.'],
        ['cybersecurity', 'easy', 'Someone calls saying they are from your bank and asks for the OTP you just received. What should you do?', ['Share it quickly', 'Share only half of it', 'Never share it; call the bank on its official number', 'Text it instead'], 2, 'Banks never ask for your OTP. Hang up and contact the bank using the number on its official website or card.'],
        ['cybersecurity', 'easy', 'Which of these is the strongest password?', ['password123', 'Kathmandu', 'river-mango-lamp-42-cloud', '12345678'], 2, 'Long passphrases made of unrelated words are much harder to guess or crack than short or common passwords.'],
        ['cybersecurity', 'easy', 'Why should you install software updates promptly?', ['They change the wallpaper', 'They often fix security weaknesses', 'They make the battery bigger', 'They remove all viruses forever'], 1, 'Updates frequently patch known vulnerabilities that attackers actively exploit.'],
        ['cybersecurity', 'easy', 'What does two-factor authentication (2FA) add to your login?', ['A second password hint', 'A second proof such as a code or app approval', 'A faster login', 'A backup email'], 1, '2FA requires something extra, like a one-time code, so a stolen password alone is not enough.'],
        ['cybersecurity', 'medium', 'What does the padlock (HTTPS) in a browser address bar guarantee?', ['The website is honest', 'The connection to the site is encrypted', 'The site has no malware', 'The site is government-approved'], 1, 'HTTPS only means traffic is encrypted. Scam sites can also use HTTPS, so still check the domain.'],
        ['cybersecurity', 'medium', 'What does ransomware do?', ['Shows advertisements', 'Encrypts your files and demands payment', 'Speeds up your computer', 'Deletes spam'], 1, 'Ransomware locks or encrypts data and demands a ransom. Regular offline backups are the best defence.'],
        ['cybersecurity', 'medium', 'You find a USB drive in a parking area. What is the safest action?', ['Plug it in to find the owner', 'Plug it into a work computer', 'Do not plug it in; hand it to security or IT', 'Format it and keep it'], 2, 'Dropped USB drives are a known attack method. Unknown drives can run malicious code when connected.'],
        ['cybersecurity', 'medium', 'What is "shoulder surfing"?', ['Watching someone\'s screen or PIN entry to steal information', 'Browsing on a beach', 'A type of Wi-Fi', 'Sharing a hotspot'], 0, 'Shoulder surfing is spying on screens or keypads in public places. Shield your PIN, especially in queues.'],
        ['cybersecurity', 'hard', 'A link points to "paypa1-secure-login.com". This is an example of what?', ['A verified domain', 'A lookalike (typosquatting) domain', 'A government site', 'A secure redirect'], 1, 'Replacing letters with similar characters (l → 1) is a common trick to imitate real brands.'],
        ['cybersecurity', 'hard', 'What is the "principle of least privilege"?', ['Give everyone admin access', 'Give users only the access they need for their job', 'Never use passwords', 'Share accounts within a team'], 1, 'Limiting access reduces the damage if an account is compromised.'],

        // ---------------- Logic ----------------
        ['logic', 'easy', 'What comes next: 2, 4, 8, 16, ?', ['18', '24', '32', '64'], 2, 'Each number doubles the previous one: 16 × 2 = 32.'],
        ['logic', 'easy', 'What comes next: 1, 1, 2, 3, 5, 8, ?', ['11', '12', '13', '15'], 2, 'This is the Fibonacci sequence: each number is the sum of the two before it (5 + 8 = 13).'],
        ['logic', 'easy', 'Which is the odd one out?', ['Apple', 'Banana', 'Carrot', 'Mango'], 2, 'Carrot is a vegetable (a root); the others are fruits.'],
        ['logic', 'easy', 'If today is Sunday, what day will it be 10 days from now?', ['Tuesday', 'Wednesday', 'Thursday', 'Friday'], 1, '7 days later is Sunday again; 3 more days is Wednesday.'],
        ['logic', 'easy', 'What is the smallest prime number?', ['0', '1', '2', '3'], 2, '2 is the smallest prime and the only even prime number.'],
        ['logic', 'medium', 'You are 7th from the front and 5th from the back of a queue. How many people are in the queue?', ['11', '12', '13', '10'], 0, '6 people ahead + you + 4 behind = 11.'],
        ['logic', 'medium', 'Sita\'s father has five daughters: Asha, Bina, Chandra and Deepa. What is the fifth daughter\'s name?', ['Esha', 'Sita', 'Gita', 'Not enough information'], 1, 'The question says "Sita\'s father", so Sita is the fifth daughter.'],
        ['logic', 'medium', 'All bloops are razzies and all razzies are lazzies. Which must be true?', ['All lazzies are bloops', 'All bloops are lazzies', 'No bloops are lazzies', 'Some razzies are not bloops'], 1, 'If every bloop is a razzie and every razzie is a lazzie, every bloop must be a lazzie.'],
        ['logic', 'medium', 'What comes next: 3, 6, 11, 18, 27, ?', ['36', '38', '40', '42'], 1, 'The gaps grow by 2 (3, 5, 7, 9, 11): 27 + 11 = 38.'],
        ['logic', 'hard', 'A bat and a ball cost Rs 110 in total. The bat costs Rs 100 more than the ball. How much is the ball?', ['Rs 5', 'Rs 10', 'Rs 15', 'Rs 1'], 0, 'Ball = x, bat = x + 100, so 2x + 100 = 110 and x = 5.'],
        ['logic', 'hard', 'If 5 machines make 5 widgets in 5 minutes, how long do 100 machines take to make 100 widgets?', ['100 minutes', '20 minutes', '5 minutes', '1 minute'], 2, 'Each machine makes one widget in 5 minutes, so 100 machines make 100 widgets in the same 5 minutes.'],
        ['logic', 'hard', 'What is the angle between the hour and minute hands at 3:15?', ['0°', '7.5°', '15°', '22.5°'], 1, 'The minute hand is at 90°. The hour hand has moved a quarter of the way from 3, to 97.5°. The difference is 7.5°.'],
    ];

    /** Insert/refresh the curated bank. Idempotent (keyed on the normalised question hash). */
    public static function seed(): int
    {
        $n = 0;
        foreach (self::ITEMS as [$cat, $diff, $q, $opts, $ans, $exp]) {
            GameQuestion::updateOrCreate(['hash' => GameQuestion::hashFor($q)], [
                'question' => $q, 'options' => $opts, 'answer_index' => $ans, 'explanation' => $exp,
                'category' => $cat, 'difficulty' => $diff, 'source' => 'bank',
            ]);
            $n++;
        }

        return $n;
    }

    /** Make sure enough approved questions exist (e.g. if the seeder was never run). */
    public static function ensureSeeded(int $needed = 5): void
    {
        if (GameQuestion::approved()->count() < $needed) {
            self::seed();
        }
    }
}
