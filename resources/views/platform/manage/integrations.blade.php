@extends('platform.layouts.console')
@section('title', 'Integrations & AI — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-plug"></i> Platform</span>
        <h1>Integrations & AI provider</h1>
        <p>What is really connected in this build. Secrets are never displayed.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <section class="pk-card pf-panel reveal" aria-labelledby="aiH">
            <div class="pf-panel-head">
                <h2 id="aiH"><i class="bi bi-cpu text-primary"></i> AI provider</h2>
                @if($ai['enabled'])<span class="pf-badge ok"><i class="bi bi-check2"></i> Configured</span>@else<span class="pf-badge muted"><i class="bi bi-power"></i> Off — local fallbacks</span>@endif
            </div>
            <div class="pf-panel-body">
                <dl class="row small mb-0 g-2">
                    <dt class="col-5 pk-muted fw-semibold">Provider (AI_PROVIDER)</dt><dd class="col-7 mb-0"><span class="pf-act">{{ $ai['provider'] }}</span></dd>
                    <dt class="col-5 pk-muted fw-semibold">Model (AI_MODEL)</dt><dd class="col-7 mb-0"><span class="pf-act">{{ $ai['model'] }}</span></dd>
                    <dt class="col-5 pk-muted fw-semibold">Endpoint host</dt><dd class="col-7 mb-0">{{ $ai['endpoint'] }}</dd>
                    <dt class="col-5 pk-muted fw-semibold">API key</dt><dd class="col-7 mb-0">{{ $ai['key_set'] ? 'Set (hidden)' : 'Not set' }}</dd>
                    <dt class="col-5 pk-muted fw-semibold">Timeout</dt><dd class="col-7 mb-0">{{ $ai['timeout'] }} s</dd>
                </dl>

                <form method="POST" action="{{ route('platform.manage.integrations.ping') }}" class="mt-3 d-flex flex-wrap align-items-center gap-2">
                    @csrf
                    <button class="btn btn-pk btn-sm" data-busy="Checking…"><i class="bi bi-broadcast me-1"></i> Check reachability</button>
                    <span class="small pk-muted">Sends a one-word health-check prompt — no personal data.</span>
                </form>
                @if($ping)
                    <div class="alert {{ $ping['ok'] ? 'alert-success' : 'alert-warning' }} mt-3 mb-0 small d-flex gap-2 fade-up" role="status">
                        <i class="bi {{ $ping['ok'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }} mt-1"></i>
                        <div><strong>{{ $ping['ok'] ? 'Reachable' : 'Not reachable' }}</strong> · {{ $ping['message'] }} @if($ping['ms'] !== null)({{ $ping['ms'] }} ms)@endif <span class="pk-muted">at {{ $ping['at'] }}</span></div>
                    </div>
                @endif
            </div>
        </section>

        <section class="pk-card pf-panel mt-3 reveal" aria-labelledby="gemH">
            <div class="pf-panel-head"><h2 id="gemH"><i class="bi bi-terminal text-primary"></i> Enable Gemma locally</h2><span class="pf-badge info">Free · offline</span></div>
            <div class="pf-panel-body small">
                <ol class="ps-3 mb-3 d-grid gap-2">
                    <li>Install <strong>Ollama</strong> on this machine (ollama.com — one-time download).</li>
                    <li>Pull a Gemma model: <code class="pf-act">ollama pull gemma3:4b</code> (≈3 GB; use <code class="pf-act">gemma3:1b</code> on low-memory laptops).</li>
                    <li>In the project <code>.env</code> set:
                        <pre class="pf-mono bg-light border rounded-3 p-3 mt-2 mb-0">AI_PROVIDER=ollama
AI_MODEL=gemma3:4b
AI_BASE_URL=http://127.0.0.1:11434</pre></li>
                    <li>Run <code class="pf-act">php artisan config:clear</code>, reload this page and press <em>Check reachability</em>.</li>
                </ol>
                <p class="mb-2"><strong>Other OpenAI-compatible servers</strong> (LM Studio, llama.cpp server, vLLM, a hosted Gemma API): set <code>AI_PROVIDER=openai_compatible</code>, <code>AI_BASE_URL</code> (ending in <code>/v1</code>), <code>AI_MODEL</code> and, if required, <code>AI_API_KEY</code>. Keys live only in <code>.env</code>.</p>
                <p class="mb-0 pk-muted"><i class="bi bi-shield-check me-1"></i>Callers never send names, emails, phones or tokens to the model, validate every JSON reply, and fall back to local logic whenever AI is off or fails.</p>
            </div>
        </section>
    </div>
    <div class="col-xl-5">
        <section class="pk-card pf-panel reveal" style="--d:.06s" aria-labelledby="intH">
            <div class="pf-panel-head"><h2 id="intH"><i class="bi bi-diagram-3 text-primary"></i> Other integrations</h2></div>
            @foreach($integrations as [$name, $icon, $state, $desc])
                <div class="d-flex gap-3 px-3 px-md-4 py-3 border-top" style="border-color:#f0f1f7 !important">
                    <span class="pk-icon {{ $state === 'active' ? 'green' : '' }}" style="width:36px;height:36px;font-size:.95rem"><i class="bi {{ $icon }}"></i></span>
                    <div class="min-w-0">
                        <div class="fw-bold d-flex align-items-center gap-2 flex-wrap">{{ $name }} @if($state === 'active')<span class="pf-badge ok">Active</span>@else<span class="pf-badge muted">Not configured</span>@endif</div>
                        <div class="small pk-muted">{{ $desc }}</div>
                    </div>
                </div>
            @endforeach
        </section>
    </div>
</div>
@endsection
