/**
 * MathBoost QCM Engine — matches the standalone HTML design exactly.
 */
(function () {
  'use strict';

  /* ── QCM RENDERER ──────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.MB_QCM_DATA) return;

    var D         = window.MB_QCM_DATA;
    var questions = D.questions || [];
    var total     = questions.length;
    var score     = 0;
    var answered  = 0;
    var container = document.getElementById('qcm-questions');
    if (!container || !total) return;

    // ── Normalise question format (supports old & new) ────────────────────
    questions = questions.map(function (q) {
      // Already new format
      if (q.ans) return q;
      // Old format: { question/statement, choices[], correct (int), explanation }
      var labels = ['a', 'b', 'c', 'd'];
      var ans    = {};
      var choices = q.choices || q.options || [];
      choices.forEach(function (ch, i) { if (i < 4) ans[labels[i]] = ch; });
      return {
        text:    q.question || q.statement || '',
        layout:  'grid',
        ans:     ans,
        correct: labels[typeof q.correct === 'number' ? q.correct : 0] || 'a',
        corr:    q.explanation || q.correction || ''
      };
    });

    // ── Build all question cards ──────────────────────────────────────────
    var html = '';
    questions.forEach(function (q, idx) {
      var num = idx + 1;
      var lc  = q.layout === 'stack' ? 'qcm-answers-stack' : 'qcm-answers-grid';
      var ans = q.ans || {};
      var answersHtml = '<div class="' + lc + '" id="answers-' + num + '">';
      ['a', 'b', 'c', 'd'].forEach(function (l) {
        answersHtml +=
          '<label class="qcm-answer qcm-ans-' + l + '" id="ans-' + num + '-' + l + '">' +
            '<input type="radio" name="q' + num + '" value="' + l + '">' +
            '<span class="qcm-answer-icon"></span>' +
            '<span>' + l.toUpperCase() + ')&nbsp;' + (ans[l] || '') + '</span>' +
          '</label>';
      });
      answersHtml += '</div>';

      html +=
        '<div class="qcm-card" id="card-' + num + '">' +
          '<div class="qcm-q-header">' +
            '<span class="qcm-q-num">Q' + num + '</span>' +
            '<span class="qcm-q-text">' + (q.text || '') + '</span>' +
          '</div>' +
          answersHtml +
          '<div class="qcm-btn-row">' +
            '<button class="qcm-toggle-btn disabled" id="btn-' + num + '" data-num="' + num + '">📖 Voir la correction</button>' +
            '<button class="qcm-report-btn" data-num="' + num + '">⚠️ Signaler une erreur</button>' +
          '</div>' +
          '<div class="qcm-correction" id="corr-' + num + '">' + (q.corr || '') + '</div>' +
        '</div>';
    });
    container.innerHTML = html;

    // Initial MathJax render
    if (window.MathJax && MathJax.typesetPromise) {
      MathJax.typesetPromise([container]);
    }

    // ── Answer selection ──────────────────────────────────────────────────
    container.addEventListener('change', function (e) {
      if (e.target.type !== 'radio' || !e.target.name) return;
      var num  = parseInt(e.target.name.replace('q', ''), 10);
      var sel  = e.target.value;
      var q    = questions[num - 1];
      var cor  = q.correct;

      var card = document.getElementById('card-' + num);
      if (card.classList.contains('qcm-answered')) return;
      card.classList.add('qcm-answered');

      ['a', 'b', 'c', 'd'].forEach(function (l) {
        var el = document.getElementById('ans-' + num + '-' + l);
        if (!el) return;
        if (l === cor) {
          el.classList.add(sel === cor ? 'selected-correct' : 'show-correct');
        } else if (l === sel) {
          el.classList.add('selected-wrong');
        }
      });

      if (sel === cor) score++;
      answered++;
      updateScoreBar();

      var btn = document.getElementById('btn-' + num);
      if (btn) btn.classList.remove('disabled');

      if (window.MathJax && MathJax.typesetPromise) {
        MathJax.typesetPromise([card]);
      }

      // Auto-scroll to next question
      if (answered < total) {
        var nextCard = document.getElementById('card-' + (num + 1));
        if (nextCard) {
          setTimeout(function () {
            nextCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }, 600);
        }
      }

      if (answered >= total) showBilan();
    });

    // ── Toggle correction ─────────────────────────────────────────────────
    container.addEventListener('click', function (e) {
      var btn = e.target.closest('.qcm-toggle-btn');
      if (!btn || btn.classList.contains('disabled')) return;
      var num  = btn.getAttribute('data-num');
      var corr = document.getElementById('corr-' + num);
      var open = corr.classList.contains('visible');
      corr.classList.toggle('visible', !open);
      btn.classList.toggle('open', !open);
      btn.innerHTML = open ? '📖 Voir la correction' : '📕 Masquer la correction';
      if (!open && window.MathJax && MathJax.typesetPromise) {
        MathJax.typesetPromise([corr]);
      }
    });

    // ── Report error modal ────────────────────────────────────────────────
    container.addEventListener('click', function (e) {
      var btn = e.target.closest('.qcm-report-btn');
      if (!btn) return;
      var num   = btn.getAttribute('data-num');
      var modal = document.getElementById('report-modal');
      var label = document.getElementById('modal-question-label');
      if (!modal) return;
      modal.classList.add('open');
      modal.setAttribute('data-qnum', num);
      if (label) label.textContent = 'Question ' + num + ' / ' + total;
    });

    // Close modal
    ['modal-close-btn', 'modal-cancel-btn'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('click', closeModal);
    });
    document.addEventListener('click', function (e) {
      var modal = document.getElementById('report-modal');
      if (modal && e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });

    function closeModal() {
      var modal = document.getElementById('report-modal');
      if (modal) modal.classList.remove('open');
    }

    // Send report via AJAX
    var sendBtn = document.getElementById('modal-send-btn');
    if (sendBtn) {
      sendBtn.addEventListener('click', function () {
        var modal = document.getElementById('report-modal');
        var msgEl = document.getElementById('report-msg');
        if (!modal || !msgEl) return;
        var msg = msgEl.value.trim();
        if (!msg) { msgEl.style.borderColor = '#E00000'; return; }

        var fd = new FormData();
        fd.append('action',       'mb_report_error');
        fd.append('nonce',        D.nonce);
        fd.append('qcm_id',       D.qcmId);
        fd.append('question_num', modal.getAttribute('data-qnum'));
        fd.append('message',      msg);

        fetch(D.ajaxUrl, { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (r) {
            msgEl.value = '';
            closeModal();
            alert(r.data ? r.data.message : 'Envoyé !');
          });
      });
    }

    // ── Score bar ─────────────────────────────────────────────────────────
    function updateScoreBar() {
      var fill  = document.getElementById('score-fill');
      var badge = document.getElementById('score-badge');
      if (fill)  fill.style.width   = Math.round(score / total * 100) + '%';
      if (badge) badge.textContent  = score + ' / ' + total;
    }

    // ── Final score bilan ─────────────────────────────────────────────────
    function showBilan() {
      var bilan = document.getElementById('score-bilan');
      if (!bilan) return;

      var pct   = Math.round(score / total * 100);
      var emoji = '', msg = '', coupe = false;

      if (pct === 100) {
        coupe = true;
        emoji = '';
        msg   = 'PARFAIT ! 🎉 Tu maîtrises ce chapitre comme un pro ! Continue sur cette lancée !';
      } else if (pct >= 90) {
        emoji = '🌟🌟🌟';
        msg   = 'Excellent ! Quelques petites erreurs — relis les corrections, le sans-faute est à ta portée !';
      } else if (pct >= 75) {
        emoji = '😊👍';
        msg   = 'Bien joué ! Tu es sur la bonne voie. Chaque erreur est une chance de progresser — relis les corrections !';
      } else if (pct >= 55) {
        emoji = '📚💪';
        msg   = "Bon début ! Reprends les corrections calmement, identifie tes points faibles et reviens : tu vas progresser !";
      } else if (pct >= 30) {
        emoji = '🤔📖';
        msg   = "Ne te décourage pas ! Relis les rappels de cours dans les corrections et retente le QCM.";
      } else {
        emoji = '😅🔄';
        msg   = "Le plus important, c'est d'avoir essayé ! Reprends le cours depuis le début et reviens — tu vas progresser !";
      }

      var coupeEl = document.getElementById('bilan-coupe');
      var emojiEl = document.getElementById('bilan-emoji');
      var scoreEl = document.getElementById('bilan-score');
      var msgEl   = document.getElementById('bilan-msg');

      if (coupeEl) coupeEl.style.display = coupe ? '' : 'none';
      if (emojiEl) emojiEl.textContent   = emoji;
      if (scoreEl) scoreEl.textContent   = score + ' / ' + total;
      if (msgEl)   msgEl.textContent     = msg;

      bilan.classList.add('visible');
      setTimeout(function () {
        bilan.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 300);
    }

    updateScoreBar(); // init
  });

  /* ── ACTIVATION CODE FORM ──────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    var btn   = document.getElementById('mb-activate-btn');
    var input = document.getElementById('mb-activation-code');
    var msgEl = document.getElementById('mb-activation-msg');
    if (!btn || !input) return;

    var cfg = window.mbConfig || {};

    btn.addEventListener('click', function () {
      var code = input.value.trim();
      if (!code) return;
      btn.disabled    = true;
      btn.textContent = '...';

      var fd = new FormData();
      fd.append('action', 'mb_activate_code');
      fd.append('nonce',  cfg.activateNonce || '');
      fd.append('code',   code);

      fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (msgEl) {
            msgEl.style.display = 'block';
            msgEl.className     = 'mb-activation-msg ' + (r.success ? 'is-success' : 'is-error');
            msgEl.innerHTML     = (r.data ? r.data.message : 'Erreur');
            if (r.success && cfg.resourcesUrl) {
              msgEl.innerHTML +=
                ' <a href="' + cfg.resourcesUrl + '" class="mb-btn mb-btn-start" style="margin-top:12px;display:inline-block">' +
                '🚀 Accéder aux ressources</a>';
            }
          }
          if (r.success) {
            setTimeout(function () {
              window.location.href = cfg.resourcesUrl || location.href;
            }, 3000);
          }
        })
        .finally(function () {
          btn.disabled    = false;
          btn.textContent = 'Activer';
        });
    });

    // Auto-format code input: XXXX-XXXX-XXXX
    input.addEventListener('input', function () {
      var v = input.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
      var parts = [];
      for (var i = 0; i < v.length && i < 12; i += 4) {
        parts.push(v.substring(i, i + 4));
      }
      input.value = parts.join('-');
    });
  });

  /* ── PAYPAL BUTTONS ────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.MB_PAYPAL || !window.paypal) return;
    var cfg       = window.MB_PAYPAL;
    var container = document.getElementById('mb-paypal-container');
    var status    = document.getElementById('mb-paypal-status');
    if (!container) return;

    function showStatus(success, msg) {
      if (!status) return;
      status.style.display = 'block';
      status.className     = 'mb-activation-msg ' + (success ? 'is-success' : 'is-error');
      status.textContent   = msg;
    }

    window.paypal.Buttons({
      style: { layout: 'vertical', color: 'blue', shape: 'rect', label: 'pay' },

      createOrder: function (data, actions) {
        return actions.order.create({
          purchase_units: [{
            amount: { value: cfg.price, currency_code: cfg.currency },
            description: 'MathBoost Premium'
          }]
        });
      },

      onApprove: function (data, actions) {
        showStatus(true, 'Traitement en cours…');
        return actions.order.capture().then(function () {
          var fd = new FormData();
          fd.append('action',   'mb_paypal_confirm');
          fd.append('nonce',    cfg.nonce);
          fd.append('order_id', data.orderID);
          return fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
              showStatus(r.success, r.data ? r.data.message : 'Erreur inattendue.');
              if (r.success) setTimeout(function () { location.reload(); }, 2500);
            })
            .catch(function () {
              showStatus(false, 'Erreur réseau. Votre paiement a été reçu — contactez le support si l\'accès n\'est pas activé.');
            });
        });
      },

      onCancel: function () {
        showStatus(false, 'Paiement annulé. Vous pouvez réessayer quand vous le souhaitez.');
      },

      onError: function (err) {
        showStatus(false, 'Erreur PayPal. Veuillez réessayer ou contacter le support.');
        console.error('PayPal error:', err);
      }

    }).render('#mb-paypal-container');
  });

})();
