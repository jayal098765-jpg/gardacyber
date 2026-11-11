/* ============================================================
   GARDACYBER — LANDING MOTION
   Lightweight, respects prefers-reduced-motion.
   ============================================================ */
(function () {
  'use strict';

  var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Reveal on scroll (fade + rise) ---------- */
  function initReveals() {
    var els = document.querySelectorAll('[data-reveal]');
    if (!els.length) return;

    if (prefersReduced || !('IntersectionObserver' in window)) {
      els.forEach(function (el) { el.classList.add('in-view'); });
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    els.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Terminal: staggered line entrance ---------- */
  function initTerminalStagger() {
    var terminal = document.querySelector('.terminal-window .terminal-body');
    if (!terminal) return;

    var lines = Array.prototype.slice.call(terminal.children);
    if (prefersReduced) {
      lines.forEach(function (line) { line.style.opacity = '1'; });
      return;
    }

    lines.forEach(function (line, index) {
      line.style.opacity = '0';
      line.style.animation = 'termLineIn 480ms ' + (250 + index * 260) + 'ms var(--ease-out-soft) forwards';
    });
  }

  /* ---------- Floating scanline sweep on hero ---------- */
  function initScanSweep() {
    var hero = document.querySelector('.perimeter-hero');
    if (!hero || prefersReduced) return;
    var sweep = document.createElement('div');
    sweep.className = 'hero-sweep';
    sweep.setAttribute('aria-hidden', 'true');
    hero.appendChild(sweep);
  }

  document.addEventListener('DOMContentLoaded', function () {
    initReveals();
    initTerminalStagger();
    initScanSweep();
  });
})();
