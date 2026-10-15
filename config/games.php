<?php

/*
| Game registry read by the game hub (/games).
| `arcade` = solo games owned by the arcade module. Only games that fully work are listed.
| Each entry: key, title, description, icon (Bootstrap Icons class), route (route name, no params),
| players, plus optional display hints: tone (pk-icon colour), minutes, category.
*/
return [
    'arcade' => [
        ['key' => 'memory', 'title' => 'Memory Match', 'description' => 'Flip cards and match all 8 pairs in as few moves as possible.', 'icon' => 'bi-grid-3x3-gap', 'route' => 'arcade.play.memory', 'players' => '1 player', 'tone' => 'violet', 'minutes' => 2, 'category' => 'Brain'],
        ['key' => 'word', 'title' => 'Word Challenge', 'description' => 'Unscramble Nepal-themed English words against the clock.', 'icon' => 'bi-fonts', 'route' => 'arcade.play.word', 'players' => '1 player', 'tone' => 'blue', 'minutes' => 3, 'category' => 'Words'],
        ['key' => 'quiz', 'title' => 'Nepal GK Quiz', 'description' => 'Eight general-knowledge questions about Nepal, with explanations.', 'icon' => 'bi-patch-question', 'route' => 'arcade.play.quiz', 'players' => '1 player', 'tone' => 'amber', 'minutes' => 3, 'category' => 'Quiz'],
        ['key' => 'cyber', 'title' => 'Cyber Safety Challenge', 'description' => 'Phishing or safe? Spot the scam in real-looking messages.', 'icon' => 'bi-shield-lock', 'route' => 'arcade.play.cyber', 'players' => '1 player', 'tone' => 'rose', 'minutes' => 3, 'category' => 'Quiz'],
        ['key' => 'logic', 'title' => 'Logic Puzzles', 'description' => 'Number sequences and patterns: find what comes next.', 'icon' => 'bi-diagram-3', 'route' => 'arcade.play.logic', 'players' => '1 player', 'tone' => 'green', 'minutes' => 4, 'category' => 'Brain'],
        ['key' => 'reaction', 'title' => 'Reaction Challenge', 'description' => 'Tap the moment the panel turns green. Five rounds.', 'icon' => 'bi-lightning-charge', 'route' => 'arcade.play.reaction', 'players' => '1 player', 'tone' => 'amber', 'minutes' => 1, 'category' => 'Reflex'],
        ['key' => 'sudoku', 'title' => 'Sudoku', 'description' => 'Classic 9×9 number puzzle in easy or medium.', 'icon' => 'bi-grid-3x3', 'route' => 'arcade.play.sudoku', 'players' => '1 player', 'tone' => 'blue', 'minutes' => 10, 'category' => 'Puzzle'],
        ['key' => 'uno', 'title' => 'UNO', 'description' => 'Full-rules UNO against 1–3 computer players.', 'icon' => 'bi-suit-diamond', 'route' => 'arcade.play.uno', 'players' => '1 vs 1–3 CPU', 'tone' => 'rose', 'minutes' => 6, 'category' => 'Cards'],
    ],
];
