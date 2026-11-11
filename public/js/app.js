/* ============================================================
   DETTACK LANDING — interactions
   ============================================================ */
(function () {
  "use strict";

  var prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Reveal on scroll ---------- */
  function addReveals(root) {
    var els = (root || document).querySelectorAll(".reveal");
    els.forEach(function (el) { el.classList.add("in-view"); });
    if (prefersReduced || !("IntersectionObserver" in window)) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add("in-view");
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Animated counters ---------- */
  function formatNum(n) { return n.toLocaleString("en-US"); }

  function animateCount(el) {
    var target = parseInt(el.getAttribute("data-count"), 10);
    if (isNaN(target)) return;
    if (prefersReduced) { el.textContent = formatNum(target); return; }
    var dur = 1400;
    var start = null;
    function frame(ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = formatNum(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(frame);
      else {
        el.textContent = formatNum(target);
        el.classList.remove("count-flash");
        void el.offsetWidth;
        el.classList.add("count-flash");
      }
    }
    requestAnimationFrame(frame);
  }

  function initCounters() {
    var counters = document.querySelectorAll("[data-count]");
    if (!("IntersectionObserver" in window) || prefersReduced) {
      counters.forEach(function (el) {
        var t = parseInt(el.getAttribute("data-count"), 10);
        if (!isNaN(t)) el.textContent = formatNum(t);
      });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          animateCount(en.target);
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Donut interaction ---------- */
  function initDonut() {
    var segs = document.querySelectorAll(".donut-seg");
    var legend = document.getElementById("donutLegend");
    var total = document.getElementById("donutTotal");
    if (!segs.length || !legend || !total) return;

    var labels = [];
    segs.forEach(function (seg, i) {
      labels.push({ label: seg.getAttribute("data-label"), count: parseInt(seg.getAttribute("data-count"), 10) });
    });

    function clear() {
      segs.forEach(function (s) { s.classList.remove("muted", "focused"); });
      legend.querySelectorAll("li").forEach(function (li) { li.classList.remove("active"); });
      total.textContent = "14,254";
      total.style.color = "";
    }

    function focus(i, color) {
      segs.forEach(function (s, j) {
        s.classList.toggle("muted", j !== i);
        s.classList.toggle("focused", j === i);
      });
      legend.querySelectorAll("li").forEach(function (li, j) {
        li.classList.toggle("active", j === i);
      });
      total.textContent = formatNum(labels[i].count);
      total.style.color = color;
    }

    segs.forEach(function (seg, i) {
      seg.addEventListener("mouseenter", function () {
        focus(i, seg.getAttribute("stroke"));
      });
      seg.addEventListener("mouseleave", clear);
    });

    legend.querySelectorAll("li").forEach(function (li, i) {
      li.addEventListener("mouseenter", function () {
        focus(i, segs[i].getAttribute("stroke"));
      });
      li.addEventListener("mouseleave", clear);
    });
  }

  /* ---------- Severity filter ---------- */
  function initSeverityFilter() {
    var pills = document.querySelectorAll("#sevFilter .pill");
    if (!pills.length) return;
    var rows = document.querySelectorAll('#vulns .tbl tbody tr');

    pills.forEach(function (pill) {
      pill.addEventListener("click", function () {
        pills.forEach(function (p) { p.classList.remove("active"); });
        pill.classList.add("active");
        var sev = pill.getAttribute("data-sev");
        rows.forEach(function (row) {
          var show = sev === "all" || row.getAttribute("data-sev") === sev;
          row.style.display = show ? "" : "none";
        });
      });
    });
  }

  /* ---------- Mobile nav ---------- */
  function initNav() {
    var burger = document.getElementById("navBurger");
    var mobile = document.getElementById("navMobile");
    if (!burger || !mobile) return;
    burger.addEventListener("click", function () {
      var open = mobile.classList.toggle("open");
      burger.setAttribute("aria-expanded", String(open));
    });
    mobile.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        mobile.classList.remove("open");
        burger.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---------- Sticky header shadow ---------- */
  function initHeader() {
    var header = document.getElementById("siteHeader");
    if (!header) return;
    function onScroll() {
      header.style.boxShadow = window.scrollY > 8 ? "0 8px 24px rgba(0,0,0,.35)" : "none";
    }
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  document.addEventListener("DOMContentLoaded", function () {
    addReveals();
    initCounters();
    initDonut();
    initSeverityFilter();
    initNav();
    initHeader();

    /* also tag rule lists for fill animation */
    var ruleLists = document.querySelectorAll(".rule-list");
    ruleLists.forEach(function (list) { list.classList.add("reveal"); });
    addReveals();
  });
})();
