<?php

namespace App\Services;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiService
{
    private const MODEL_POOL_CACHE_KEY = 'openrouter_model_pool';

    private const MODEL_POOL_TTL_SECONDS = 21600; // 6 hours

    private const MAX_ROTATION_ATTEMPTS = 30;

    public function __construct(
        protected RemediationAdvisor $advisor,
        protected SecurityRatingService $ratingService
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     provider: ?string,
     *     api_key: ?string,
     *     base_url: string,
     *     model: string
     * }
     */
    public function resolveConfig(?User $user = null, array $overrides = []): array
    {
        $provider = $overrides['ai_provider']
            ?? $user?->ai_provider
            ?? config('ai.provider')
            ?? config('services.ai.provider')
            ?? env('AI_PROVIDER');

        $apiKey = $overrides['ai_api_key']
            ?? $user?->ai_api_key
            ?? config('ai.api_key')
            ?? config('services.ai.api_key')
            ?? env('AI_API_KEY');

        $baseUrl = $overrides['ai_base_url']
            ?? $user?->ai_base_url
            ?? config('ai.base_url')
            ?? config('services.ai.base_url')
            ?? env('AI_BASE_URL');

        $model = $overrides['ai_model']
            ?? $user?->ai_model
            ?? config('ai.model')
            ?? config('services.ai.model')
            ?? env('AI_MODEL');

        $cleanProvider = $provider ? strtolower(trim((string) $provider)) : null;

        if (! $baseUrl) {
            $baseUrl = match ($cleanProvider) {
                'anthropic' => 'https://api.anthropic.com',
                '9router' => 'https://9router.net/v1',
                default => 'https://api.openai.com/v1',
            };
        }

        if (! $model) {
            $model = match ($cleanProvider) {
                'anthropic' => 'claude-3-5-haiku-20241022',
                '9router' => 'gpt-4o-mini',
                default => 'gpt-4o-mini',
            };
        }

        return [
            'provider' => $cleanProvider,
            'api_key' => $apiKey ? trim((string) $apiKey) : null,
            'base_url' => rtrim((string) $baseUrl, '/'),
            'model' => trim((string) $model),
        ];
    }

    /**
     * @return array{
     *     analysis: string,
     *     root_cause: string,
     *     remediation: array<int, string>,
     *     commands: array<int, string>,
     *     config_snippet: ?string,
     *     verification: string,
     *     source: string
     * }
     */
    public function explainFinding(Finding $finding, ?User $user = null): array
    {
        $fallback = $this->fallbackFindingExplanation($finding);
        $config = $this->resolveConfig($user);

        if (! $config['provider'] || ! $config['api_key']) {
            return $fallback;
        }

        $systemPrompt = 'You are a Senior Security Analyst at GardaCyber. Provide root-cause analysis and actionable remediation commands for the observed security finding. Respond ONLY with a valid JSON object with keys: "analysis" (string), "root_cause" (string), "remediation" (array of strings), "commands" (array of shell or server config command strings), "config_snippet" (optional string), "verification" (string).';

        $userPrompt = 'Target Host: '.($finding->asset?->hostname ?: 'unknown')."\n"
            ."Title: {$finding->title}\n"
            ."Category: {$finding->category}\n"
            ."Kind: {$finding->kind}\n"
            ."Severity: {$finding->severity}\n"
            ."Description: {$finding->description}\n"
            ."Observed Evidence: {$finding->evidence}";

        try {
            $json = $this->callProvider($config, $systemPrompt, $userPrompt);

            if ($json && is_array($json)) {
                return [
                    'analysis' => (string) ($json['analysis'] ?? $fallback['analysis']),
                    'root_cause' => (string) ($json['root_cause'] ?? $fallback['root_cause']),
                    'remediation' => is_array($json['remediation'] ?? null)
                        ? array_values(array_map('strval', $json['remediation']))
                        : $fallback['remediation'],
                    'commands' => is_array($json['commands'] ?? null)
                        ? array_values(array_map('strval', $json['commands']))
                        : $fallback['commands'],
                    'config_snippet' => isset($json['config_snippet']) ? (string) $json['config_snippet'] : $fallback['config_snippet'],
                    'verification' => (string) ($json['verification'] ?? $fallback['verification']),
                    'source' => 'ai',
                ];
            }
        } catch (Throwable) {
            // Automatic safe fallback when unconfigured, timed out, or offline
        }

        return $fallback;
    }

