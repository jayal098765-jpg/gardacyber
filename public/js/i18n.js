/* ============================================================
   GARDACYBER — LANDING I18N (EN / ID)
   Lightweight dictionary + toggle stored in localStorage.
   ============================================================ */
(function () {
  'use strict';

  window.GardaI18N = {
    nav_perimeter: { en: 'Perimeter', id: 'Perimeter' },
    nav_workspace: { en: 'Workspace', id: 'Workspace' },
    nav_incidents: { en: 'Incidents', id: 'Insiden' },
    nav_workflow: { en: 'Workflow', id: 'Alur Kerja' },
    nav_plans: { en: 'Plans', id: 'Paket' },
    nav_signin: { en: 'Sign in', id: 'Masuk' },
    nav_getstarted: { en: 'Get started', id: 'Mulai' },
    nav_openterminal: { en: 'Open terminal', id: 'Buka terminal' },

    hero_label: { en: 'external-attack-surface.terminal', id: 'terminal-permukaan-serangan-eksternal' },
    hero_t1: { en: 'Know your', id: 'Kenali' },
    hero_t2: { en: 'perimeter.', id: 'perimeter Anda.' },
    hero_t3: { en: 'Own the next move.', id: 'Ambil langkah berikutnya.' },
    hero_desc: { en: 'GardaCyber maps what you expose to the internet — weak headers, exposed paths, and compromise signals — so you can fix them fast.', id: 'GardaCyber memetakan apa yang terekspos ke internet — header lemah, path terbuka, dan sinyal kompromi — biar cepat kamu perbaiki.' },
    hero_cta_launch: { en: 'Launch scan workspace', id: 'Buka workspace scan' },
    hero_cta_see: { en: 'See the console', id: 'Lihat konsol' },
    hero_cmd: { en: '$ ./gardacyber --scan perimeter --no-credit-card', id: '$ ./gardacyber --scan perimeter --tanpa-kartu-kredit' },
    hero_term_title: { en: 'root@gardacyber: ~/scans ● LIVE PROBE', id: 'root@gardacyber: ~/scans ● PROBE LANGSUNG' },
    hero_term_l1: { en: 'resolving example.org → 103.x.x.x (VERIFIED PUBLIC)', id: 'menyelesaikan example.org → 103.x.x.x (PUBLIK TERVERIFIKASI)' },
    hero_term_l2: { en: 'subdomain discovery → 12 live assets', id: 'penemuan subdomain → 12 aset aktif' },
    hero_term_l3: { en: 'open ports → 80 443 8080', id: 'port terbuka → 80 443 8080' },
    hero_term_l4: { en: 'HSTS header missing (vulnerability)', id: 'header HSTS tidak ada (kerentanan)' },
    hero_term_l5: { en: '/phpinfo.php exposed (exposure)', id: '/phpinfo.php terekspos (eksposur)' },
    hero_term_l6: { en: 'gambling-injection signature (incident)', id: 'signature injeksi-judi (insiden)' },
    hero_term_l7: { en: 'A–F rating computed → grade D · 64/100', id: 'nilai A–F terhitung → nilai D · 64/100' },
    hero_term_l8: { en: 'cat report.csv | head -5', id: 'cat report.csv | head -5' },

    cov_kicker: { en: 'Reconnaissance coverage', id: 'Cakupan pengintaian' },
    cov_h2: { en: 'Checks that run like an attacker\'s checklist.', id: 'Pemeriksaan yang berjalan seperti daftar periksa penyerang.' },
    cov_sub: { en: 'Hosts, ports, headers, paths, and content signatures land in one queue.', id: 'Host, port, header, path, dan signature konten masuk dalam satu antrean.' },
    cov_stat1_v: { en: '14', id: '14' },
    cov_stat1_l: { en: 'Probe categories', id: 'Kategori probe' },
    cov_stat1_s: { en: 'DNS, ports, headers, paths, incident keywords, and more.', id: 'DNS, port, header, path, kata kunci insiden, dan lainnya.' },
    cov_stat2_v: { en: '28', id: '28' },
    cov_stat2_l: { en: 'Detection signatures', id: 'Signature deteksi' },
    cov_stat2_s: { en: 'Defacement, gambling injection, SEO spam, exposure fingerprints.', id: 'Defacement, injeksi judi, spam SEO, sidik jari eksposur.' },
    cov_stat3_v: { en: 'A–F', id: 'A–F' },
    cov_stat3_l: { en: 'Security rating', id: 'Nilai keamanan' },
    cov_stat3_s: { en: 'Density-aware grading that reflects worst open risk.', id: 'Penilaian sadar-kepadatan yang mencerminkan risiko terbuka terburuk.' },

    vuln_h3: { en: 'Vulnerability probes', id: 'Probe kerentanan' },
    vuln_sub: { en: 'Non-destructive checks of publicly visible configuration and response behavior.', id: 'Pemeriksaan non-destruktif atas konfigurasi dan perilaku respons yang terlihat publik.' },
    vuln_row1: { en: 'Security headers', id: 'Header keamanan' },
    vuln_row1c: { en: 'HSTS · CSP · nosniff · referrer', id: 'HSTS · CSP · nosniff · referrer' },
    vuln_row2: { en: 'Exposure paths', id: 'Path eksposur' },
    vuln_row2c: { en: '.git · .env · phpinfo · ignition · swagger', id: '.git · .env · phpinfo · ignition · swagger' },
    vuln_row3: { en: 'Web injection hints', id: 'Petunjuk injeksi web' },
    vuln_row3c: { en: 'SQL error leak · reflected param', id: 'Bocor error SQL · param terpantul' },
    vuln_row4: { en: 'Port fingerprint', id: 'Sidik jari port' },
    vuln_row4c: { en: '80 · 443 · 8080 · 8443', id: '80 · 443 · 8080 · 8443' },

    inc_h3: { en: 'Incident signatures', id: 'Signature insiden' },
    inc_sub: { en: 'Heuristic content signals tuned for SEA web compromise patterns.', id: 'Sinyal konten heuristik yang disetel untuk pola kompromi web Asia Tenggara.' },
    inc_row1: { en: 'defacement', id: 'defacement' },
    inc_row1c: { en: 'hacked-by · pwned · greetz', id: 'hacked-by · pwned · greetz' },
    inc_row2: { en: 'gambling-injection', id: 'injeksi-judi' },
    inc_row2c: { en: 'slot gacor · maxwin · judi online', id: 'slot gacor · maxwin · judi online' },
    inc_row3: { en: 'seo-spam', id: 'spam-seo' },
    inc_row3c: { en: 'hidden backlinks · keyword stuffing', id: 'backlink tersembunyi · isian kata kunci' },

    triage_title: { en: 'Signal triage flow', id: 'Alur triase sinyal' },
    triage_l1: { en: 'discovery → assets', id: 'penemuan → aset' },
    triage_l2: { en: 'probes → vulnerabilities', id: 'probe → kerentanan' },
    triage_l3: { en: 'content sweep → incidents', id: 'sapuan konten → insiden' },
    triage_l4: { en: 'rating → A–F', id: 'nilai → A–F' },
    triage_l5: { en: 'triage → resolved / false positive', id: 'triase → selesai / positif palsu' },
    triage_note: { en: 'Every finding keeps its evidence. Nothing is auto-dismissed.', id: 'Setiap temuan menyimpan buktinya. Tidak ada yang otomatis ditutup.' },

    ws_kicker: { en: 'From signal to decision', id: 'Dari sinyal ke keputusan' },
    ws_h2: { en: 'One console. All the evidence.', id: 'Satu konsol. Semua bukti.' },
    ws_side: { en: 'Hosts, findings, and triage decisions stay together.', id: 'Host, temuan, dan keputusan triase berada dalam satu tempat.' },
    ws_preview_1v: { en: '12', id: '12' },
    ws_preview_1l: { en: 'Assets in scope', id: 'Aset dalam cakupan' },
    ws_preview_2l: { en: 'Open vulnerabilities', id: 'Kerentanan terbuka' },
    ws_preview_3l: { en: 'Open incidents', id: 'Insiden terbuka' },
    ws_preview_4l: { en: 'Asset group', id: 'Grup aset' },
    ws_tbl_sev: { en: 'Severity', id: 'Keparahan' },
    ws_tbl_find: { en: 'Finding', id: 'Temuan' },
    ws_tbl_asset: { en: 'Asset', id: 'Aset' },
    ws_tbl_ev: { en: 'Evidence', id: 'Bukti' },
    ws_tbl_r1: { en: 'Exposed .git repository', id: 'Repositori .git terekspos' },
    ws_tbl_r1e: { en: 'HTTP 200 · ref: refs/', id: 'HTTP 200 · ref: refs/' },
    ws_tbl_r2: { en: 'Gambling content injection', id: 'Injeksi konten judi' },
    ws_tbl_r2e: { en: 'matches "slot gacor"', id: 'cocok dengan "slot gacor"' },
    ws_tbl_r3: { en: 'HSTS header missing', id: 'Header HSTS tidak ada' },
    ws_tbl_r3e: { en: 'response header absent', id: 'header respons tidak ada' },
    ws_note: { en: 'Illustrative findings — sample workspace data, not a live target.', id: 'Temuan ilustratif — data workspace contoh, bukan target langsung.' },

    inc_kicker: { en: 'Unique to GardaCyber', id: 'Khas GardaCyber' },
    inc_h2: { en: 'Incident Check. What a regular scanner skips.', id: 'Incident Check. Yang dilewatkan scanner biasa.' },
    inc_sub2: { en: 'GardaCyber sweeps indexed content for signs a live page has been compromised.', id: 'GardaCyber menyapu konten terindeks untuk tanda halaman langsung telah disusupi.' },
    inc_card1_t: { en: 'Defacement', id: 'Defacement' },
    inc_card1_d: { en: 'Hacked-by pages, pwned messages, and attacker greetz markers.', id: 'Halaman hacked-by, pesan pwned, dan penanda greetz penyerang.' },
    inc_card2_t: { en: 'Gambling injection', id: 'Injeksi judi' },
    inc_card2_d: { en: 'Slot gacor, maxwin, judi online keywords injected into your pages.', id: 'Kata kunci slot gacor, maxwin, judi online yang disuntikkan ke halaman Anda.' },
    inc_card3_t: { en: 'SEO spam', id: 'Spam SEO' },
    inc_card3_d: { en: 'Hidden backlinks and keyword-stuffed doorway content.', id: 'Backlink tersembunyi dan konten doorway berisi kata kunci.' },
    inc_queue: { en: 'Incident queue — evidence first', id: 'Antrean insiden — bukti lebih dulu' },
    inc_row1_t: { en: 'Defacement signature: hacked-by', id: 'Signature defacement: hacked-by' },
    inc_row2_t: { en: 'Gambling injection: slot gacor', id: 'Injeksi judi: slot gacor' },
    inc_row3_t: { en: 'Hidden SEO spam backlinks', id: 'Backlink spam SEO tersembunyi' },
    inc_foot: { en: '5 open · 12 resolved this week · last check 4m ago', id: '5 terbuka · 12 selesai minggu ini · pemeriksaan terakhir 4 menit lalu' },

    cap_kicker: { en: 'Capabilities', id: 'Kemampuan' },
    cap_h2: { en: 'Built for focused security work.', id: 'Dibangun untuk pekerjaan keamanan yang fokus.' },
    cap_sub: { en: 'Assets, findings, and reports in one clean workspace.', id: 'Aset, temuan, dan laporan dalam satu workspace yang rapi.' },
    cap_c1_t: { en: 'Asset inventory', id: 'Inventaris aset' },
    cap_c1_d: { en: 'Discovered hosts grouped by root domain with HTTP status, server, ports, and technologies.', id: 'Host yang ditemukan dikelompokkan per root domain dengan status HTTP, server, port, dan teknologi.' },
    cap_c2_t: { en: 'Exposure probes', id: 'Probe eksposur' },
    cap_c2_d: { en: 'Non-destructive checks for sensitive paths, weak headers, and web injection hints.', id: 'Pemeriksaan non-destruktif untuk path sensitif, header lemah, dan petunjuk injeksi web.' },
    cap_c3_t: { en: 'Incident signatures', id: 'Signature insiden' },
    cap_c3_d: { en: 'Defacement, gambling injection, and SEO spam sweeps with matched evidence.', id: 'Sapuan defacement, injeksi judi, dan spam SEO dengan bukti yang cocok.' },
    cap_c4_t: { en: 'A–F security rating', id: 'Nilai keamanan A–F' },
    cap_c4_d: { en: 'Density-aware grading driven by your worst open severity, not averages.', id: 'Penilaian sadar-kepadatan didorong oleh keparahan terbuka terburuk Anda, bukan rata-rata.' },
    cap_c5_t: { en: 'Live scan console', id: 'Konsol scan langsung' },
    cap_c5_d: { en: 'Watch each probe phase run with progress, counts, and final status.', id: 'Saksikan setiap fase probe berjalan dengan progres, jumlah, dan status akhir.' },
    cap_c6_t: { en: 'Remediation playbooks', id: 'Playbook remediasi' },
    cap_c6_d: { en: 'Per-finding guidance, hardening snippets, and verification standards.', id: 'Panduan per-temuan, cuplikan penguatan, dan standar verifikasi.' },

    wf_kicker: { en: 'Workflow', id: 'Alur kerja' },
    wf_h2: { en: 'One scan. Five stages.', id: 'Satu scan. Lima tahap.' },
    wf_sub: { en: 'The same chain every time.', id: 'Rangkaian yang sama setiap kali.' },
    wf_s1_t: { en: 'Discover', id: 'Temukan' },
    wf_s1_d: { en: 'Resolve DNS and enumerate live subdomains.', id: 'Selesaikan DNS dan enumerasi subdomain aktif.' },
    wf_s1_tag: { en: 'dig · DNS A/AAAA', id: 'dig · DNS A/AAAA' },
    wf_s2_t: { en: 'Probe', id: 'Probe' },
    wf_s2_d: { en: 'Fingerprint HTTP state and open web ports.', id: 'Sidik jari status HTTP dan port web terbuka.' },
    wf_s2_tag: { en: '80 · 443 · 8080 · 8443', id: '80 · 443 · 8080 · 8443' },
    wf_s3_t: { en: 'Analyze', id: 'Analisis' },
    wf_s3_d: { en: 'Check headers, exposure paths, and injection hints.', id: 'Periksa header, path eksposur, dan petunjuk injeksi.' },
    wf_s3_tag: { en: 'HSTS · .git · SQL-error', id: 'HSTS · .git · error-SQL' },
    wf_s4_t: { en: 'Sweep', id: 'Sapu' },
    wf_s4_d: { en: 'Scan visible content for compromise signatures.', id: 'Pindai konten yang terlihat untuk signature kompromi.' },
    wf_s4_tag: { en: 'defacement · gambling · spam', id: 'defacement · judi · spam' },
    wf_s5_t: { en: 'Grade', id: 'Nilai' },
    wf_s5_d: { en: 'Compute A–F rating and queue findings for triage.', id: 'Hitung nilai A–F dan antrekan temuan untuk triase.' },
    wf_s5_tag: { en: 'worst-open-severity', id: 'keparahan-terbuka-terburuk' },
    wf_legend: { en: 'Stages 03–04 produce vulnerabilities and incidents in separate lanes.', id: 'Tahap 03–04 menghasilkan kerentanan dan insiden di jalur terpisah.' },
    wf_rating: { en: 'Security rating', id: 'Nilai keamanan' },
    wf_rating_crit: { en: 'Critical', id: 'Kritis' },
    wf_rating_high: { en: 'High', id: 'Tinggi' },
    wf_rating_med: { en: 'Medium', id: 'Sedang' },
    wf_rating_low: { en: 'Low', id: 'Rendah' },
    wf_rating_assets: { en: 'Assets', id: 'Aset' },
    wf_rating_vulns: { en: 'Open vulns', id: 'Kerentanan terbuka' },
    wf_rating_incs: { en: 'Open incidents', id: 'Insiden terbuka' },
    wf_rating_res: { en: 'Resolved', id: 'Selesai' },

    plan_kicker: { en: 'Plans', id: 'Paket' },
    plan_h2: { en: 'Start inside the terminal.', id: 'Mulai di dalam terminal.' },
    plan_sub: { en: 'Create an account, add a domain, and watch the probe run. Demo pricing for the hackathon build.', id: 'Buat akun, tambahkan domain, dan saksikan probe berjalan. Harga demo untuk build hackathon.' },
    plan1_name: { en: 'Checklist', id: 'Checklist' },
    plan1_price: { en: 'Rp 0', id: 'Rp 0' },
    plan1_forever: { en: 'forever', id: 'selamanya' },
    plan1_desc: { en: 'The full workflow on the training fixture.', id: 'Alur kerja lengkap pada fixture pelatihan.' },
    plan1_cta: { en: 'Start free', id: 'Mulai gratis' },
    plan1_f1: { en: 'Demo training domain', id: 'Domain pelatihan demo' },
    plan1_f2: { en: 'Full probe workflow', id: 'Alur kerja probe lengkap' },
    plan1_f3: { en: 'Vulnerability exposure checks', id: 'Pemeriksaan eksposur kerentanan' },
    plan1_f4: { en: 'Incident signature sweep', id: 'Sapuan signature insiden' },
    plan1_f5: { en: 'Remediation playbooks', id: 'Playbook remediasi' },
    plan2_name: { en: 'Perimeter', id: 'Perimeter' },
    plan2_flag: { en: 'Most used', id: 'Paling banyak dipakai' },
    plan2_price: { en: 'Rp 1,489,000', id: 'Rp 1.489.000' },
    plan2_month: { en: '/ month', id: '/ bulan' },
    plan2_desc: { en: 'Live target scanning + CSV/HTML reports.', id: 'Pemindaian target langsung + laporan CSV/HTML.' },
    plan2_cta: { en: 'Upgrade to Perimeter', id: 'Naik ke Perimeter' },
    plan2_f1: { en: 'Authorized live domain scans', id: 'Pemindaian domain langsung resmi' },
    plan2_f2: { en: 'Open-port probing', id: 'Probe port terbuka' },
    plan2_f3: { en: 'CSV & HTML report export', id: 'Ekspor laporan CSV & HTML' },
    plan2_f4: { en: 'Security rating A–F', id: 'Nilai keamanan A–F' },
    plan2_f5: { en: 'Priority support + SLA', id: 'Dukungan prioritas + SLA' },
    plan3_name: { en: 'Squad', id: 'Skuad' },
    plan3_price: { en: 'Rp 3,989,000', id: 'Rp 3.989.000' },
    plan3_desc: { en: 'Team access with role-based consoles.', id: 'Akses tim dengan konsol berbasis peran.' },
    plan3_cta: { en: 'Upgrade to Squad', id: 'Naik ke Skuad' },
    plan3_f1: { en: 'Everything in Perimeter', id: 'Semua di Perimeter' },
    plan3_f2: { en: '10 team seats', id: '10 kursi tim' },
    plan3_f3: { en: 'Per-group asset isolation', id: 'Isolasi aset per-grup' },
    plan3_f4: { en: 'AI triage & summaries', id: 'Triase AI & ringkasan' },
    plan_note: { en: 'Prices in IDR are demo values for this hackathon build, not a commercial offer.', id: 'Harga dalam IDR adalah nilai demo untuk build hackathon ini, bukan penawaran komersial.' },

    foot_brand: { en: 'Attack surface analysis engineered for defenders who think like attackers.', id: 'Analisis permukaan serangan yang direkayasa untuk pembela yang berpikir seperti penyerang.' },
    foot_explore: { en: 'Explore', id: 'Jelajahi' },
    foot_perimeter: { en: 'Perimeter coverage', id: 'Cakupan perimeter' },
    foot_incidents: { en: 'Incident signals', id: 'Sinyal insiden' },
    foot_workflow: { en: 'Workflow', id: 'Alur kerja' },
    foot_ws: { en: 'Workspace', id: 'Workspace' },
    foot_signin: { en: 'Sign in', id: 'Masuk' },
    foot_create: { en: 'Create account', id: 'Buat akun' },
    foot_note: { en: 'Hackathon build — not a substitute for a professional security audit.', id: 'Build hackathon — bukan pengganti audit keamanan profesional.' },
    foot_right: { en: 'scan only what you own or have permission to test', id: 'scan hanya yang Anda miliki atau berizin untuk diuji' },

    // ---- Auth pages (login / signup) ----
    auth_title: { en: 'Sign in', id: 'Masuk' },
    auth_sub: { en: 'Back to your scans and findings.', id: 'Kembali ke scan dan temuan Anda.' },
    label_email: { en: 'Email', id: 'Email' },
    label_password: { en: 'Password', id: 'Kata sandi' },
    label_remember: { en: 'Keep me signed in', id: 'Biarkan saya tetap masuk' },
    btn_signin: { en: 'Sign in', id: 'Masuk' },
    auth_foot_signup: { en: 'New to GardaCyber? Create an account', id: 'Baru di GardaCyber? Buat akun' },
    signup_title: { en: 'Create your account', id: 'Buat akun Anda' },
    signup_sub: { en: 'Scans, findings, and reports stay inside your own workspace.', id: 'Scan, temuan, dan laporan tersimpan di workspace Anda sendiri.' },
    label_display_name: { en: 'Display name (optional)', id: 'Nama tampilan (opsional)' },
    label_confirm_password: { en: 'Confirm password', id: 'Konfirmasi kata sandi' },
    placeholder_password: { en: 'At least 8 characters', id: 'Minimal 8 karakter' },
    btn_signup: { en: 'Create account', id: 'Buat akun' },
    auth_foot_login: { en: 'Already have an account? Sign in', id: 'Sudah punya akun? Masuk' },
  };

  var CURRENT = 'en';
  try {
    var saved = localStorage.getItem('gardacyber_lang');
    if (saved === 'id' || saved === 'en') CURRENT = saved;
    else if ((navigator.language || navigator.userLanguage || 'en').toLowerCase().indexOf('id') === 0 || (navigator.language || '').toLowerCase().indexOf('in') === 0) CURRENT = 'id';
  } catch (e) { /* private mode */ }

  function applyLang(lang) {
    CURRENT = lang;
    try { localStorage.setItem('gardacyber_lang', lang); } catch (e) {}
    var dict = window.GardaI18N;
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      var key = el.getAttribute('data-i18n');
      if (dict[key] && dict[key][lang] !== undefined) el.textContent = dict[key][lang];
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
      var key = el.getAttribute('data-i18n-placeholder');
      if (dict[key] && dict[key][lang] !== undefined) el.setAttribute('placeholder', dict[key][lang]);
    });
    var html = document.documentElement;
    if (html) html.setAttribute('lang', lang === 'id' ? 'id' : 'en');
    document.querySelectorAll('[data-lang-btn]').forEach(function (btn) {
      var active = btn.getAttribute('data-lang-btn') === lang;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-pressed', String(active));
    });
  }

  function initLangToggle() {
    document.querySelectorAll('[data-lang-btn]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        applyLang(btn.getAttribute('data-lang-btn'));
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initLangToggle();
    applyLang(CURRENT);
  });
})();
