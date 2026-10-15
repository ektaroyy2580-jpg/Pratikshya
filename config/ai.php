<?php

/*
| AI provider configuration. Everything is optional: with AI_PROVIDER=none the
| app uses local fallbacks (question bank, rule-based feedback, template
| explanations) and keeps working.
|
| Gemma locally (recommended, free, offline):
|   1. Install Ollama, then: ollama pull gemma3:4b
|   2. .env: AI_PROVIDER=ollama  AI_MODEL=gemma3:4b
|
| Any OpenAI-compatible server (LM Studio, llama.cpp server, vLLM, a hosted Gemma API):
|   .env: AI_PROVIDER=openai_compatible  AI_BASE_URL=http://127.0.0.1:1234/v1  AI_MODEL=gemma-3-4b-it  AI_API_KEY=(if required)
*/

return [
    'provider' => env('AI_PROVIDER', 'none'),           // none | ollama | openai_compatible
    'model' => env('AI_MODEL', 'gemma3:4b'),
    'base_url' => env('AI_BASE_URL', 'http://127.0.0.1:11434'),
    'api_key' => env('AI_API_KEY'),                      // never hardcode; read from .env only
    'timeout' => (int) env('AI_TIMEOUT', 20),
    'temperature' => (float) env('AI_TEMPERATURE', 0.4),
];
