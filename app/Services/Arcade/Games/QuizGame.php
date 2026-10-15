<?php

namespace App\Services\Arcade\Games;

use App\Services\Arcade\AiQuestionSource;
use App\Services\Arcade\QuestionGame;

/** Nepali General Knowledge Quiz — server-held bank (38 questions) with explanations. */
class QuizGame extends QuestionGame
{
    protected int $secondsPerQuestion = 20;

    public function key(): string
    {
        return 'quiz';
    }

    protected function extraQuestions(): array
    {
        return app(AiQuestionSource::class)->questions('quiz', 'general knowledge about Nepal (geography, culture, history, festivals)');
    }

    protected function bank(): array
    {
        $q = fn ($q, $options, $answer, $explain) => compact('q', 'options', 'answer', 'explain');

        return [
            $q('What is the capital city of Nepal?', ['Pokhara', 'Kathmandu', 'Lalitpur', 'Biratnagar'], 1, 'Kathmandu is the capital and largest city, set in the Kathmandu Valley with Lalitpur and Bhaktapur.'),
            $q('What is the Nepali name for Mount Everest?', ['Sagarmatha', 'Annapurna', 'Machhapuchhre', 'Kanchenjunga'], 0, 'Sagarmatha means roughly "forehead of the sky". The mountain sits in Sagarmatha National Park.'),
            $q('What height for Mount Everest did Nepal and China announce jointly in 2020?', ['8,848.86 m', '8,844.43 m', '8,850.00 m', '8,611.00 m'], 0, 'The 2020 joint survey set the official height at 8,848.86 metres.'),
            $q('What is the national flower of Nepal?', ['Lotus', 'Marigold', 'Rhododendron (Lali Gurans)', 'Orchid'], 2, 'Lali Gurans, the red rhododendron, colours Nepal\'s hills in spring.'),
            $q('Which bird is Nepal\'s national bird?', ['Himalayan Monal (Danphe)', 'Peacock', 'Spiny Babbler', 'Sarus Crane'], 0, 'The Danphe (Himalayan Monal) is known for its shimmering, rainbow-coloured feathers.'),
            $q('What is the national animal of Nepal?', ['One-horned rhino', 'Cow', 'Snow leopard', 'Yak'], 1, 'The cow is Nepal\'s national animal and is considered sacred by many Nepalis.'),
            $q('What makes Nepal\'s national flag unique?', ['It has three stars', 'It is the only non-rectangular national flag', 'It is entirely blue', 'It has no symbols'], 1, 'Nepal\'s flag is made of two stacked pennants, the only non-rectangular national flag in the world.'),
            $q('Lumbini is famous as the birthplace of whom?', ['King Janak', 'Siddhartha Gautama (the Buddha)', 'Araniko', 'Bhanubhakta Acharya'], 1, 'Siddhartha Gautama was born in Lumbini (Rupandehi), now a UNESCO World Heritage Site.'),
            $q('What is the currency of Nepal?', ['Nepalese Rupee', 'Taka', 'Ngultrum', 'Kyat'], 0, 'The Nepalese Rupee (NPR, रू) is issued by Nepal Rastra Bank.'),
            $q('How many provinces does Nepal have under the 2015 constitution?', ['5', '6', '7', '14'], 2, 'The Constitution of Nepal (2015) created seven provinces.'),
            $q('In which year was the current Constitution of Nepal promulgated?', ['2006', '2008', '2015', '2017'], 2, 'It was promulgated on 20 September 2015 (Asoj 3, 2072 BS), now marked as Constitution Day.'),
            $q('In which year did Nepal become a federal democratic republic, ending the monarchy?', ['1990', '2006', '2008', '2015'], 2, 'The first Constituent Assembly declared Nepal a republic on 28 May 2008.'),
            $q('Which is the longest river in Nepal?', ['Koshi', 'Gandaki', 'Bagmati', 'Karnali'], 3, 'The Karnali, rising near Mount Kailash, is the longest river flowing through Nepal.'),
            $q('Which is the largest lake in Nepal?', ['Phewa Lake', 'Rara Lake', 'Begnas Lake', 'Tilicho Lake'], 1, 'Rara Lake in Mugu district is Nepal\'s largest and deepest lake.'),
            $q('Who is known for unifying modern Nepal?', ['Prithvi Narayan Shah', 'Jung Bahadur Rana', 'Bhimsen Thapa', 'King Mahendra'], 0, 'Prithvi Narayan Shah, King of Gorkha, began the unification of Nepal in the 18th century.'),
            $q('Who summited Everest with Edmund Hillary in 1953?', ['Ang Rita Sherpa', 'Tenzing Norgay Sherpa', 'Apa Sherpa', 'Pemba Dorje Sherpa'], 1, 'Tenzing Norgay Sherpa and Edmund Hillary made the first confirmed ascent on 29 May 1953.'),
            $q('Which calendar is Nepal\'s official calendar?', ['Gregorian', 'Bikram Sambat', 'Nepal Sambat only', 'Hijri'], 1, 'Bikram Sambat (BS) is the official calendar; it runs about 56 years and 8 months ahead of AD.'),
            $q('Which was Nepal\'s first national park?', ['Sagarmatha', 'Bardiya', 'Chitwan', 'Langtang'], 2, 'Chitwan National Park was established in 1973 and is home to one-horned rhinos and Bengal tigers.'),
            $q('How many districts does Nepal have?', ['75', '77', '72', '80'], 1, 'Nepal has 77 districts across its seven provinces.'),
            $q('Phewa Lake is located in which city?', ['Pokhara', 'Dharan', 'Butwal', 'Janakpur'], 0, 'Phewa Lake lies in Pokhara, with the Annapurna range and Machhapuchhre reflected on calm days.'),
            $q('Which is the longest and most widely celebrated festival in Nepal?', ['Tihar', 'Holi', 'Dashain', 'Teej'], 2, 'Dashain lasts about 15 days and celebrates the victory of good over evil; elders give tika and jamara.'),
            $q('On Kukur Tihar, which animal is honoured?', ['Crow', 'Dog', 'Cow', 'Ox'], 1, 'On Kukur Tihar, dogs receive garlands, tika and treats for their loyalty.'),
            $q('How many UNESCO World Heritage Sites does Nepal have?', ['2', '4', '6', '8'], 1, 'Kathmandu Valley, Sagarmatha National Park, Chitwan National Park and Lumbini.'),
            $q('Which sport was declared Nepal\'s national game in 2017?', ['Cricket', 'Football', 'Volleyball', 'Dandi Biyo'], 2, 'Volleyball was declared the national game in 2017.'),
            $q('Pashupatinath Temple is dedicated to which deity?', ['Lord Vishnu', 'Lord Shiva', 'Goddess Durga', 'Lord Ganesh'], 1, 'Pashupatinath, on the banks of the Bagmati, is one of the most sacred Shiva temples.'),
            $q('Gorkhapatra, Nepal\'s oldest running newspaper, was first published in which year?', ['1901', '1951', '1920', '1890'], 0, 'Gorkhapatra began in 1901 AD (1958 BS).'),
            $q('What is the national colour of Nepal?', ['Royal blue', 'Crimson (Simrik)', 'Saffron', 'Green'], 1, 'Crimson red (Simrik) is the national colour, also the colour of the flag and the rhododendron.'),
            $q('Tribhuvan International Airport is in which city?', ['Pokhara', 'Bhairahawa', 'Kathmandu', 'Biratnagar'], 2, 'Tribhuvan International Airport in Kathmandu is Nepal\'s main international gateway.'),
            $q('Who was the first President of Nepal?', ['Bidya Devi Bhandari', 'Ram Chandra Poudel', 'Ram Baran Yadav', 'Girija Prasad Koirala'], 2, 'Dr. Ram Baran Yadav became the first President in 2008.'),
            $q('What is Nepal Standard Time\'s offset from UTC?', ['+5:30', '+5:45', '+6:00', '+5:00'], 1, 'Nepal is one of very few countries with a 45-minute offset: UTC+5:45.'),
            $q('Swayambhunath is popularly known as which temple?', ['Golden Temple', 'Monkey Temple', 'Lake Temple', 'Peacock Temple'], 1, 'Swayambhunath stupa is called the Monkey Temple because of the monkeys living on the hill.'),
            $q('Which script is used to write the Nepali language?', ['Latin', 'Tibetan', 'Devanagari', 'Ranjana only'], 2, 'Nepali is written in Devanagari, the same script used for Hindi and Sanskrit.'),
            $q('Nepal shares land borders with which two countries?', ['India and China', 'India and Bhutan', 'China and Bangladesh', 'Bhutan and China'], 0, 'Nepal is landlocked between India (east, south, west) and China (north).'),
            $q('Gautam Buddha International Airport is located near which town?', ['Nepalgunj', 'Bhairahawa', 'Janakpur', 'Dhangadhi'], 1, 'It serves Bhairahawa in Rupandehi, close to Lumbini.'),
            $q('Kanchenjunga, the world\'s third-highest mountain, lies on Nepal\'s border with which region?', ['Tibet', 'Sikkim (India)', 'Bhutan', 'Uttarakhand (India)'], 1, 'Kanchenjunga (8,586 m) sits on the border between eastern Nepal and Sikkim, India.'),
            $q('Who is the "Kumari" in Kathmandu tradition?', ['A queen', 'A young girl worshipped as a living goddess', 'A temple priestess', 'A festival dancer'], 1, 'The Kumari is a young girl revered as the living incarnation of goddess Taleju.'),
            $q('Bhanubhakta Acharya is celebrated for what?', ['First Nepali-language Ramayana', 'Building Dharahara', 'Founding Gorkhapatra', 'Measuring Everest'], 0, 'Bhanubhakta translated the Ramayana into Nepali and is honoured as Adikavi (first poet).'),
            $q('Which river flows through the Kathmandu Valley past Pashupatinath?', ['Seti', 'Bagmati', 'Trishuli', 'Rapti'], 1, 'The Bagmati is sacred to Hindus and Buddhists and flows past Pashupatinath.'),
        ];
    }
}