    /**
     * @return array{
     *     summary: string,
     *     risk_assessment: string,
     *     action_items: array<int, string>,
     *     density: float,
     *     ratings: array{vuln: array{score: int, grade: string}, incident: array{score: int, grade: string}},
     *     source: string
     * }
     */
    public function summarizeGroup(AssetGroup $group, ?User $user = null): array
    {
        $group->loadMissing(['assets', 'findings']);
        $openFindings = $group->findings->where('status', Finding::STATUS_OPEN);
        $assetCount = max(1, $group->assets->count());
        $findingCount = $openFindings->count();
        $density = round($findingCount / $assetCount, 2);
        $ratings = $this->ratingService->rateGroup($group);

        $fallback = $this->fallbackGroupSummary($group, $openFindings, $assetCount, $density, $ratings);
        $config = $this->resolveConfig($user);

        if (! $config['provider'] || ! $config['api_key']) {
            return $fallback;
        }

        $systemPrompt = 'You are an Executive Cyber Risk Advisor at GardaCyber. Generate an executive perimeter posture summary, density-weighted risk assessment, and top 3 immediate action items for the target asset group. Respond ONLY with a valid JSON object with keys: "summary" (string), "risk_assessment" (string), "action_items" (array of exactly 3 strings).';

        $topFindings = $openFindings->sortByDesc(fn (Finding $f): int => match ($f->severity) {
            Finding::SEVERITY_CRITICAL => 4,
            Finding::SEVERITY_HIGH => 3,
            Finding::SEVERITY_MEDIUM => 2,
            default => 1,
        })->take(5)->map(fn (Finding $f): string => "[{$f->severity}/{$f->kind}] {$f->title} ({$f->category})")->values()->all();

        $userPrompt = "Root Domain: {$group->root_domain}\n"
            ."Assets Count: {$assetCount}\n"
            ."Open Findings Count: {$findingCount}\n"
            ."Finding Density: {$density} issues per asset\n"
            ."Vulnerability Rating: Grade {$ratings['vuln']['grade']} (Score: {$ratings['vuln']['score']}/100)\n"
            ."Incident Rating: Grade {$ratings['incident']['grade']} (Score: {$ratings['incident']['score']}/100)\n"
            ."Top Observations:\n".implode("\n", $topFindings);

        try {
            $json = $this->callProvider($config, $systemPrompt, $userPrompt);

            if ($json && is_array($json) && ! empty($json['summary'])) {
                $actions = is_array($json['action_items'] ?? null)
                    ? array_values(array_map('strval', $json['action_items']))
                    : $fallback['action_items'];

                return [
                    'summary' => (string) ($json['summary'] ?? $fallback['summary']),
                    'risk_assessment' => (string) ($json['risk_assessment'] ?? $fallback['risk_assessment']),
                    'action_items' => array_slice($actions, 0, 3),
                    'density' => $density,
                    'ratings' => $ratings,
                    'source' => 'ai',
                ];
            }
        } catch (Throwable) {
            // Automatic safe fallback when unconfigured, timed out, or offline
        }

        return $fallback;
    }

