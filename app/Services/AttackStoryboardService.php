<?php

namespace App\Services;

use App\Models\AssetGroup;
use App\Models\Finding;
use Illuminate\Support\Collection;

class AttackStoryboardService
{
    public const PHASE_RECONNAISSANCE = 'reconnaissance';

    public const PHASE_PROBING = 'probing';

    public const PHASE_EXPLOITATION = 'exploitation';

    public const PHASE_COMPROMISE = 'compromise';

    public const PHASE_IMPACT = 'impact';

    public const PHASES = [
        self::PHASE_RECONNAISSANCE => [
            'label' => 'Reconnaissance',
            'short' => 'Recon',
            'icon' => 'radar',
            'hint' => 'Bagaimana penyerang menemukan target Anda.',
        ],
        self::PHASE_PROBING => [
            'label' => 'Probing',
            'short' => 'Probe',
            'icon' => 'probe',
            'hint' => 'Permukaan yang bisa disentuh penyerang.',
        ],
        self::PHASE_EXPLOITATION => [
            'label' => 'Exploitation',
            'short' => 'Exploit',
            'icon' => 'exploit',
            'hint' => 'Kelemahan yang bisa dipakai untuk masuk.',
        ],
        self::PHASE_COMPROMISE => [
            'label' => 'Compromise',
            'short' => 'Inside',
            'icon' => 'compromise',
            'hint' => 'Tanda situs sudah benar-benar disusupi.',
        ],
        self::PHASE_IMPACT => [
            'label' => 'Impact',
            'short' => 'Impact',
            'icon' => 'impact',
            'hint' => 'Seberapa parah akibatnya bagi Anda.',
        ],
    ];

    /**
     * Build a visual attack storyboard for a group based on open findings.
     *
     * @return array{
     *     phases: array<int, array{key: string, label: string, short: string, icon: string, hint: string, count: int, findings: Collection<int, Finding>}>,
     *     narrative: array<int, string>,
     *     totalFindings: int,
     *     worstSeverity: ?string
     * }
     */
    public function build(AssetGroup $group): array
    {
        $findings = $group->findings()
            ->with('asset')
            ->where('status', Finding::STATUS_OPEN)
            ->get();

        $grouped = $findings->groupBy(fn (Finding $f): string => $this->phaseFor($f));

        $phases = [];
        foreach (array_keys(self::PHASES) as $key) {
            $phaseFindings = $grouped->get($key, collect());

            $phases[] = [
                'key' => $key,
                'label' => self::PHASES[$key]['label'],
                'short' => self::PHASES[$key]['short'],
                'icon' => self::PHASES[$key]['icon'],
                'hint' => self::PHASES[$key]['hint'],
                'count' => $phaseFindings->count(),
                'findings' => $phaseFindings->sortByDesc(fn (Finding $f): int => array_search($f->severity, Finding::SEVERITIES, true))->values(),
            ];
        }

        return [
            'phases' => $phases,
            'narrative' => $this->narrative($phases, $group),
            'totalFindings' => $findings->count(),
            'worstSeverity' => $findings->map(fn (Finding $f): string => $f->severity)->unique()->map(fn (string $s): int => array_search($s, Finding::SEVERITIES, true))->max() !== null
                ? Finding::SEVERITIES[$findings->map(fn (Finding $f): string => $f->severity)->unique()->map(fn (string $s): int => array_search($s, Finding::SEVERITIES, true))->max()] ?? null
                : null,
        ];
    }

    /**
     * Map a finding to an attack phase using its kind and category.
     */
    public function phaseFor(Finding $finding): string
    {
        $category = strtolower((string) $finding->category);
        $title = strtolower((string) $finding->title);

        // Compromise: live incident signals are the clearest evidence of a takeover.
        if ($finding->kind === Finding::KIND_INCIDENT) {
            return self::PHASE_COMPROMISE;
        }

        // Reconnaissance: discovery of the surface (DNS, subdomains, ports, services).
        if (str_contains($category, 'port')
            || str_contains($category, 'dns')
            || str_contains($category, 'subdomain')
            || str_contains($category, 'service')
            || str_contains($category, 'fingerprint')
            || str_contains($title, 'subdomain')
            || str_contains($title, 'port')
        ) {
            return self::PHASE_RECONNAISSANCE;
        }

        // Probing: exposed paths and information disclosure.
        if (str_contains($category, 'exposure')
            || str_contains($category, 'path')
            || str_contains($title, '.git')
            || str_contains($title, '.env')
            || str_contains($title, 'phpinfo')
            || str_contains($title, 'swagger')
            || str_contains($title, 'ignition')
        ) {
            return self::PHASE_PROBING;
        }

        // Exploitation: weaknesses an attacker can leverage.
        if (str_contains($category, 'header')
            || str_contains($category, 'injection')
            || str_contains($category, 'sql')
            || str_contains($category, 'cve')
            || str_contains($category, 'misconfig')
            || str_contains($category, 'credential')
            || str_contains($category, 'weak')
        ) {
            return self::PHASE_EXPLOITATION;
        }

        // Anything ambiguous lands in exploitation unless it is clearly impact-sized.
        if ($finding->severity === Finding::SEVERITY_CRITICAL) {
            return self::PHASE_IMPACT;
        }

        return self::PHASE_EXPLOITATION;
    }

    /**
     * Build a readable attack narrative from the phases.
     *
     * @param  array<int, array{key: string, label: string, short: string, icon: string, hint: string, count: int, findings: Collection<int, Finding>}>  $phases
     * @return array<int, string>
     */
    protected function narrative(array $phases, AssetGroup $group): array
    {
        $lines = [];
        $recon = $phases[0]['findings'];
        $probe = $phases[1]['findings'];
        $exploit = $phases[2]['findings'];
        $compromise = $phases[3]['findings'];
        $impact = $phases[4]['findings'];

        if ($recon->isNotEmpty()) {
            $hosts = $recon->map(fn (Finding $f): string => $f->asset?->hostname ?: $group->root_domain)->unique()->take(3)->implode(', ');
            $lines[] = "Penyerang memetakan permukaan {$group->root_domain} melalui {$recon->count()} sinyal penemuan ({$hosts}).";
        } else {
            $lines[] = "Tidak ada sinyal reconnaissance terbuka yang terdeteksi untuk {$group->root_domain}.";
        }

        if ($probe->isNotEmpty()) {
            $lines[] = "Kemudian mereka menguji titik masuk: {$probe->count()} jalur terekspos terbuka (mis. {$probe->first()->title}).";
        }

        if ($exploit->isNotEmpty()) {
            $lines[] = "Pada fase exploitation, {$exploit->count()} kelemahan dapat dieksploitasi — termasuk {$exploit->first()->title}.";
        }

        if ($compromise->isNotEmpty()) {
            $names = $compromise->map(fn (Finding $f): string => $f->asset?->hostname ?: $group->root_domain)->unique()->take(3)->implode(', ');
            $lines[] = "Sinyal kompromi aktif ditemukan di {$names}: {$compromise->count()} insiden (mis. {$compromise->first()->title}).";
        } else {
            $lines[] = 'Belum ada sinyal kompromi aktif — tetapi permukaan tetap terbuka selama temuan belum dituntaskan.';
        }

        if ($impact->isNotEmpty()) {
            $lines[] = "Dampak dinilai {$impact->count()} temuan bernilai tinggi/kritis pada rating kelompok.";
        }

        return $lines;
    }
}
