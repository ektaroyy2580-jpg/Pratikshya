{{-- Pure-CSS food illustration. Usage: @include('preorder.partials.food-art', ['art' => 'momo', 'class' => ''])
     Optional 'photo' => image_path (public/ relative): a representative photo fades in over the illustration;
     the illustration stays as the fallback when the file is missing or fails to load. --}}
@php
    $known = ['momo', 'jhol', 'cmomo', 'chiya', 'thali', 'dhido', 'salad', 'selroti', 'samosa', 'lassi', 'croissant', 'bun', 'cake', 'sandwich', 'coffee', 'soup'];
    $art = in_array($art ?? null, $known, true) ? $art : 'momo';
    $label = $label ?? '';
    $photoUrl = \App\Services\Cafes\CafeImages::url($photo ?? null);
@endphp
<div class="fa fa-{{ $art }} {{ $class ?? '' }} {{ $photoUrl ? 'has-photo' : '' }}" role="img" aria-label="{{ $label ?: 'Illustration of '.$art }}">
@if($photoUrl)<img class="fa-photo" src="{{ $photoUrl }}" alt="" loading="lazy" decoding="async" onload="this.classList.add('is-in')" onerror="this.remove()">@endif
@switch($art)
    @case('momo')
        <span class="plate"></span>
        <span class="d" style="left:27%;top:30%"></span><span class="d" style="left:47%;top:27%"></span>
        <span class="d" style="left:24%;top:51%"></span><span class="d" style="left:45%;top:50%"></span>
        <span class="dip" style="left:63%;top:42%"></span>
        <span class="herb" style="left:40%;top:46%;--r:30deg"></span><span class="herb" style="left:60%;top:30%;--r:-20deg"></span>
        @break
    @case('cmomo')
        <span class="plate"></span>
        <span class="d glaze" style="left:28%;top:30%"></span><span class="d glaze" style="left:50%;top:28%"></span>
        <span class="d glaze" style="left:30%;top:52%"></span><span class="d glaze" style="left:52%;top:50%"></span>
        <span class="pep" style="left:44%;top:46%;--c:#3b9a3b;--r:25deg"></span><span class="pep" style="left:25%;top:46%;--c:#f2c230;--r:-30deg"></span>
        <span class="pep" style="left:58%;top:70%;--c:#3b9a3b;--r:-10deg"></span><span class="pep" style="left:62%;top:40%;--c:#f2c230;--r:60deg"></span>
        @break
    @case('jhol')
        <span class="bowl" style="--soup:#e8642c"></span>
        <span class="d" style="left:33%;top:32%;width:17%"></span><span class="d" style="left:50%;top:30%;width:17%"></span>
        <span class="d" style="left:41%;top:50%;width:17%"></span>
        <span class="herb" style="left:36%;top:60%;--r:40deg"></span><span class="herb" style="left:60%;top:55%;--r:-30deg"></span><span class="herb" style="left:30%;top:46%"></span>
        @break
    @case('soup')
        <span class="bowl" style="--soup:#a8862f"></span>
        <span class="herb" style="left:38%;top:36%;--r:30deg;width:6%"></span><span class="herb" style="left:56%;top:48%;--r:-30deg;width:6%"></span>
        <span class="herb" style="left:44%;top:60%;--r:70deg;width:5%"></span><span class="ghee" style="left:52%;top:32%;width:5%"></span><span class="ghee" style="left:36%;top:52%;width:4%"></span>
        @break
    @case('chiya')
        <span class="saucer"></span><span class="glass"></span>
        <span class="steam" style="left:43%;--r:-8deg"></span><span class="steam" style="left:50%;top:1%;--r:10deg"></span><span class="steam" style="left:56%;--r:-4deg"></span>
        <span class="leaf" style="left:66%;bottom:14%;--r:-30deg"></span>
        @break
    @case('lassi')
        <span class="saucer"></span><span class="glass tall cream"></span><span class="straw"></span>
        <span class="leaf" style="left:40%;top:15%;--r:-35deg"></span>
        @break
    @case('thali')
        <span class="plate brass"></span>
        <span class="rice" style="left:36%;top:38%"></span>
        <span class="katori" style="left:24%;top:24%;--f:#e9b949"></span>
        <span class="katori" style="left:56%;top:20%;--f:#5a8f3a"></span>
        <span class="katori" style="left:66%;top:46%;--f:#d97a2b"></span>
        <span class="katori" style="left:22%;top:56%;--f:#b8321f"></span>
        <span class="papad" style="left:50%;top:60%;width:17%"></span>
        @break
    @case('dhido')
        <span class="plate brass"></span>
        <span class="dhido" style="left:32%;top:34%"></span><span class="ghee" style="left:43%;top:40%"></span>
        <span class="katori" style="left:60%;top:26%;--f:#6e8f2f"></span>
        <span class="katori" style="left:62%;top:52%;--f:#b8321f"></span>
        @break
    @case('salad')
        <span class="bowl" style="--soup:#f4e3b5"></span><span class="cubes"></span>
        <span class="herb" style="left:40%;top:40%"></span><span class="herb" style="left:56%;top:52%;--r:-40deg"></span><span class="herb" style="left:48%;top:34%;--r:70deg"></span>
        @break
    @case('selroti')
        <span class="paper"></span>
        <span class="ring" style="left:16%;top:22%"></span><span class="ring" style="left:42%;top:30%"></span>
        @break
    @case('samosa')
        <span class="plate"></span>
        <span class="tri" style="left:22%;top:30%;transform:rotate(-14deg)"></span>
        <span class="tri" style="left:42%;top:34%;transform:rotate(12deg)"></span>
        <span class="dip" style="left:64%;top:24%;width:16%;background:radial-gradient(closest-side,#7a3b12 0 55%,#5c2a0b 70%,#fff 74% 86%,#dfe3ea 100%)"></span>
        @break
    @case('croissant')
        <span class="shadow"></span>
        <span class="seg" style="left:14%;top:48%;width:18%;transform:rotate(-50deg)"></span>
        <span class="seg" style="left:24%;top:36%;width:22%;transform:rotate(-30deg)"></span>
        <span class="seg" style="left:37%;top:31%;width:26%"></span>
        <span class="seg" style="left:54%;top:36%;width:22%;transform:rotate(30deg)"></span>
        <span class="seg" style="left:68%;top:48%;width:18%;transform:rotate(50deg)"></span>
        @break
    @case('bun')
        <span class="plate"></span><span class="swirl" style="left:28%;top:26%"></span>
        <span class="glaze-line" style="left:30%;top:40%;width:40%"></span><span class="glaze-line" style="left:32%;top:52%;width:36%;--r:-12deg"></span><span class="glaze-line" style="left:36%;top:63%;width:28%;--r:-22deg"></span>
        @break
    @case('cake')
        <span class="saucer" style="left:18%;right:18%;height:12%"></span><span class="slice"></span>
        <span class="cherry" style="left:36%;top:22%"></span>
        @break
    @case('sandwich')
        <span class="plate"></span>
        <span class="sand" style="left:22%;top:30%"></span>
        <span class="sand" style="left:42%;top:26%;transform:scaleX(-1) rotate(-6deg)"></span>
        @break
    @case('coffee')
        <span class="saucer" style="left:22%;right:22%;top:20%;bottom:12%;height:auto"></span>
        <span class="cup" style="left:28%;top:22%"></span><span class="handle" style="left:68%;top:40%"></span>
        <span class="heart" style="left:45%;top:41%"></span>
        @break
@endswitch
</div>
