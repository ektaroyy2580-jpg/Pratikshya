<?php

/*
| Café hub — representative photos.
| Every photo was downloaded once (2026-10-15) from Wikimedia Commons through the Commons API,
| licence checked from its extmetadata (CC0 / public domain / CC BY / CC BY-SA only), and saved
| as a ≤1000px thumbnail under public/images/{cafes,dishes}. Nothing is fetched at runtime.
| The cafés in this app are fictional, so every photo is shown as "representative photo"
| and never shows a real business's signage or branding.
|
| images: key => [path, commons file title, author, licence, licence url, source page, alt text]
*/

return [
    'images' => [
        // ---------- café / restaurant scenes ----------
        'cafes/cafe-interior-1' => ['images/cafes/cafe-interior-1.jpg', 'Cozy cafe interior with stylish seating and large windows on a quiet street.jpg', 'Shixart1985', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0', 'https://commons.wikimedia.org/wiki/File:Cozy_cafe_interior_with_stylish_seating_and_large_windows_on_a_quiet_street.jpg', 'Café seating by large windows'],
        'cafes/cafe-interior-2' => ['images/cafes/cafe-interior-2.jpg', 'Stylish interior of a modern cafe showcasing green chairs.jpg', 'Shixart1985', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0', 'https://commons.wikimedia.org/wiki/File:Stylish_interior_of_a_modern_cafe_showcasing_green_chairs.jpg', 'Café interior with green chairs and a wooden counter'],
        'cafes/cafe-interior-3' => ['images/cafes/cafe-interior-3.jpg', 'Modern interior design of a chic café showcasing wooden accents and spacious layout.jpg', 'Shixart1985', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0', 'https://commons.wikimedia.org/wiki/File:Modern_interior_design_of_a_chic_caf%C3%A9_showcasing_wooden_accents_and_spacious_layout.jpg', 'Café interior with wooden pillars and slatted ceiling'],
        'cafes/chiya-masala' => ['images/cafes/chiya-masala.jpg', 'Masala Chiya.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Masala_Chiya.jpg', 'A cup of spiced masala chiya'],
        'cafes/chiya-roadside' => ['images/cafes/chiya-roadside.jpg', 'TeaShop.jpg', 'Tasbirkarma', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:TeaShop.jpg', 'Tea being poured at a roadside tea stall at night'],
        'cafes/chiya-selroti' => ['images/cafes/chiya-selroti.jpg', 'Chiya Sel Roti.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Chiya_Sel_Roti.jpg', 'Milk tea with sel roti'],
        'cafes/coffee-espresso' => ['images/cafes/coffee-espresso.jpg', 'Espresso machine in café.jpg', 'MichalPL', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Espresso_machine_in_caf%C3%A9.jpg', 'Espresso machine with cups stacked on top'],
        'cafes/coffee-latteart' => ['images/cafes/coffee-latteart.jpg', 'Latte art 3.jpg', 'Kim Sanso', 'CC0', 'https://creativecommons.org/publicdomain/zero/1.0/', 'https://commons.wikimedia.org/wiki/File:Latte_art_3.jpg', 'Milk being poured into a latte'],
        'cafes/dalbhat-1' => ['images/cafes/dalbhat-1.jpg', 'Dal Bhat Tarkari.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Dal_Bhat_Tarkari.jpg', 'Dal bhat tarkari set on a steel plate'],
        'cafes/dalbhat-2' => ['images/cafes/dalbhat-2.jpg', 'Nepali dal-bhat-tarkari.jpg', 'Subhashish Panigrahi', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Nepali_dal-bhat-tarkari.jpg', 'Thakali-style set meal on a brass plate'],
        'cafes/juice-mango' => ['images/cafes/juice-mango.jpg', 'Glasses of Mango juice.jpg', 'Kvr.lohith', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Glasses_of_Mango_juice.jpg', 'Glasses of fresh mango juice'],
        'cafes/juice-orange' => ['images/cafes/juice-orange.jpg', 'Glass of Fresh Orange Juice.jpg', 'Rebeloasisx', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Glass_of_Fresh_Orange_Juice.jpg', 'A glass of fresh orange juice'],
        'cafes/juice-pomegranate' => ['images/cafes/juice-pomegranate.jpg', 'Pomegranate juice with slice.jpg', 'Augustus Binu', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0', 'https://commons.wikimedia.org/wiki/File:Pomegranate_juice_with_slice.jpg', 'Pomegranate juice beside an open pomegranate'],
        'cafes/khaja-set' => ['images/cafes/khaja-set.jpg', 'Khaja Set.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Khaja_Set.jpg', 'Newari khaja set on a steel plate'],
        'cafes/lassi-glass' => ['images/cafes/lassi-glass.jpg', 'Plain Lassi in a glass.jpg', 'Kyu3a', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Plain_Lassi_in_a_glass.jpg', 'A tall glass of plain lassi'],
        'cafes/momo-mokto' => ['images/cafes/momo-mokto.jpg', 'Mokto or moktoo 02.jpg', 'Poojilsharma07', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Mokto_or_moktoo_02.jpg', 'Traditional brass momo steamers (mokto)'],
        'cafes/momo-plate' => ['images/cafes/momo-plate.jpg', 'Momo nepal.jpg', 'Kushal Goyal', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0', 'https://commons.wikimedia.org/wiki/File:Momo_nepal.jpg', 'A plate of momo with sesame and chilli achar'],
        'cafes/momo-steamer' => ['images/cafes/momo-steamer.jpg', 'Momos in a steamer..jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Momos_in_a_steamer..jpg', 'A tray of momo in a steamer'],
        'cafes/nepali-thali' => ['images/cafes/nepali-thali.jpg', 'Traditional Nepali Thali.jpg', 'Antara.upadhyay7', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Traditional_Nepali_Thali.jpg', 'Traditional Nepali thali with small bowls'],

        // ---------- dishes ----------
        'dishes/aloo-chop' => ['images/dishes/aloo-chop.jpg', 'Potato Croquettes-Aloo Chop (- Kolkata - West Bengal - DSC 0014.jpg', 'Tapas Kumar Halder', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Potato_Croquettes-Aloo_Chop_(-_Kolkata_-_West_Bengal_-_DSC_0014.jpg', 'Fried aloo chop'],
        'dishes/americano' => ['images/dishes/americano.jpg', 'Black coffee with sugar - Evan Swigart.jpg', 'Evan Swigart', 'CC BY 2.0', 'https://creativecommons.org/licenses/by/2.0', 'https://commons.wikimedia.org/wiki/File:Black_coffee_with_sugar_-_Evan_Swigart.jpg', 'Black coffee in a white cup'],
        'dishes/buff-momo' => ['images/dishes/buff-momo.jpg', 'Buff Momo 1.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Buff_Momo_1.jpg', 'Steamed buff momo with achar'],
        'dishes/cappuccino' => ['images/dishes/cappuccino.jpg', 'A cup of cappuccino.jpg', 'Vee Satayamas', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0', 'https://commons.wikimedia.org/wiki/File:A_cup_of_cappuccino.jpg', 'Cappuccino with latte art'],
        'dishes/chilli-momo' => ['images/dishes/chilli-momo.jpg', 'Chilli Momo.jpg', 'Medhi jyoti', 'CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0', 'https://commons.wikimedia.org/wiki/File:Chilli_Momo.jpg', 'Chilli momo tossed with peppers'],
        'dishes/chiura-bhatmas' => ['images/dishes/chiura-bhatmas.jpg', 'Chiura Bhuja Dalmoth.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Chiura_Bhuja_Dalmoth.jpg', 'Beaten rice with crunchy snacks'],
        'dishes/chocolate-cake' => ['images/dishes/chocolate-cake.jpg', 'Piece of chocolate cake on a white plate decorated with chocolate sauce.jpg', 'Daria Yakovleva', 'CC0', 'https://creativecommons.org/publicdomain/zero/1.0/', 'https://commons.wikimedia.org/wiki/File:Piece_of_chocolate_cake_on_a_white_plate_decorated_with_chocolate_sauce.jpg', 'A slice of layered chocolate cake'],
        'dishes/cinnamon-roll' => ['images/dishes/cinnamon-roll.jpg', 'Cinnamon roll in Stockholm.jpg', 'Kritzolina', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Cinnamon_roll_in_Stockholm.jpg', 'A knotted cinnamon roll with pearl sugar'],
        'dishes/croissant' => ['images/dishes/croissant.jpg', 'Croissants au beurre (18953292873).jpg', 'Herry Wibisono', 'CC0', 'https://creativecommons.org/publicdomain/zero/1.0/', 'https://commons.wikimedia.org/wiki/File:Croissants_au_beurre_(18953292873).jpg', 'Butter croissants'],
        'dishes/dhido' => ['images/dishes/dhido.jpg', 'Nepali Dhido.jpg', 'Janak Bhatta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Nepali_Dhido.jpg', 'Dhido with gundruk and greens'],
        'dishes/gundruk-soup' => ['images/dishes/gundruk-soup.jpg', 'Gundruk Jhol.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Gundruk_Jhol.jpg', 'Gundruk soup with a ladle'],
        'dishes/iced-latte' => ['images/dishes/iced-latte.jpg', 'Iced latte - BROR 2025-04-26.jpg', 'Andy Li', 'CC0', 'https://creativecommons.org/publicdomain/zero/1.0/', 'https://commons.wikimedia.org/wiki/File:Iced_latte_-_BROR_2025-04-26.jpg', 'A glass of iced latte'],
        'dishes/jhol-momo' => ['images/dishes/jhol-momo.jpg', 'Jhol Momo.jpg', 'Shristi Kapali', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Jhol_Momo.jpg', 'Jhol momo in a bowl of soup'],
        'dishes/kalo-chiya' => ['images/dishes/kalo-chiya.jpg', 'Glass tea cup.jpg', 'Zeynel Cebeci', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Glass_tea_cup.jpg', 'Black tea in a small glass'],
        'dishes/kothey-momo' => ['images/dishes/kothey-momo.jpg', 'Kothey momo.JPG', 'Kushal Goyal', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0', 'https://commons.wikimedia.org/wiki/File:Kothey_momo.JPG', 'Pan-fried kothey momo'],
        'dishes/lassi' => ['images/dishes/lassi.jpg', 'Lassi 1.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Lassi_1.jpg', 'A glass of sweet lassi'],
        'dishes/lemon-ginger' => ['images/dishes/lemon-ginger.jpg', 'HK food drink Lemon water boiling hot Dec-2015 sliced Lemonade.JPG', 'Fiarwalloe 2015', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:HK_food_drink_Lemon_water_boiling_hot_Dec-2015_sliced_Lemonade.JPG', 'Hot lemon drink with lemon slices'],
        'dishes/lemon-soda' => ['images/dishes/lemon-soda.jpg', 'Cold water with lemon and mint.jpg', 'Peachyeung316', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Cold_water_with_lemon_and_mint.jpg', 'Cold lemon and mint drink'],
        'dishes/masala-chiya' => ['images/dishes/masala-chiya.jpg', 'Masala Chiya.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Masala_Chiya.jpg', 'A cup of masala chiya'],
        'dishes/samosa' => ['images/dishes/samosa.jpg', 'Samosa with Chutney.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Samosa_with_Chutney.jpg', 'Samosa on a steel plate'],
        'dishes/sandwich' => ['images/dishes/sandwich.jpg', 'Chicken sandwich.jpg', 'Tmannya', 'CC BY-SA 3.0', 'https://creativecommons.org/licenses/by-sa/3.0', 'https://commons.wikimedia.org/wiki/File:Chicken_sandwich.jpg', 'Toasted chicken sandwich'],
        'dishes/sel-roti' => ['images/dishes/sel-roti.jpg', 'Sel Roti Nepal.jpg', 'Janak Bhatta', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Sel_Roti_Nepal.jpg', 'A stack of sel roti'],
        'dishes/thakali-chicken' => ['images/dishes/thakali-chicken.jpg', 'Dal bhat.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Dal_bhat.jpg', 'Dal bhat set with curry'],
        'dishes/thakali-mutton' => ['images/dishes/thakali-mutton.jpg', 'Dal Bhat Tarkari 4.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Dal_Bhat_Tarkari_4.jpg', 'Dal bhat set with meat curry'],
        'dishes/thakali-veg' => ['images/dishes/thakali-veg.jpg', 'Dal Bhat Tarkari 2.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Dal_Bhat_Tarkari_2.jpg', 'Vegetarian dal bhat set'],
        'dishes/veg-momo' => ['images/dishes/veg-momo.jpg', 'Veg Momo 1.jpg', 'Gaurav Dhwaj Khadka', 'CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0', 'https://commons.wikimedia.org/wiki/File:Veg_Momo_1.jpg', 'Veg momo with tomato achar'],
    ],

    /*
    | Galleries per café theme (first = hero). The theme is picked from the place / café name.
    | Same-themed places rotate the order so neighbouring listings don't all share one hero.
    */
    'themes' => [
        'momo' => ['cafes/momo-plate', 'cafes/momo-steamer', 'cafes/momo-mokto', 'dishes/jhol-momo'],
        'chiya' => ['cafes/chiya-masala', 'cafes/chiya-roadside', 'cafes/chiya-selroti', 'dishes/samosa'],
        'thakali' => ['cafes/dalbhat-2', 'cafes/dalbhat-1', 'cafes/nepali-thali', 'dishes/dhido'],
        'coffee' => ['cafes/coffee-latteart', 'cafes/cafe-interior-2', 'cafes/coffee-espresso', 'dishes/croissant'],
        'juice' => ['cafes/juice-pomegranate', 'cafes/juice-orange', 'cafes/juice-mango', 'cafes/cafe-interior-1'],
        'lassi' => ['cafes/lassi-glass', 'dishes/lassi', 'cafes/cafe-interior-3', 'cafes/juice-mango'],
        'canteen' => ['cafes/khaja-set', 'cafes/dalbhat-1', 'cafes/chiya-selroti', 'dishes/samosa'],
        'cafe' => ['cafes/cafe-interior-1', 'cafes/coffee-latteart', 'cafes/cafe-interior-3', 'dishes/cinnamon-roll'],
        'restaurant' => ['cafes/nepali-thali', 'cafes/momo-plate', 'cafes/dalbhat-1'],
    ],

    /* Theme detection: first keyword found in the lower-cased name (then category) wins. */
    'theme_keywords' => [
        'momo' => 'momo', 'chiya' => 'chiya', 'tea' => 'chiya', 'thakali' => 'thakali', 'bhansa' => 'thakali', 'dal bhat' => 'thakali',
        'canteen' => 'canteen', 'coffee' => 'coffee', 'brew' => 'coffee', 'bakery' => 'coffee', 'juice' => 'juice', 'lassi' => 'lassi',
    ],

    /* Dish photos: first keyword found in the lower-cased item name wins; then the CSS art key. */
    'dish_keywords' => [
        'c-momo' => 'dishes/chilli-momo', 'chilli momo' => 'dishes/chilli-momo', 'jhol' => 'dishes/jhol-momo', 'kothey' => 'dishes/kothey-momo',
        'veg momo' => 'dishes/veg-momo', 'buff' => 'dishes/buff-momo', 'momo' => 'dishes/buff-momo',
        'masala chiya' => 'dishes/masala-chiya', 'kalo chiya' => 'dishes/kalo-chiya', 'ginger lemon' => 'dishes/lemon-ginger', 'lemon mint' => 'dishes/lemon-soda',
        'sel roti' => 'dishes/sel-roti', 'samosa' => 'dishes/samosa', 'aloo chop' => 'dishes/aloo-chop', 'iced latte' => 'dishes/iced-latte', 'lassi' => 'dishes/lassi',
        'mutton thakali' => 'dishes/thakali-mutton', 'chicken thakali' => 'dishes/thakali-chicken', 'thakali' => 'dishes/thakali-veg',
        'dhido' => 'dishes/dhido', 'gundruk' => 'dishes/gundruk-soup', 'chiura' => 'dishes/chiura-bhatmas',
        'croissant' => 'dishes/croissant', 'cinnamon' => 'dishes/cinnamon-roll', 'chocolate' => 'dishes/chocolate-cake', 'sandwich' => 'dishes/sandwich',
        'americano' => 'dishes/americano', 'cappuccino' => 'dishes/cappuccino', 'chiya' => 'dishes/masala-chiya',
    ],
    'dish_art' => [
        'momo' => 'dishes/buff-momo', 'cmomo' => 'dishes/chilli-momo', 'jhol' => 'dishes/jhol-momo', 'chiya' => 'dishes/masala-chiya',
        'thali' => 'dishes/thakali-veg', 'dhido' => 'dishes/dhido', 'selroti' => 'dishes/sel-roti', 'samosa' => 'dishes/samosa',
        'lassi' => 'dishes/lassi', 'croissant' => 'dishes/croissant', 'bun' => 'dishes/cinnamon-roll', 'cake' => 'dishes/chocolate-cake',
        'sandwich' => 'dishes/sandwich', 'coffee' => 'dishes/cappuccino', 'soup' => 'dishes/gundruk-soup',
    ],
];
