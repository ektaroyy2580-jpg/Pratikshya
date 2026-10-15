{{--
    Audiobook player bar (Listen mode). Uses the browser's built-in speechSynthesis — fully local, no audio files,
    nothing is uploaded. Driven by public/js/modules/library-listen.js. Vars: $book, $chapter, $isComic
--}}
<section class="ls-bar" id="lsBar" aria-label="{{ $isComic ? 'Read-aloud player' : 'Audiobook player' }}" hidden>
    <div class="ls-in">
        <div class="ls-track" aria-hidden="true"><span id="lsTrack"></span></div>
        <div class="ls-row">
            <div class="ls-meta min-w-0">
                <div class="ls-k"><i class="bi bi-headphones"></i> <span id="lsState">{{ $isComic ? 'Read aloud' : 'Audiobook' }}</span><span class="ls-sleep-left" id="lsSleepLeft" hidden></span></div>
                <div class="ls-where text-truncate" id="lsWhere">Chapter {{ $chapter->position }} · ready</div>
            </div>
            <div class="ls-ctrls" role="group" aria-label="Playback">
                <button type="button" class="ls-btn ls-skip" id="lsBack" aria-label="Back about 15 seconds" title="Back ~15 s (previous sentences)"><i class="bi bi-arrow-counterclockwise"></i><span>15</span></button>
                <button type="button" class="ls-play" id="lsPlay" aria-label="Play"><i class="bi bi-play-fill"></i></button>
                <button type="button" class="ls-btn ls-skip" id="lsFwd" aria-label="Forward about 15 seconds" title="Forward ~15 s (next sentences)"><i class="bi bi-arrow-clockwise"></i><span>15</span></button>
            </div>
            <div class="ls-side">
                <button type="button" class="ls-pill" id="lsRate" aria-label="Playback speed: 1×. Click to change.">1×</button>
                <button type="button" class="ls-pill ls-follow" id="lsFollow" hidden><i class="bi bi-crosshair me-1"></i>Follow</button>
                <div class="position-relative">
                    <button type="button" class="ls-btn" id="lsMoreBtn" aria-expanded="false" aria-controls="lsMore" aria-label="Voice, speed and sleep timer"><i class="bi bi-sliders"></i></button>
                    <div class="ls-pop" id="lsMore" hidden>
                        <label for="lsVoice" class="ls-lbl">Voice</label>
                        <select id="lsVoice" class="form-select form-select-sm mb-1"><option value="">Device default</option></select>
                        <p class="ls-hint mb-3" id="lsVoiceHint"></p>
                        <span class="ls-lbl" id="lsRatesLbl">Speed</span>
                        <div class="rd-seg ls-rates mb-3" role="radiogroup" aria-labelledby="lsRatesLbl">
                            @foreach([0.75, 1, 1.25, 1.5, 1.75, 2] as $r)
                                <button type="button" role="radio" data-rate="{{ $r }}">{{ rtrim(rtrim(number_format($r, 2), '0'), '.') }}×</button>
                            @endforeach
                        </div>
                        <label for="lsSleep" class="ls-lbl">Sleep timer</label>
                        <select id="lsSleep" class="form-select form-select-sm mb-3">
                            <option value="">Off</option>
                            <option value="5">5 minutes</option>
                            <option value="10">10 minutes</option>
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                            <option value="chapter">End of this {{ $isComic ? 'page' : 'chapter' }}</option>
                        </select>
                        <label class="ls-check"><input type="checkbox" id="lsAutoScroll" checked> Follow the voice (auto-scroll)</label>
                        <p class="ls-hint mt-2 mb-0"><i class="bi bi-shield-check me-1"></i>Spoken by your device’s own voice. No audio files, nothing uploaded.</p>
                    </div>
                </div>
                <button type="button" class="ls-btn" id="lsClose" aria-label="Close the player"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <div class="ls-note" id="lsNote" role="status" aria-live="polite" hidden></div>
    </div>
</section>
