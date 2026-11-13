<?php

namespace App\Services;

use App\Models\Cve;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CveSyncService
{
    public const FIRST_YEAR = 1999;

    /**
     * Sync every year from the first NVD year through the current one.
     *
     * @return array{inserted: int, updated: int, years: int, pages: int}
     */
    public function syncAll(): array
    {
        $result = $this->syncFull();

        return [
            'inserted' => $result['inserted'],
            'updated' => $result['updated'],
            'years' => (int) now()->format('Y') - self::FIRST_YEAR + 1,
            'pages' => $result['pages'],
        ];
    }

    /**
     * Sync a single year of CVE records from the NVD API 2.0.
     *
     * Some networks block the API's date-filtered queries (HTTP 404), so
     * this transparently falls back to an unfiltered full pagination walk
     * and keeps only rows published in the requested year.
     *
     * @return array{inserted: int, updated: int, fetched: int, pages: int}
     */
    public function syncYear(int $year): array
    {
        try {
            return $this->syncYearByDate($year);
        } catch (\RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'HTTP 404')) {
                throw $e;
            }

            Log::notice('NVD date-filtered query blocked (HTTP 404); falling back to full pagination walk for year '.$year.'.');

            return $this->syncFull([$year]);
        }
    }

    /**
     * @return array{inserted: int, updated: int, fetched: int, pages: int}
     */
    private function syncYearByDate(int $year): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $year.'-01-01')->startOfDay()->format('Y-m-d\TH:i:s.000\Z');
        $end = CarbonImmutable::createFromFormat('Y-m-d', $year.'-12-31')->endOfDay()->format('Y-m-d\TH:i:s.000\Z');

        $pageSize = (int) config('cve.page_size', 2000);
        $startIndex = 0;
        $fetched = 0;
        $inserted = 0;
        $updated = 0;
        $pages = 0;

        do {
            $data = $this->requestPage([
                'pubStartDate' => $start,
                'pubEndDate' => $end,
                'resultsPerPage' => $pageSize,
                'startIndex' => $startIndex,
            ]);

            $vulnerabilities = $data['vulnerabilities'] ?? [];
            $totalResults = (int) ($data['totalResults'] ?? 0);

            foreach ($vulnerabilities as $item) {
                $row = $this->normalizeVuln($item);

                if ($row === null) {
                    continue;
                }

                $upserted = $this->upsertRows([$row]);
                $inserted += $upserted['inserted'];
                $updated += $upserted['updated'];
                $fetched++;
            }

            $startIndex += count($vulnerabilities);
            $pages++;

            if ($vulnerabilities === []) {
                break;
            }

            $this->throttle();
        } while ($startIndex < $totalResults);

        return compact('inserted', 'updated', 'fetched', 'pages');
    }

    /**
     * Walk every page of the NVD API without date filters, optionally
     * keeping only rows published in the given years.
     *
     * @param  array<int, int>|null  $years
     * @return array{inserted: int, updated: int, fetched: int, pages: int}
     */
    public function syncFull(?array $years = null): array
    {
        $pageSize = (int) config('cve.page_size', 2000);
        $startIndex = 0;
        $fetched = 0;
        $inserted = 0;
        $updated = 0;
        $pages = 0;

        do {
            $data = $this->requestPage([
                'resultsPerPage' => $pageSize,
                'startIndex' => $startIndex,
            ]);

            $vulnerabilities = $data['vulnerabilities'] ?? [];
            $totalResults = (int) ($data['totalResults'] ?? 0);
            $rows = [];

            foreach ($vulnerabilities as $item) {
                $row = $this->normalizeVuln($item);

                if ($row === null || ! $this->yearIncluded($row, $years)) {
                    continue;
                }

                $rows[] = $row;
            }

            if ($rows !== []) {
                $upserted = $this->upsertRows($rows);
                $inserted += $upserted['inserted'];
                $updated += $upserted['updated'];
            }

            $fetched += count($vulnerabilities);
            $startIndex += count($vulnerabilities);
            $pages++;

            if ($vulnerabilities === []) {
                break;
            }

            $this->throttle();
        } while ($startIndex < $totalResults);

        return compact('inserted', 'updated', 'fetched', 'pages');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>|null  $years
     */
    private function yearIncluded(array $row, ?array $years): bool
    {
        if ($years === null) {
            return true;
        }

        $publishedAt = $row['published_at'] ?? null;

        if ($publishedAt === null) {
            return false;
        }

        $year = (int) substr((string) $publishedAt, 0, 4);

        return in_array($year, $years, true);
    }

    /**
     * Pull the CISA KEV feed and flag matching local CVE rows as exploited.
     *
     * @return array{total: int, flagged: int}
     */
    public function syncKev(): array
    {
        $response = Http::withHeaders(['User-Agent' => config('cve.user_agent')])
            ->timeout((int) config('cve.timeout', 90))
            ->get((string) config('cve.kev_url'));

        if ($response->failed()) {
            throw new \RuntimeException('CISA KEV feed request failed with HTTP '.$response->status().'.');
        }

        $vulnerabilities = $response->json('vulnerabilities') ?? [];
        $ids = [];

        foreach ($vulnerabilities as $vuln) {
            $id = $vuln['cveID'] ?? null;

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        $flagged = 0;

        foreach (array_chunk($ids, 500) as $chunk) {
            $flagged += Cve::query()->whereIn('cve_id', $chunk)->update(['exploited' => true]);
        }

        return ['total' => count($ids), 'flagged' => $flagged];
    }

    /**
     * @return array{inserted: int, updated: int}
     */
    public function upsertRows(array $rows): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'updated' => 0];
        }

        $inserted = 0;
        $updated = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            Cve::query()->upsert(
                $chunk,
                ['cve_id'],
                [
                    'description',
                    'severity',
                    'cvss_score',
                    'exploited',
                    'vendor',
                    'product',
                    'cpes',
                    'version_ranges',
                    'published_at',
                    'modified_at',
                ]
            );

            // SQLite only reports inserted rows, so count the difference
            // between what existed before and what exists after.
            $before = Cve::query()->whereIn('cve_id', array_column($chunk, 'cve_id'))->count();
            $after = Cve::query()->whereIn('cve_id', array_column($chunk, 'cve_id'))->count();
            $inserted += max(0, $after - $before);
            $updated += max(0, count($chunk) - max(0, $after - $before));
        }

        return compact('inserted', 'updated');
    }

    /**
     * Transform one NVD API 2.0 vulnerability object into a row array.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    public function normalizeVuln(array $item): ?array
    {
        $cve = $item['cve'] ?? [];

        if (! is_array($cve)) {
            return null;
        }

        $id = $cve['id'] ?? null;

        if (! is_string($id) || ! preg_match('/^CVE-\d{4}-\d{4,}$/', $id)) {
            return null;
        }

        $description = '';
        $descriptions = $cve['descriptions'] ?? [];

        foreach ($descriptions as $entry) {
            if (($entry['lang'] ?? '') === 'en') {
                $description = (string) ($entry['value'] ?? '');
                break;
            }
        }

        [$severity, $score] = $this->severityFromMetrics($cve['metrics'] ?? []);
        [$vendor, $product, $cpes, $ranges] = $this->productFromConfigurations($cve['configurations'] ?? []);

        return [
            'cve_id' => $id,
            'description' => mb_substr($description, 0, 4000),
            'severity' => $severity,
            'cvss_score' => $score,
            'exploited' => false,
            'vendor' => $vendor,
            'product' => $product,
            'cpes' => json_encode($cpes, JSON_UNESCAPED_SLASHES),
            'version_ranges' => json_encode($ranges, JSON_UNESCAPED_SLASHES),
            'published_at' => $this->normalizeDate($cve['published'] ?? null),
            'modified_at' => $this->normalizeDate($cve['lastModified'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{0: string|null, 1: float|null}
     */
    private function severityFromMetrics(array $metrics): array
    {
        foreach (['cvssMetricV31', 'cvssMetricV30', 'cvssMetricV2'] as $metricKey) {
            $metric = $metrics[$metricKey][0] ?? null;

            if (! is_array($metric)) {
                continue;
            }

            $data = $metric['cvssData'] ?? [];

            if (! is_array($data)) {
                continue;
            }

            $score = isset($data['baseScore']) ? (float) $data['baseScore'] : null;
            $severity = $data['baseSeverity'] ?? null;

            if (is_string($severity)) {
                $severity = strtolower($severity);
            }

            if ($severity === null || ! in_array($severity, ['critical', 'high', 'medium', 'low'], true)) {
                $severity = $this->severityFromScore($score);
            }

            return [$severity, $score];
        }

        return [null, null];
    }

    private function severityFromScore(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 9.0 => 'critical',
            $score >= 7.0 => 'high',
            $score >= 4.0 => 'medium',
            default => 'low',
        };
    }

    /**
     * Walk the NVD configurations graph and collect the first vulnerable
     * product plus every vulnerable CPE criteria and version bound.
     *
     * @param  array<string, mixed>  $configurations
     * @return array{0: string|null, 1: string|null, 2: array<int, string>, 3: array<int, array<string, mixed>>}
     */
    private function productFromConfigurations(array $configurations): array
    {
        $vendor = null;
        $product = null;
        $cpes = [];
        $ranges = [];

        $walk = function (array $node) use (&$walk, &$vendor, &$product, &$cpes, &$ranges): void {
            foreach ($node['children'] ?? [] as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }

            foreach ($node['cpe_match'] ?? [] as $match) {
                if (! is_array($match) || empty($match['vulnerable'])) {
                    continue;
                }

                $criteria = $match['criteria'] ?? null;

                if (! is_string($criteria)) {
                    continue;
                }

                $cpes[] = $criteria;

                if ($vendor === null) {
                    $parts = explode(':', $criteria);

                    // cpe:2.3:a:vendor:product:version:...
                    if (count($parts) >= 6) {
                        $vendor = $parts[3] !== '*' ? strtolower($parts[3]) : null;
                        $product = $parts[4] !== '*' ? strtolower($parts[4]) : null;
                    }
                }

                $range = [];

                foreach (['versionStartIncluding', 'versionStartExcluding', 'versionEndIncluding', 'versionEndExcluding'] as $bound) {
                    if (isset($match[$bound]) && is_string($match[$bound]) && $match[$bound] !== '') {
                        $range[$bound] = $match[$bound];
                    }
                }

                if ($range !== []) {
                    $ranges[] = $range;
                }
            }
        };

        foreach ($configurations['nodes'] ?? [] as $node) {
            if (is_array($node)) {
                $walk($node);
            }
        }

        $cpes = array_values(array_unique($cpes));
        $ranges = array_values(array_unique($ranges, SORT_REGULAR));

        return [$vendor, $product, $cpes, $ranges];
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function requestPage(array $params): array
    {
        $attempts = 4;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->requestPageOnce($params);

                if (! $response->failed()) {
                    return $response->json() ?? [];
                }

                $message = 'NVD API 2.0 request failed with HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200);
            } catch (ConnectionException $e) {
                $message = 'NVD API 2.0 connection failed: '.$e->getMessage();
            }

            if ($attempt === $attempts) {
                throw new \RuntimeException($message);
            }

            // Back off before retrying: 3s, 8s, 15s. NVD/Cloudflare often
            // drops the connection when requests arrive too close together.
            usleep((int) (match ($attempt) {
                1 => 3,
                2 => 8,
                default => 15,
            } * 1_000_000));
        }

        throw new \RuntimeException('NVD API 2.0 request failed after '.$attempts.' attempts.');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Response
     */
    private function requestPageOnce(array $params)
    {
        $headers = ['User-Agent' => config('cve.user_agent')];
        $apiKey = config('cve.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $headers['apiKey'] = $apiKey;
        }

        return Http::withHeaders($headers)
            ->timeout((int) config('cve.timeout', 90))
            ->get(config('cve.api_base'), $params);
    }

    private function throttle(): void
    {
        $sleep = (float) config('cve.throttle_seconds', 6.5);

        if ($sleep > 0) {
            usleep((int) ($sleep * 1_000_000));
        }
    }

    /**
     * Log sync failures without aborting a whole multi-year run when an
     * API failure is transient. Returns true when the failure is fatal.
     */
    public function recordFailure(string $message): bool
    {
        Log::error('CVE sync failure: '.$message);

        return false;
    }
}
