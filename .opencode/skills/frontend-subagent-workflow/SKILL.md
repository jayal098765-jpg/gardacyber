---
name: frontend-subagent-workflow
description: Kebijakan kerja untuk tugas frontend (Blade/CSS/JS) dan pekerjaan besar: selalu delegasikan ke subagent. Aktifkan saat menangani UI, styling, halaman baru, debugging frontend, atau tugas yang bisa dipecah paralel.
---

# Frontend & Subagent Workflow

Aturan ini berlaku untuk project GardaCyber (Laravel + Blade + CSS statis di `public/css/` + JS statis di `public/js/`).

## Kapan HARUS pakai subagent

1. **Pekerjaan frontend apa pun** yang menyentuh lebih dari satu file view/assets (mis. perubahan layout, halaman baru, polish UI menyeluruh, redesign, perbaikan CSS/JS lintas halaman).
2. **Tugas besar/panjang** — apa pun yang butuh banyak langkah (scan menyeluruh, audit UI, penambahan fitur multi-halaman, refactor).
3. **Pekerjaan paralel** — beberapa bagian independen yang bisa dikerjakan bersamaan (mis. panel A di dashboard + halaman settings + CSS bersama).

## Cara pakai subagent

- Untuk **eksplorasi/kebutuhan jawaban cepat**: pilih agent `explore` (tingkat: quick/medium/very thorough).
- Untuk **eksekusi kerja multi-langkah**: pilih agent `general`, sertakan:
  - Path file yang relevan (misal `resources/views/app/dashboard.blade.php`, `public/css/console.css`, `public/js/console.js`).
  - Konteks singkat: framework (Laravel 13 + Blade), lokasi CSS/JS statis, dan aturan project.
  - Hasil yang diharapkan (formatted output / checklist).
- Jangan menjalankan pekerjaan frontend besar secara langsung di sesi utama tanpa subagent, kecuali perubahan kecil satu file.

## Konvensi penting untuk dikirim ke subagent

- Frontend memakai **CSS statis** (`public/css/app.css`, `public/css/console.css`) — bukan hasil build Vite. Ubah file statis langsung; `npm run build` TIDAK diperlukan.
- JS statis di `public/js/` (mis. `console.js`, `i18n.js`, `copilot-widget.js`). Validasi dengan `node --check <file>`.
- View Blade di `resources/views/`. Setelah edit banyak view, jalankan `php artisan view:cache`.
- Pastikan seluruh test tetap hijau: `php artisan test --compact`.
- Hormati `prefers-reduced-motion`, focus-visible keyboard, dan kontras (checklist dari skill ui-ux-pro-max).
- Selalu akhiri dengan ringkasan file yang diubah agar mudah di-review.

## Anti-pola

- Jangan menumpuk semua langkah besar di satu sesi utama.
- Jangan meminta subagent mengubah dependency (`composer.json`/`package.json`) tanpa persetujuan user.
- Jangan biarkan subagent menghapus file yang bukan bagian dari tugasnya.
