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
            'hint' => 'Bagaimana penyerang memetakan dan menemukan target Anda.',
        ],
        self::PHASE_PROBING => [
            'label' => 'Probing',
            'short' => 'Probe',
            'icon' => 'probe',
            'hint' => 'Pencarian titik masuk, jalur terekspos, dan kebocoran metadata.',
        ],
        self::PHASE_EXPLOITATION => [
            'label' => 'Exploitation',
            'short' => 'Exploit',
            'icon' => 'exploit',
            'hint' => 'Kelemahan konfigurasi atau kerentanan yang dieksploitasi penyerang.',
        ],
        self::PHASE_COMPROMISE => [
            'label' => 'Compromise',
            'short' => 'Inside',
            'icon' => 'compromise',
            'hint' => 'Sinyal pengambilalihan, injeksi script liar, atau akses ilegal.',
        ],
        self::PHASE_IMPACT => [
            'label' => 'Impact',
            'short' => 'Impact',
            'icon' => 'impact',
            'hint' => 'Estimasi kerugian finansial, reputasi, dan sanksi regulasi.',
        ],
    ];

    /**
     * Build an interactive visual attack storyboard with kill-chain simulation and auto-remediation.
     *
     * @return array{
     *     phases: array<int, array{key: string, label: string, short: string, icon: string, hint: string, count: int, status: string, findings: Collection<int, Finding>}>,
     *     attackChain: array<int, array{step: int, phase: string, phase_key: string, time_offset: string, actor_action: string, target_vector: string, severity: string, defense_action: string}>,
     *     blastRadius: array{level: string, financial_impact: string, compliance_risk: string, downtime_est: string, threat_actor: string},
     *     remediationScripts: array{nginx: string, cloudflare: string, bash: string},
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
        $severityRank = [
            Finding::SEVERITY_CRITICAL => 4,
            Finding::SEVERITY_HIGH => 3,
            Finding::SEVERITY_MEDIUM => 2,
            Finding::SEVERITY_LOW => 1,
        ];

        foreach (array_keys(self::PHASES) as $key) {
            /** @var Collection<int, Finding> $phaseFindings */
            $phaseFindings = $grouped->get($key, collect());

            $sortedFindings = $phaseFindings->sortByDesc(
                fn (Finding $f): int => $severityRank[$f->severity] ?? 0
            )->values();

            $hasCritical = $sortedFindings->contains(fn (Finding $f): bool => $f->severity === Finding::SEVERITY_CRITICAL);
            $hasHigh = $sortedFindings->contains(fn (Finding $f): bool => $f->severity === Finding::SEVERITY_HIGH);
            $hasMedium = $sortedFindings->contains(fn (Finding $f): bool => $f->severity === Finding::SEVERITY_MEDIUM);

            $status = 'clean';
            if ($hasCritical) {
                $status = 'critical';
            } elseif ($hasHigh || $hasMedium) {
                $status = 'warning';
            } elseif ($sortedFindings->isNotEmpty()) {
                $status = 'low';
            }

            $phases[] = [
                'key' => $key,
                'label' => self::PHASES[$key]['label'],
                'short' => self::PHASES[$key]['short'],
                'icon' => self::PHASES[$key]['icon'],
                'hint' => self::PHASES[$key]['hint'],
                'count' => $sortedFindings->count(),
                'status' => $status,
                'findings' => $sortedFindings,
            ];
        }

        $worstSeverity = null;
        if ($findings->isNotEmpty()) {
            $maxRank = $findings->map(fn (Finding $f): int => $severityRank[$f->severity] ?? 0)->max();
            $worstSeverity = array_search($maxRank, $severityRank, true) ?: null;
        }

        $attackChain = $this->buildAttackChain($phases, $group);
        $blastRadius = $this->calculateBlastRadius($findings, $worstSeverity, $group);
        $remediationScripts = $this->generateRemediationScripts($findings, $group);

        return [
            'phases' => $phases,
            'attackChain' => $attackChain,
            'blastRadius' => $blastRadius,
            'remediationScripts' => $remediationScripts,
            'narrative' => $this->narrative($phases, $group),
            'totalFindings' => $findings->count(),
            'worstSeverity' => $worstSeverity,
        ];
    }

    /**
     * Map a finding to an attack phase using its kind, category, and title.
     */
    public function phaseFor(Finding $finding): string
    {
        $category = strtolower((string) $finding->category);
        $title = strtolower((string) $finding->title);

        // Compromise: live incident signals are clear evidence of a takeover.
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
            || str_contains($title, 'dns')
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
            || str_contains($title, 'actuator')
            || str_contains($title, 'backup')
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
            || str_contains($title, 'ssl')
            || str_contains($title, 'tls')
            || str_contains($title, 'cors')
        ) {
            return self::PHASE_EXPLOITATION;
        }

        // Critical findings without explicit category land in impact.
        if ($finding->severity === Finding::SEVERITY_CRITICAL) {
            return self::PHASE_IMPACT;
        }

        return self::PHASE_EXPLOITATION;
    }

    /**
     * Build an ordered Kill-Chain attack scenario based on real group findings.
     *
     * @param  array<int, array{key: string, label: string, short: string, icon: string, hint: string, count: int, status: string, findings: Collection<int, Finding>}>  $phases
     * @return array<int, array{step: int, phase: string, phase_key: string, time_offset: string, actor_action: string, target_vector: string, severity: string, defense_action: string}>
     */
    protected function buildAttackChain(array $phases, AssetGroup $group): array
    {
        $chain = [];
        $stepIndex = 1;

        $timeOffsets = ['T+00:00', 'T+00:18', 'T+00:45', 'T+01:20', 'T+02:10', 'T+03:30', 'T+05:00'];

        foreach ($phases as $phase) {
            /** @var Collection<int, Finding> $findings */
            $findings = $phase['findings'];

            // Take up to 2 distinct representative findings per phase to form a realistic story
            $selected = $findings->take(2);

            foreach ($selected as $finding) {
                $timeOffset = $timeOffsets[min($stepIndex - 1, count($timeOffsets) - 1)];
                $targetHost = $finding->asset?->hostname ?: $group->root_domain;

                $actorAction = $this->deriveActorAction($phase['key'], $finding);
                $defenseAction = $this->deriveDefenseAction($phase['key'], $finding);

                $chain[] = [
                    'step' => $stepIndex++,
                    'phase' => $phase['label'],
                    'phase_key' => $phase['key'],
                    'time_offset' => $timeOffset,
                    'actor_action' => $actorAction,
                    'target_vector' => "{$finding->title} ({$targetHost})",
                    'severity' => $finding->severity,
                    'defense_action' => $defenseAction,
                ];
            }
        }

        // If perimeter has 0 findings or very few, generate simulated baseline steps
        if (empty($chain)) {
            $chain = [
                [
                    'step' => 1,
                    'phase' => 'Reconnaissance',
                    'phase_key' => self::PHASE_RECONNAISSANCE,
                    'time_offset' => 'T+00:00',
                    'actor_action' => "Pemindaian pasif DNS & OSINT otomatis terhadap domain {$group->root_domain}",
                    'target_vector' => "Perimeter Publik {$group->root_domain}",
                    'severity' => 'low',
                    'defense_action' => 'Monitor terus rekaman DNS dan aktifkan proteksi Cloudflare/WAF.',
                ],
                [
                    'step' => 2,
                    'phase' => 'Probing',
                    'phase_key' => self::PHASE_PROBING,
                    'time_offset' => 'T+00:20',
                    'actor_action' => 'Fuzzing jalur terekspos untuk mencari file tersembunyi (.git, .env, admin panel)',
                    'target_vector' => "Web Application Root ({$group->root_domain})",
                    'severity' => 'low',
                    'defense_action' => 'Terapkan rule Nginx deny-all untuk dotfiles dan private endpoints.',
                ],
                [
                    'step' => 3,
                    'phase' => 'Exploitation',
                    'phase_key' => self::PHASE_EXPLOITATION,
                    'time_offset' => 'T+01:00',
                    'actor_action' => 'Percobaan eksploitasi celah injeksi, header bypass, dan miskonfigurasi SSL',
                    'target_vector' => "HTTP Gateway {$group->root_domain}",
                    'severity' => 'low',
                    'defense_action' => 'Pasang security headers lengkap (HSTS, CSP, X-Frame-Options).',
                ],
                [
                    'step' => 4,
                    'phase' => 'Compromise & Impact',
                    'phase_key' => self::PHASE_COMPROMISE,
                    'time_offset' => 'T+02:30',
                    'actor_action' => 'Percobaan akses tak berizin atau injeksi script pihak ketiga',
                    'target_vector' => "Core Application Asset {$group->root_domain}",
                    'severity' => 'low',
                    'defense_action' => 'Garda OS aktif memonitor integritas konten dan defacement secara berkala.',
                ],
            ];
        }

        return $chain;
    }

    /**
     * Derive what an attacker does based on phase and finding details.
     */
    protected function deriveActorAction(string $phaseKey, Finding $finding): string
    {
        $title = strtolower($finding->title);

        return match ($phaseKey) {
            self::PHASE_RECONNAISSANCE => str_contains($title, 'port')
                ? 'Melakukan port-scanning mendalam dan mengidentifikasi service terbuka tanpa firewall'
                : 'Memetakan DNS records, namespace subdomain, dan alamat IP publik target',
            self::PHASE_PROBING => str_contains($title, '.env') || str_contains($title, '.git')
                ? 'Mengunduh repositori sumber kode atau file kredensial database yang terekspos langsung'
                : 'Melakukan automated brute-force fuzzing pada path sensitif dan direktori tersembunyi',
            self::PHASE_EXPLOITATION => str_contains($title, 'header')
                ? 'Memanfaatkan ketiadaan header proteksi (CSP/HSTS) untuk serangan Man-in-the-Middle atau Clickjacking'
                : 'Mengeksploitasi kerentanan konfigurasi aplikasi untuk bypass otentikasi awal',
            self::PHASE_COMPROMISE => str_contains($title, 'gambling') || str_contains($title, 'injection')
                ? 'Menyisipkan backdoor web shell dan menginjeksi link phishing/judi online ke halaman publik'
                : 'Mengambil alih sesi administratif dan mendapatkan kendali remote execution pada server',
            self::PHASE_IMPACT => 'Melakukan eksfiltrasi database, merusak reputasi domain, serta menyebabkan downtime layanan',
            default => 'Mengeksekusi tahapan serangan terkoordinasi terhadap endpoint target',
        };
    }

    /**
     * Derive immediate defensive remediation to break the attack chain.
     */
    protected function deriveDefenseAction(string $phaseKey, Finding $finding): string
    {
        $title = strtolower($finding->title);

        return match ($phaseKey) {
            self::PHASE_RECONNAISSANCE => 'Tutup port non-esensial via Security Group/UFW dan sembunyikan origin IP di balik Cloudflare.',
            self::PHASE_PROBING => 'Blokir akses publik ke file dotfiles (.env, .git) dan direktori backup dengan rule Nginx 404.',
            self::PHASE_EXPLOITATION => 'Suntikkan HTTP Security Headers (HSTS, CSP, X-Frame-Options) dan aktifkan WAF Managed Rules.',
            self::PHASE_COMPROMISE => 'Isolasi server segera, bersihkan file terinjeksi, rotasi seluruh kredensial DB/API, dan restore dari backup bersih.',
            self::PHASE_IMPACT => 'Aktifkan Disaster Recovery Plan, buat laporan insiden sesuai mandat UU PDP No. 27/2022 Pasal 46.',
            default => 'Terapkan rekomendasi mitigasi Garda dan pantau log akses secara real-time.',
        };
    }

    /**
     * Calculate tangible business impact, financial risk, compliance penalty, and threat actor profile.
     *
     * @param  Collection<int, Finding>  $findings
     * @return array{level: string, financial_impact: string, compliance_risk: string, downtime_est: string, threat_actor: string}
     */
    protected function calculateBlastRadius(Collection $findings, ?string $worstSeverity, AssetGroup $group): array
    {
        $hasIncident = $findings->contains(fn (Finding $f): bool => $f->kind === Finding::KIND_INCIDENT);

        if ($worstSeverity === Finding::SEVERITY_CRITICAL || $hasIncident) {
            return [
                'level' => 'CRITICAL',
                'financial_impact' => 'Rp 75.000.000 - Rp 300.000.000',
                'compliance_risk' => 'Pelanggaran UU PDP No. 27/2022 Pasal 35 & 46 (Sanksi denda hingga 2% pendapatan tahunan)',
                'downtime_est' => '8 - 24 Jam Recovery Time',
                'threat_actor' => 'Initial Access Broker & Automated Ransomware/SEO Hijacker',
            ];
        }

        if ($worstSeverity === Finding::SEVERITY_HIGH) {
            return [
                'level' => 'HIGH',
                'financial_impact' => 'Rp 20.000.000 - Rp 65.000.000',
                'compliance_risk' => 'Peringatan Audit Kepatuhan ISO 27001 (A.12) & NIST CSF v2.0',
                'downtime_est' => '2 - 6 Jam Intervensi Teknis',
                'threat_actor' => 'Opportunistic Threat Actor & Vulnerability Scanner Botnet',
            ];
        }

        if ($worstSeverity === Finding::SEVERITY_MEDIUM) {
            return [
                'level' => 'ELEVATED',
                'financial_impact' => 'Rp 5.000.000 - Rp 20.000.000',
                'compliance_risk' => 'Penyimpangan Security Baseline Internal & Kebocoran Metadata Sistem',
                'downtime_est' => '1 - 2 Jam Patching & Hardening',
                'threat_actor' => 'Automated Web Crawler & Targeted Reconnaissance Script',
            ];
        }

        return [
            'level' => 'LOW',
            'financial_impact' => '< Rp 5.000.000 (Risiko Finansial Terkendali)',
            'compliance_risk' => 'Tingkat Kepatuhan Regulasi Terjaga & Aman',
            'downtime_est' => '0 Jam (Operasional Normal)',
            'threat_actor' => 'Generic Internet Background Scanner',
        ];
    }

    /**
     * Generate 1-click tailored defense scripts (Nginx, Cloudflare WAF, Bash).
     *
     * @param  Collection<int, Finding>  $findings
     * @return array{nginx: string, cloudflare: string, bash: string}
     */
    protected function generateRemediationScripts(Collection $findings, AssetGroup $group): array
    {
        $domain = $group->root_domain;
        $openPorts = [];
        $exposedPaths = [];

        foreach ($findings as $f) {
            $cat = strtolower((string) $f->category);
            $title = strtolower((string) $f->title);

            if (str_contains($cat, 'port') || str_contains($title, 'port')) {
                if (preg_match('/port\s+(\d+)/i', $f->title, $m)) {
                    $openPorts[] = $m[1];
                }
            }

            if (str_contains($title, '.env')) {
                $exposedPaths[] = '/.env';
            }
            if (str_contains($title, '.git')) {
                $exposedPaths[] = '/.git';
            }
            if (str_contains($title, 'phpinfo')) {
                $exposedPaths[] = '/phpinfo.php';
            }
        }

        $openPorts = array_values(array_unique($openPorts));
        $exposedPaths = array_values(array_unique($exposedPaths));

        // 1. Nginx Hardening Configuration
        $nginx = <<<CONF
# =========================================================================
# GardaCyber Auto-Remediation Shield for {$domain}
# File: /etc/nginx/conf.d/garda-hardening-{$domain}.conf
# Generated: auto-mitigation against detected attack vectors
# =========================================================================

# [1] Block direct access to hidden files and metadata (.env, .git, config)
location ~ /\.(?!well-known).* {
    deny all;
    access_log off;
    log_not_found off;
    return 404;
}

location ~* (\.env|\.git|\.dockerignore|docker-compose\.ya?ml|composer\.(json|lock)|package\.json)$ {
    deny all;
    return 404;
}

# [2] Block diagnostic probes and administrative leaks
location ~* (phpinfo\.php|info\.php|telescope|ignition|_profiler|swagger|actuator|wp-config) {
    deny all;
    return 404;
}

# [3] Hardened HTTP Response Headers (Defense-in-Depth)
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

# [4] Anti-DDoS & Buffer Abuse Mitigations
client_body_buffer_size 128k;
client_max_body_size 10m;
CONF;

        // 2. Cloudflare WAF Expression
        $cfExpressions = [
            'http.request.uri.path contains "/.git"',
            'http.request.uri.path contains "/.env"',
            'http.request.uri.path contains "phpinfo"',
            'http.request.uri.path contains "actuator"',
            'http.request.uri.path contains "telescope"',
            'http.request.uri.path contains "wp-config"',
            'http.request.uri.path contains "docker-compose"',
        ];

        $cfExprJoined = implode(' or ', $cfExpressions);
        $cloudflare = <<<CF
# Cloudflare WAF Custom Rule (Expression Builder)
# Rule Name: GardaCyber Perimeter Shield - {$domain}
# Action: Block (or Managed Challenge)

({$cfExprJoined})
CF;

        // 3. Linux Shell Script (garda-shield.sh)
        $portCommands = '';
        if (! empty($openPorts)) {
            foreach ($openPorts as $p) {
                if (! in_array($p, ['80', '443', '22'], true)) {
                    $portCommands .= "ufw deny {$p}/tcp comment 'Blocked by GardaCyber' || true\n";
                }
            }
        }
        if (empty($portCommands)) {
            $portCommands = "# Menutup port database publik yang rentan terekspos\nufw deny 3306/tcp comment 'Block MySQL Public' || true\nufw deny 5432/tcp comment 'Block Postgres Public' || true\nufw deny 6379/tcp comment 'Block Redis Public' || true\n";
        }

        $bash = <<<BASH
#!/usr/bin/env bash
# =========================================================================
# GardaCyber 1-Click Auto-Remediation Shield
# Target: {$domain}
# Usage: sudo bash garda-shield.sh
# =========================================================================

set -euo pipefail
echo "🔒 [GardaShield] Applying perimeter hardening for {$domain}..."

# 1. Web Root Permissions Hardening
WEB_ROOT="/var/www/html"
if [ -d "\$WEB_ROOT" ]; then
    echo "📁 Securing directory permissions in \$WEB_ROOT..."
    find "\$WEB_ROOT" -type d -exec chmod 755 {} \;
    find "\$WEB_ROOT" -type f -exec chmod 644 {} \;
    find "\$WEB_ROOT" -name ".*" -exec chmod 600 {} \; 2>/dev/null || true
fi

# 2. Linux Firewall (UFW) Protection
if command -v ufw >/dev/null 2>&1; then
    echo "🛡️ Configuring UFW Firewall policies..."
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow 80/tcp comment 'HTTP'
    ufw allow 443/tcp comment 'HTTPS'
    ufw allow 22/tcp comment 'SSH'
    {$portCommands}
    echo "y" | ufw enable || true
fi

# 3. Kernel Network Stack Hardening
SYSCTL_CONF="/etc/sysctl.d/99-garda-shield.conf"
echo "⚙️ Applying kernel anti-spoofing parameters to \$SYSCTL_CONF..."
cat << 'EOF' > "\$SYSCTL_CONF"
net.ipv4.tcp_syncookies = 1
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1
net.ipv4.conf.all.accept_source_route = 0
net.ipv4.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0
EOF
sysctl -p "\$SYSCTL_CONF" >/dev/null 2>&1 || true

echo "✅ [GardaShield] Hardening applied successfully! Target {$domain} is now protected."
BASH;

        return [
            'nginx' => $nginx,
            'cloudflare' => $cloudflare,
            'bash' => $bash,
        ];
    }

    /**
     * Build a readable attack narrative from the phases.
     *
     * @param  array<int, array{key: string, label: string, short: string, icon: string, hint: string, count: int, status: string, findings: Collection<int, Finding>}>  $phases
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
