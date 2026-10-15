<?php

/*
 * ORIGINAL comics for the Pratikshya demo library. Every panel is drawn at runtime as inline SVG
 * by App\Services\Library\ComicArt from this spec (no images, no third-party characters).
 *
 * page   = ['style' => 'color'|'manga', 'panels' => [panel, ...]]
 * panel  = ['scene', 'tone'?, 'span'? (1|2), 'figures' => [[who, x%, mood, scale?, flip?]], 'props' => [[kind, x%, y%?, scale?, label?]],
 *           'lines'? ('left'|'right'), 'caption'?, 'bubbles' => [[text, x%, y%, w%, tail (bl|br|tl|tr|none), kind (speech|thought|shout|sfx)]],
 *           'alt' => text description for screen readers]
 */

$f = fn (string $who, float $x, string $mood = 'happy', float $scale = 1, bool $flip = false) => ['who' => $who, 'x' => $x, 'mood' => $mood, 'scale' => $scale, 'flip' => $flip];
$p = fn (string $kind, float $x, ?float $y = null, float $scale = 1, string $label = '') => array_filter(['kind' => $kind, 'x' => $x, 'y' => $y, 'scale' => $scale, 'label' => $label], fn ($v) => $v !== null && $v !== '');
$b = fn (string $text, float $x, float $y, float $w = 44, string $tail = 'bl', string $kind = 'speech') => compact('text', 'x', 'y', 'w', 'tail', 'kind');

