/**
 * MathBoost Navigation helpers
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    // Smooth scroll for in-page anchors
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var target = document.querySelector(a.getAttribute('href'));
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    // Auto-format activation code input (XXXX-XXXX-XXXX)
    var codeInput = document.getElementById('mb-activation-code');
    if (codeInput) {
      codeInput.addEventListener('input', function () {
        var v = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        var parts = [];
        for (var i = 0; i < v.length && i < 12; i += 4) {
          parts.push(v.substring(i, i + 4));
        }
        this.value = parts.join('-');
      });
    }

    // Card entrance animation via IntersectionObserver
    var cards = document.querySelectorAll('.mb-level-card, .mb-category-item, .mb-qcm-item');
    if (!cards.length) return;

    if (!('IntersectionObserver' in window)) {
      // No IO support — just make all cards visible immediately
      cards.forEach(function (c) { c.classList.add('is-visible'); });
      return;
    }

    // Mark <html> so CSS can hide cards until observed
    document.documentElement.classList.add('js-io-ready');

    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.05, rootMargin: '0px 0px 40px 0px' });

    cards.forEach(function (c, i) {
      // Stagger the transition-delay for a nice cascade
      c.style.transitionDelay = (i * 40) + 'ms';
      obs.observe(c);
    });
  });
})();