    /**
     * Chat with the AI about the user's workspace. Answers in Indonesian by default.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, source: string}
     */
    public function chat(User $user, string $message, array $workspaceContext, array $history = []): array
    {
        $config = $this->resolveConfig($user);

        $fallbackReply = 'Maaf, asisten AI belum terkonfigurasi atau sedang tidak tersedia. Saya tetap bisa membantu lewat data di halaman ini — coba tanya lebih spesifik, misalnya "temuan kritis apa saja yang terbuka?" atau "grup mana yang paling berisiko?".';

        if (! $config['provider'] || ! $config['api_key']) {
            return ['reply' => $fallbackReply, 'source' => 'unconfigured'];
        }

        $systemPrompt = <<<'PROMPT'
Kamu adalah "Garda Copilot", asisten keamanan siber di GardaCyber — platform yang menampilkan hasil scan attack surface (grup aset, temuan kerentanan, insiden, dan rating A–F).

Aturan menjawab:
1. Selalu jawab dalam Bahasa Indonesia, kecuali pengguna meminta bahasa lain.
2. Gunakan data konteks workspace yang tersedia di bawah ini sebagai sumber utama. Jangan mengarang fakta yang tidak ada di konteks.
3. Jika pertanyaan di luar data yang tersedia, jawab singkat dan jujur bahwa kamu tidak punya datanya, lalu sarankan apa yang bisa dilakukan (mis. scan baru).
4. Jawab padat, langsung ke inti, tidak bertele-tele. Pakai bullet point bila membantu.
5. Jika pengguna bertanya tentang cara memperbaiki temuan, berikan langkah praktis dan aman (jangan perintah destruktif tanpa verifikasi).
PROMPT;

        $contextText = $this->workspaceContextToText($workspaceContext);
        $historyText = $this->historyToText($history);

        $userPrompt = "Pertanyaan pengguna:\n{$message}\n\n--- KONTEKS WORKSPACE ---\n{$contextText}";
        if ($historyText !== '') {
            $userPrompt .= "\n\n--- RIWAYAT PERCAKAPAN ---\n{$historyText}";
        }

        try {
            $json = $this->callProviderWithRotation(
                $config,
                $systemPrompt,
                $userPrompt,
            );

            if (is_array($json) && isset($json['reply'])) {
                return [
                    'reply' => (string) $json['reply'],
                    'source' => 'ai',
                ];
            }
        } catch (Throwable) {
            // fall through to fallback
        }

        return ['reply' => $fallbackReply, 'source' => 'fallback'];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    protected function historyToText(array $history): string
    {
        $lines = [];
        foreach (array_slice($history, -8) as $entry) {
            $role = ($entry['role'] ?? 'user') === 'assistant' ? 'Asisten' : 'Pengguna';
            $lines[] = "{$role}: ".((string) ($entry['content'] ?? ''));
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $workspaceContext
     */
    protected function workspaceContextToText(array $workspaceContext): string
    {
        $lines = [];

        $groups = $workspaceContext['groups'] ?? [];
        if (is_array($groups) && count($groups) > 0) {
            $lines[] = 'GRUP ASET:';
            foreach ($groups as $group) {
                $lines[] = sprintf(
                    '- %s (%d aset, %d vuln terbuka, %d insiden terbuka, rating vuln %s, rating insiden %s, scan terakhir: %s)',
                    $group['root_domain'] ?? '?',
                    $group['assets_count'] ?? 0,
                    $group['open_vulns'] ?? 0,
                    $group['open_incidents'] ?? 0,
                    $group['vuln_grade'] ?? '?',
                    $group['incident_grade'] ?? '?',
                    $group['last_scanned_at'] ?? 'belum pernah',
                );
            }
        } else {
            $lines[] = 'GRUP ASET: (belum ada grup)';
        }

        $findings = $workspaceContext['findings'] ?? [];
        if (is_array($findings) && count($findings) > 0) {
            $lines[] = 'TEMUAN TERBUKA (contoh):';
            foreach ($findings as $finding) {
                $lines[] = sprintf(
                    '- [%s] %s | kategori: %s | host: %s | status: %s',
                    $finding['severity'] ?? '?',
                    $finding['title'] ?? '?',
                    $finding['category'] ?? '?',
                    $finding['hostname'] ?? '?',
                    $finding['status'] ?? 'open',
                );
            }
        } else {
            $lines[] = 'TEMUAN TERBUKA: (tidak ada)';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     success: bool,
     *     message: string,
     *     provider?: ?string,
     *     model?: string
     * }
     */
    public function testConnection(?User $user = null, array $overrides = []): array
    {
        $config = $this->resolveConfig($user, $overrides);

        if (! $config['provider'] || ! $config['api_key']) {
            return [
                'success' => false,
                'message' => 'AI provider and API key are required to test connection.',
            ];
        }

        try {
            $this->callProvider($config, 'You are a test ping agent.', 'Ping. Respond with: {"pong": true}');

            return [
                'success' => true,
                'message' => "Connection to {$config['provider']} successful.",
                'provider' => $config['provider'],
                'model' => $config['model'],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'AI connection failed: '.$e->getMessage(),
                'provider' => $config['provider'],
                'model' => $config['model'],
            ];
        }
    }

    /**
     * @param  array{provider: ?string, api_key: ?string, base_url: string, model: string}  $config
     * @return array<string, mixed>|null
     */
    protected function callProvider(array $config, string $systemPrompt, string $userPrompt): ?array
    {
        // OpenRouter: rotate through available models so quota/rate-limit failures on
        // one model automatically fall through to the next one.
        if ($config['provider'] === 'openrouter') {
            return $this->callProviderWithRotation($config, $systemPrompt, $userPrompt);
        }

        return $this->callSingleProvider($config, $systemPrompt, $userPrompt);
    }

    /**
     * Try each model in rotation until one returns a usable JSON payload.
     * Falls back to the configured model when the rotating list is unavailable.
     *
     * @param  array{provider: ?string, api_key: ?string, base_url: string, model: string}  $config
     * @return array<string, mixed>|null
     */
    protected function callProviderWithRotation(array $config, string $systemPrompt, string $userPrompt): ?array
    {
        $models = $this->openRouterModels();
        $configured = trim((string) $config['model']);

        if (empty($models)) {
            return $this->callSingleProvider($config, $systemPrompt, $userPrompt);
        }

        // Start rotation from a rotating offset so concurrent calls spread across models.
        $count = count($models);
        $offset = (int) Cache::increment('ai_model_rotation_offset');
        if ($offset <= 0) {
            $offset = random_int(0, $count - 1);
        }
        $offset %= $count;
        $rotated = array_merge(
            array_slice($models, $offset),
            array_slice($models, 0, $offset)
        );

        // If a specific model is configured, try it first.
        if ($configured && $configured !== '*') {
            $rotated = array_values(array_unique(array_merge([$configured], $rotated)));
        }

        $attempts = min(self::MAX_ROTATION_ATTEMPTS, count($rotated));

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $candidate = $rotated[$attempt];

            try {
                $result = $this->callSingleProvider(
                    array_merge($config, ['model' => $candidate]),
                    $systemPrompt,
                    $userPrompt,
                );

                if (is_array($result)) {
                    return $result;
                }
            } catch (Throwable) {
                // Try the next model.
            }
        }

        return null;
    }

    /**
     * Cache-enabled list of usable OpenRouter model IDs (free tiers first).
     * Free models are preferred because the account may lack credit for paid ones.
     *
     * @return array<int, string>
     */
    protected function openRouterModels(): array
    {
        return Cache::remember(self::MODEL_POOL_CACHE_KEY, self::MODEL_POOL_TTL_SECONDS, function (): array {
            try {
                $response = Http::timeout(8)
                    ->connectTimeout(5)
                    ->get('https://openrouter.ai/api/v1/models');

                if (! $response->successful()) {
                    return [];
                }

                $ids = collect($response->json('data') ?? [])
                    ->pluck('id')
                    ->filter(fn ($id): bool => is_string($id) && $id !== '')
                    ->unique()
                    ->values()
                    ->all();

                $free = array_values(array_filter($ids, fn (string $id): bool => str_ends_with($id, ':free')));

                return $free;
            } catch (Throwable) {
                return [];
            }
        });
    }

    /**
     * @param  array{provider: ?string, api_key: ?string, base_url: string, model: string}  $config
     * @return array<string, mixed>|null
     */
    protected function callSingleProvider(array $config, string $systemPrompt, string $userPrompt): ?array
    {
        if ($config['provider'] === 'anthropic') {
            $url = str_ends_with($config['base_url'], '/messages')
                ? $config['base_url']
                : $config['base_url'].'/v1/messages';

            $response = Http::timeout(10)->connectTimeout(5)
                ->withHeaders([
                    'x-api-key' => $config['api_key'],
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post($url, [
                    'model' => $config['model'],
                    'max_tokens' => 1024,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Anthropic API error: '.$response->body());
            }

            $rawContent = $response->json('content.0.text') ?? '';

            return $this->extractJson($rawContent);
        }

        // OpenAI, 9router, and generic OpenAI-compatible APIs
        $url = str_ends_with($config['base_url'], '/chat/completions')
            ? $config['base_url']
            : $config['base_url'].'/chat/completions';

        $response = Http::timeout(10)->connectTimeout(5)
            ->withToken($config['api_key'])
            ->post($url, [
                'model' => $config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("{$config['provider']} API error: ".$response->body());
        }

        $rawContent = $response->json('choices.0.message.content') ?? '';

        return $this->extractJson($rawContent);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractJson(string $text): ?array
    {
        $decoded = json_decode(trim($text), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $matched = json_decode($matches[0], true);
            if (is_array($matched)) {
                return $matched;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     analysis: string,
     *     root_cause: string,
     *     remediation: array<int, string>,
     *     commands: array<int, string>,
     *     config_snippet: ?string,
     *     verification: string,
     *     source: string
     * }
     */
    protected function fallbackFindingExplanation(Finding $finding): array
    {
        $guide = $this->advisor->guideFor($finding);

        $commands = [];
        if (! empty($guide['config_snippet'])) {
            $commands = array_values(array_filter(array_map('trim', explode("\n", $guide['config_snippet'])), fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#')));
        }

        return [
            'analysis' => $guide['summary'],
            'root_cause' => $guide['impact'],
            'remediation' => $guide['steps'],
            'commands' => $commands,
            'config_snippet' => $guide['config_snippet'],
            'verification' => $guide['verification'],
            'source' => 'fallback',
        ];
    }

    /**
     * @param  Collection<int, Finding>  $openFindings
     * @param  array{vuln: array{score: int, grade: string}, incident: array{score: int, grade: string}}  $ratings
     * @return array{
     *     summary: string,
     *     risk_assessment: string,
     *     action_items: array<int, string>,
     *     density: float,
     *     ratings: array{vuln: array{score: int, grade: string}, incident: array{score: int, grade: string}},
     *     source: string
     * }
     */
    protected function fallbackGroupSummary(AssetGroup $group, $openFindings, int $assetCount, float $density, array $ratings): array
    {
        $findingCount = $openFindings->count();
        $critCount = $openFindings->where('severity', Finding::SEVERITY_CRITICAL)->count();
        $highCount = $openFindings->where('severity', Finding::SEVERITY_HIGH)->count();
        $incCount = $openFindings->where('kind', Finding::KIND_INCIDENT)->count();

        $summary = "Perimeter posture for {$group->root_domain}: Vuln grade {$ratings['vuln']['grade']} ({$ratings['vuln']['score']}/100), Incident grade {$ratings['incident']['grade']} ({$ratings['incident']['score']}/100). {$findingCount} open findings across {$assetCount} asset(s).";

        $riskLevel = ($critCount > 0 || $incCount > 0) ? 'CRITICAL' : ($highCount > 0 ? 'HIGH' : ($findingCount > 0 ? 'MODERATE' : 'LOW'));
        $riskAssessment = "Density-weighted risk is evaluated as {$riskLevel} ({$density} findings/asset). Exposure is driven by {$critCount} critical finding(s), {$highCount} high severity issue(s), and {$incCount} active incident signal(s).";

        $topFindings = $openFindings->sortByDesc(fn (Finding $f): int => match ($f->severity) {
            Finding::SEVERITY_CRITICAL => 4,
            Finding::SEVERITY_HIGH => 3,
            Finding::SEVERITY_MEDIUM => 2,
            default => 1,
        })->take(3);

        $actionItems = [];
        foreach ($topFindings as $tf) {
            $actionItems[] = "Remediate {$tf->title} [{$tf->severity}] on ".($tf->asset?->hostname ?: $group->root_domain).'.';
        }

        if (count($actionItems) < 3) {
            $actionItems[] = 'Configure Strict-Transport-Security (HSTS) and Content-Security-Policy across all web services.';
        }
        if (count($actionItems) < 3) {
            $actionItems[] = 'Schedule weekly automated perimeter scans to detect configuration drift and newly registered subdomains.';
        }
        if (count($actionItems) < 3) {
            $actionItems[] = 'Review DNS CNAME pointers to prevent dangling subdomain takeovers.';
        }

        return [
            'summary' => $summary,
            'risk_assessment' => $riskAssessment,
            'action_items' => array_slice($actionItems, 0, 3),
            'density' => $density,
            'ratings' => $ratings,
            'source' => 'fallback',
        ];
    }
}