return [
    // ---------------------------------------------------------------- 1. Children's comic (left-to-right)
    [
        'slug' => 'momo-and-the-missing-token',
        'title' => 'Momo and the Missing Token',
        'author' => 'Pratikshya Studio (original comic)',
        'type' => 'comic',
        'category' => 'childrens',
        'language' => 'en',
        'reading_direction' => 'ltr',
        'description' => 'A brave little dumpling named Momo must chase a sneaky cat through Asan Bazaar to win back Hajur-aama\'s queue token before their number is called.',
        'license' => 'Original comic © Pratikshya demo library. Characters and art drawn in SVG for this app.',
        'featured' => true,
        'cover' => ['palette' => 'sunset', 'motif' => 'panels'],
        'chapters' => [
            ['title' => 'Market Day', 'pages' => [
                ['style' => 'color', 'panels' => [
                    ['scene' => 'market', 'span' => 2, 'caption' => 'Asan Bazaar, early in the morning.',
                        'figures' => [$f('momo', 32), $f('grandma', 62)],
                        'bubbles' => [$b('Momo, hold our token tightly. We are number A-7!', 54, 6, 30, 'bl'), $b('I\'ll guard it like treasure, Hajur-aama!', 12, 20, 26, 'br')],
                        'alt' => 'Momo the dumpling and Grandmother stand among colourful market stalls.'],
                    ['scene' => 'plain', 'tone' => '#ffe08a', 'figures' => [$f('momo', 28)], 'props' => [$p('token', 68, 62, 2.2, 'A-7')],
                        'bubbles' => [$b('A-7… A for Amazing, 7 for… seven momos!', 40, 6, 54, 'bl')],
                        'alt' => 'Momo admires a large paper token marked A-7.'],
                    ['scene' => 'market', 'lines' => 'left', 'figures' => [$f('momo', 30, 'surprised'), $f('cat', 78, 'happy')], 'props' => [$p('token', 86, 60, 1, 'A-7')],
                        'bubbles' => [$b('WHOOSH!', 50, 8, 34, 'none', 'sfx'), $b('Hey! My token!', 4, 8, 38, 'bl', 'shout')],
                        'alt' => 'An orange cat snatches the token and runs; Momo is shocked.'],
                ]],
                ['style' => 'color', 'panels' => [
                    ['scene' => 'market', 'caption' => 'A sneaky cat dashes off with the shiny paper.', 'figures' => [$f('cat', 60)], 'props' => [$p('token', 70, 66, 1, 'A-7')],
                        'bubbles' => [$b('Mew! (Shiny paper is mine.)', 40, 22, 46, 'bl', 'thought')],
                        'alt' => 'The cat looks pleased with the token beside it.'],
                    ['scene' => 'street', 'lines' => 'left', 'figures' => [$f('momo', 50, 'determined', 1.2)],
                        'bubbles' => [$b('Wait! We need that!', 8, 8, 52, 'br', 'shout')],
                        'alt' => 'Momo runs determinedly down a street of brick houses.'],
                    ['scene' => 'street', 'span' => 2, 'figures' => [$f('momo', 22, 'worried'), $f('girl', 58, 'surprised'), $f('kid', 78)],
                        'bubbles' => [$b('Excuse me! Did you see a cat with a ticket?', 4, 6, 28, 'br'), $b('A cat with a ticket? It ran toward the temple!', 52, 6, 30, 'bl')],
                        'alt' => 'Momo asks two children for help; the girl points the way.'],
                ]],
                ['style' => 'color', 'panels' => [
                    ['scene' => 'temple', 'caption' => 'At the temple steps…', 'figures' => [$f('momo', 24, 'neutral'), $f('cat', 62, 'sleepy')], 'props' => [$p('token', 74, 80, .9, 'A-7')],
                        'bubbles' => [$b('Shh… it\'s sleeping on our number.', 4, 24, 46, 'bl', 'thought')],
                        'alt' => 'The cat naps in front of a pagoda with the token beside it.'],
                    ['scene' => 'plain', 'tone' => '#d9f3ff', 'figures' => [$f('momo', 26), $f('cat', 74, 'surprised')], 'props' => [$p('momo', 50, 78, 1.3)],
                        'bubbles' => [$b('Trade? One fresh momo for one token?', 4, 6, 46, 'br'), $b('Mrrp! Deal!', 60, 14, 32, 'bl')],
                        'alt' => 'Momo offers the cat a plate of dumplings.'],
                    ['scene' => 'burst', 'span' => 2, 'figures' => [$f('momo', 50, 'laugh', 1.2)], 'props' => [$p('token', 66, 34, 1.5, 'A-7')],
                        'bubbles' => [$b('GOT IT!', 12, 14, 26, 'none', 'sfx')],
                        'alt' => 'Momo cheers holding the token high.'],
                ]],
            ]],
            ['title' => 'Counter Seven', 'pages' => [
                ['style' => 'color', 'panels' => [
                    ['scene' => 'office', 'caption' => 'Back at the ward office…', 'figures' => [$f('grandma', 38, 'worried'), $f('momo', 66, 'surprised')],
                        'bubbles' => [$b('Momo! Where were you? They\'re calling A-6!', 44, 6, 52, 'bl')],
                        'alt' => 'Grandmother looks worried in the office; the token board shows numbers.'],
                    ['scene' => 'office', 'figures' => [$f('momo', 44)], 'props' => [$p('token', 66, 62, 1.4, 'A-7')],
                        'bubbles' => [$b('Here! Safe, and only a little bit sticky.', 48, 30, 48, 'bl')],
                        'alt' => 'Momo proudly returns the token.'],
                    ['scene' => 'counter', 'span' => 2, 'figures' => [$f('officer', 50)],
                        'bubbles' => [$b('Number A-7, Counter 7, please!', 60, 30, 30, 'bl', 'shout')],
                        'alt' => 'A friendly officer calls the next number from behind a wooden counter.'],
                ]],
                ['style' => 'color', 'panels' => [
                    ['scene' => 'counter', 'span' => 2, 'figures' => [$f('grandma', 30), $f('momo', 50, 'laugh'), $f('officer', 76)],
                        'bubbles' => [$b('All done. Have a lovely day!', 64, 30, 30, 'bl'), $b('Dhanyabad! Thank you!', 4, 30, 24, 'br')],
                        'alt' => 'Grandmother, Momo and the officer smile at the counter.'],
                    ['scene' => 'temple', 'caption' => 'And the cat?', 'figures' => [$f('momo', 26), $f('cat', 60)], 'props' => [$p('momo', 76, 92, 1)],
                        'bubbles' => [$b('Best. Trade. Ever.', 40, 26, 40, 'bl', 'thought')],
                        'alt' => 'The cat happily eats momos beside Momo.'],
                    ['scene' => 'plain', 'tone' => '#fff3c4', 'props' => [$p('book', 50, 58, 2.6), $p('heart', 30, 30, 1.4), $p('star', 72, 26, 1.6)],
                        'caption' => 'THE END. Keep your token safe — and always share your momos.',
                        'alt' => 'An open book with a heart and a star.'],
                ]],
            ]],
        ],
    ],

    // ---------------------------------------------------------------- 2. Manga-style (right-to-left, black & white)
    [
        'slug' => 'ronin-of-counter-nine',
        'title' => 'Ronin of Counter Nine',
        'author' => 'Pratikshya Studio (original manga-style comic)',
        'type' => 'comic',
        'category' => 'manga',
        'language' => 'en',
        'reading_direction' => 'rtl',
        'description' => 'An original manga-style story, read right to left. In the longest line in Kathmandu, a quiet young man stands up for the one rule that lets strangers trust each other: the number.',
        'license' => 'Original manga-style comic © Pratikshya demo library, drawn in SVG. Inspired by the format only — no existing characters.',
        'featured' => true,
        'cover' => ['palette' => 'ink', 'motif' => 'panels'],
        'chapters' => [
            ['title' => 'The Long Line', 'pages' => [
                ['style' => 'manga', 'panels' => [
                    ['scene' => 'counter', 'span' => 2, 'caption' => 'Kathmandu. Counter Nine. The longest line in the city.',
                        'figures' => [$f('kid', 16, 'sleepy'), $f('dai', 36, 'neutral'), $f('grandma', 58, 'sleepy'), $f('ronin', 80, 'neutral')],
                        'alt' => 'A long line of people waits in front of Counter Nine; the young ronin stands at the back.'],
                    ['scene' => 'burst', 'tone' => '#d0d0d0', 'figures' => [$f('ronin', 50, 'determined', 1.35)],
                        'bubbles' => [$b('Forty-two people ahead. Patience is also a sword.', 4, 4, 48, 'none', 'thought')],
                        'alt' => 'Close-up of the ronin, calm and focused.'],
                    ['scene' => 'office', 'figures' => [$f('dai', 40, 'worried')], 'props' => [$p('clock', 82, 22, 1.3)],
                        'bubbles' => [$b('Eleven o\'clock already…', 54, 34, 42, 'bl')],
                        'alt' => 'A man in a dhaka topi glances at the clock.'],
                ]],
                ['style' => 'manga', 'panels' => [
                    ['scene' => 'street', 'lines' => 'right', 'figures' => [$f('stranger', 40, 'happy', 1, true)],
                        'bubbles' => [$b('SHFF SHFF', 60, 8, 34, 'none', 'sfx'), $b('Excuse me, excuse me — I\'m just in a hurry!', 4, 6, 50, 'br')],
                        'alt' => 'A man in sunglasses hurries past the line.'],
                    ['scene' => 'counter', 'figures' => [$f('grandma', 30, 'surprised'), $f('stranger', 68, 'happy', 1, true)],
                        'bubbles' => [$b('Young man, the line starts back there!', 2, 6, 52, 'br')],
                        'alt' => 'Grandmother objects as the man pushes to the front.'],
                    ['scene' => 'burst', 'span' => 2, 'figures' => [$f('stranger', 50, 'angry', 1.3)],
                        'bubbles' => [$b('Everyone\'s in a hurry, auntie!', 62, 14, 30, 'bl', 'shout')],
                        'alt' => 'The line-cutter shouts back, annoyed.'],
                ]],
                ['style' => 'manga', 'panels' => [
                    ['scene' => 'plain', 'tone' => '#9a9a9a', 'figures' => [$f('ronin', 50, 'determined', 1.5)],
                        'bubbles' => [$b('…', 70, 10, 16, 'none', 'sfx')],
                        'alt' => 'The ronin steps forward silently.'],
                    ['scene' => 'counter', 'figures' => [$f('ronin', 34, 'neutral'), $f('stranger', 72, 'surprised', 1, true)],
                        'bubbles' => [$b('We are all in a hurry. That is why there is a line. Your token number?', 2, 4, 56, 'br')],
                        'alt' => 'The ronin calmly asks the stranger for his number.'],
                    ['scene' => 'plain', 'tone' => '#e0e0e0', 'span' => 2, 'figures' => [$f('stranger', 24, 'sad')], 'props' => [$p('token', 62, 64, 2.4, 'A-88')],
                        'bubbles' => [$b('…Eighty-eight.', 34, 10, 22, 'bl')],
                        'alt' => 'The stranger shows his token: A-88.'],
                ]],
            ]],
            ['title' => 'The Number That Mattered', 'pages' => [
                ['style' => 'manga', 'panels' => [
                    ['scene' => 'counter', 'figures' => [$f('clerk', 52)],
                        'bubbles' => [$b('Number forty-one! Is forty-one here?', 46, 6, 50, 'bl', 'shout')],
                        'alt' => 'The clerk calls number forty-one.'],
                    ['scene' => 'burst', 'tone' => '#d0d0d0', 'figures' => [$f('dai', 50, 'surprised', 1.3)],
                        'bubbles' => [$b('That\'s me! I almost dozed off…', 2, 6, 44, 'br')],
                        'alt' => 'The man in the dhaka topi jumps up.'],
                    ['scene' => 'counter', 'span' => 2, 'caption' => 'The line moved. Slowly. Fairly.',
                        'figures' => [$f('grandma', 22), $f('ronin', 44, 'neutral'), $f('stranger', 80, 'sad', 1, true)],
                        'alt' => 'The line moves forward; the stranger now waits at the back.'],
                ]],
                ['style' => 'manga', 'panels' => [
                    ['scene' => 'plain', 'tone' => '#c8c8c8', 'span' => 2, 'figures' => [$f('stranger', 30, 'worried'), $f('ronin', 72, 'neutral', 1, true)],
                        'bubbles' => [$b('Why did you stop me? You don\'t even know me.', 4, 4, 30, 'br'), $b('Exactly. Nobody here knows each other. The number is how strangers trust strangers.', 56, 4, 40, 'bl')],
                        'alt' => 'The stranger and the ronin talk face to face.'],
                    ['scene' => 'office', 'figures' => [$f('stranger', 30), $f('grandma', 70)], 'props' => [$p('tea', 50, 86, 1.4)],
                        'bubbles' => [$b('Here, have some chiya while you wait, son.', 40, 6, 56, 'br')],
                        'alt' => 'Grandmother offers the stranger a cup of tea; he smiles.'],
                    ['scene' => 'night', 'figures' => [$f('ronin', 50, 'happy')], 'props' => [$p('book', 70, 74, 1)],
                        'caption' => 'Every line is a small promise between strangers.',
                        'bubbles' => [$b('Next time… I\'ll bring a book.', 4, 24, 44, 'br', 'thought')],
                        'alt' => 'The ronin walks home under the moon.'],
                ]],
            ]],
        ],
    ],

    // ---------------------------------------------------------------- 3. Comedy comic (left-to-right)
    [
        'slug' => 'yeti-delivery-co',
        'title' => 'Yeti Delivery Co.',
        'author' => 'Pratikshya Studio (original comic)',
        'type' => 'comic',
        'category' => 'comics',
        'language' => 'en',
        'reading_direction' => 'ltr',
        'description' => 'The fastest courier in the Khumbu is seven feet tall, very fluffy and slightly too interested in your parcel of momos.',
        'license' => 'Original comic © Pratikshya demo library. Characters and art drawn in SVG for this app.',
        'featured' => false,
        'cover' => ['palette' => 'ocean', 'motif' => 'mountain'],
        'chapters' => [
            ['title' => 'Special Delivery', 'pages' => [
                ['style' => 'color', 'panels' => [
                    ['scene' => 'mountain', 'span' => 2, 'caption' => 'High above Namche Bazaar: the fastest delivery service in the Himalaya.',
                        'figures' => [$f('yeti', 40)], 'props' => [$p('box', 58), $p('flags', 76, 30, 1)],
                        'alt' => 'A friendly yeti stands in the snowy mountains next to a parcel.'],
                    ['scene' => 'mountain', 'lines' => 'left', 'figures' => [$f('yeti', 56, 'determined', .9)], 'props' => [$p('box', 72, 62, .8)],
                        'bubbles' => [$b('STOMP STOMP', 4, 10, 46, 'none', 'sfx')],
                        'alt' => 'The yeti runs down the trail with the parcel.'],
                    ['scene' => 'street', 'figures' => [$f('dai', 66, 'surprised')], 'props' => [$p('box', 30)],
                        'bubbles' => [$b('A yeti… with my parcel?!', 2, 8, 48, 'br')],
                        'alt' => 'A man in a dhaka topi stares at the parcel at his door.'],
                ]],
                ['style' => 'color', 'panels' => [
                    ['scene' => 'street', 'span' => 2, 'figures' => [$f('yeti', 34), $f('dai', 70, 'worried')],
                        'bubbles' => [$b('Delivery for Mr. Sherpa! Please sign here.', 2, 4, 30, 'br'), $b('Do you take… a thumbprint?', 70, 8, 28, 'bl')],
                        'alt' => 'The yeti holds out a delivery form; the man hesitates.'],
                    ['scene' => 'plain', 'tone' => '#d9f3ff', 'props' => [$p('box', 50, 82, 2.4)],
                        'bubbles' => [$b('Careful — it says FRAGILE. And also… SNACKS.', 6, 6, 88, 'none')],
                        'alt' => 'A close-up of the parcel.'],
                    ['scene' => 'burst', 'figures' => [$f('dai', 50, 'laugh', 1.2)],
                        'bubbles' => [$b('RIIIP!', 6, 8, 34, 'none', 'sfx')],
                        'alt' => 'The man tears the parcel open, delighted.'],
                ]],
                ['style' => 'color', 'panels' => [
                    ['scene' => 'room', 'figures' => [$f('dai', 30), $f('yeti', 72, 'surprised', .9)], 'props' => [$p('momo', 50, 70, 1.2)],
                        'bubbles' => [$b('Fresh momos from my sister in Kathmandu! Share some?', 4, 4, 58, 'br')],
                        'alt' => 'Inside a cosy room, the man offers the yeti a plate of momos.'],
                    ['scene' => 'room', 'figures' => [$f('yeti', 50, 'laugh')],
                        'bubbles' => [$b('Yeti Delivery Co.: we deliver — and sometimes we stay for lunch.', 4, 4, 92, 'none')],
                        'alt' => 'The yeti laughs happily.'],
                    ['scene' => 'night', 'span' => 2, 'figures' => [$f('yeti', 30, 'sleepy')], 'props' => [$p('star', 60, 30), $p('star', 70, 18, .7)],
                        'caption' => 'Tomorrow\'s deliveries: 12. Today\'s momos left: 0.',
                        'alt' => 'The yeti dozes under the night sky.'],
                ]],
            ]],
        ],
    ],
];
