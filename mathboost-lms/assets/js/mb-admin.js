/**
 * MathBoost QCM Question Builder — Admin
 * Format: { text, layout, ans:{a,b,c,d}, correct (letter), corr }
 */
(function ($) {
    'use strict';

    var qList, jsonField, countEl, saveBtn, statusEl;
    var qIdx  = 0;
    var dirty = false;

    $(function () {
        qList     = document.getElementById('mb-questions-list');
        jsonField = document.getElementById('mb-questions-json');
        countEl   = document.getElementById('mb-q-count');
        saveBtn   = document.getElementById('mb-save-questions');
        statusEl  = document.getElementById('mb-save-status');
        if (!qList || !jsonField) return;

        // ── Fetch questions from server on page load ──────────────────────────
        var pidEl  = document.getElementById('mb_qcm_id') || document.getElementById('post_ID');
        var postId = pidEl ? (parseInt(pidEl.value, 10) || 0) : 0;

        if (postId) {
            fetchQuestionsFromServer(postId, function (questions) {
                if (questions !== null) {
                    questions.forEach(function (q) { appendBlock(q); });
                    if (jsonField) jsonField.value = JSON.stringify(questions);
                } else {
                    // Server unreachable — fall back to what PHP rendered in the hidden field
                    loadFromHiddenField();
                }
                updateCount();
            });
        } else {
            // New auto-draft with no post_ID yet
            loadFromHiddenField();
            updateCount();
        }

        // ── Add-question buttons ─────────────────────────────────────────────
        ['mb-add-q', 'mb-add-q-bottom'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) btn.addEventListener('click', function () {
                appendBlock({});
                updateCount();
                markDirty();
                var last = qList.lastElementChild;
                if (last) last.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // ── Remove / move via delegation ─────────────────────────────────────
        qList.addEventListener('click', function (e) {
            var rm = e.target.closest('.mb-remove-q');
            if (rm) {
                if (confirm('Supprimer cette question ?')) {
                    rm.closest('.mb-q-block').remove();
                    renumber();
                    updateCount();
                    markDirty();
                }
                return;
            }
            var mv = e.target.closest('.mb-move-q');
            if (mv) {
                var block = mv.closest('.mb-q-block');
                if (mv.dataset.dir === 'up' && block.previousElementSibling) {
                    qList.insertBefore(block, block.previousElementSibling);
                } else if (mv.dataset.dir === 'down' && block.nextElementSibling) {
                    qList.insertBefore(block.nextElementSibling, block);
                }
                renumber();
                markDirty();
            }
        });

        // Mark dirty on any field change inside the builder
        qList.addEventListener('input',  markDirty);
        qList.addEventListener('change', markDirty);

        // ── Dedicated AJAX save buttons (top + bottom) ──────────────────────
        if (saveBtn) saveBtn.addEventListener('click', saveViaAjax);
        var saveBtnBottom = document.getElementById('mb-save-questions-bottom');
        if (saveBtnBottom) saveBtnBottom.addEventListener('click', saveViaAjax);

        // ── Safety-net: serialize into hidden field on WP form submit ────────
        var postForm = document.getElementById('post');
        if (postForm) postForm.addEventListener('submit', serialize, true);
        $(document).on('click', '#publish, #save-post', function () { serialize(); });

        // ── Import panel ─────────────────────────────────────────────────────
        initImportPanel();
    });

    // ── Fetch questions from server ──────────────────────────────────────────
    // callback(questions[]) on success, callback(null) on failure.
    function fetchQuestionsFromServer(postId, callback) {
        var cfg   = window.mbAdmin || {};
        var url   = cfg.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        var nonce = cfg.nonce || '';

        if (!nonce) {
            // No nonce — can't make authenticated request
            callback(null);
            return;
        }

        if (statusEl) {
            statusEl.className   = 'mb-save-status';
            statusEl.textContent = '⏳ Chargement des questions…';
        }

        $.ajax({
            url:      url,
            type:     'POST',
            dataType: 'json',
            data: {
                action:  'mb_get_questions',
                nonce:   nonce,
                post_id: postId,
            },
            success: function (r) {
                if (statusEl) statusEl.textContent = '';
                if (r && r.success) {
                    callback(r.data.questions || []);
                } else {
                    callback(null);
                }
            },
            error: function () {
                if (statusEl) statusEl.textContent = '';
                callback(null);
            },
        });
    }

    // ── Load from the PHP-rendered hidden field (fallback) ───────────────────
    function loadFromHiddenField() {
        var existing = [];
        try { existing = JSON.parse((jsonField && jsonField.value) || '[]') || []; } catch (e) {}
        existing.forEach(function (q) { appendBlock(q); });
    }

    // ── Dirty / saved state ──────────────────────────────────────────────────
    function markDirty() {
        dirty = true;
        if (jsonField) jsonField.value = JSON.stringify(gatherQuestions());
        if (statusEl) {
            statusEl.className   = 'mb-save-status is-unsaved';
            statusEl.textContent = '● Modifications non sauvegardées';
        }
    }

    function markSaved(msg) {
        dirty = false;
        if (statusEl) {
            statusEl.className   = 'mb-save-status is-saved';
            statusEl.textContent = '✓ ' + (msg || 'Questions sauvegardées');
            setTimeout(function () {
                if (!dirty && statusEl) {
                    statusEl.textContent = '';
                    statusEl.className   = 'mb-save-status';
                }
            }, 3000);
        }
    }

    function markError(msg) {
        if (statusEl) {
            statusEl.className   = 'mb-save-status is-error';
            statusEl.textContent = '✕ ' + (msg || 'Erreur de sauvegarde.');
        }
    }

    function setSaveBtns(disabled, label) {
        ['mb-save-questions', 'mb-save-questions-bottom'].forEach(function (id) {
            var b = document.getElementById(id);
            if (b) { b.disabled = disabled; b.textContent = label; }
        });
    }

    // ── AJAX save ────────────────────────────────────────────────────────────
    function saveViaAjax() {
        var questions = gatherQuestions();
        var json      = JSON.stringify(questions);
        if (jsonField) jsonField.value = json;

        var pidEl  = document.getElementById('mb_qcm_id') || document.getElementById('post_ID');
        var postId = pidEl ? (parseInt(pidEl.value, 10) || 0) : 0;

        if (!postId) {
            if (statusEl) {
                statusEl.className   = 'mb-save-status is-warning';
                statusEl.textContent = '⚠ Publiez d\'abord le QCM, puis cliquez Sauvegarder.';
            }
            return;
        }

        if (!questions.length) {
            if (statusEl) {
                statusEl.className   = 'mb-save-status is-warning';
                statusEl.textContent = '⚠ Aucune question à sauvegarder.';
            }
            return;
        }

        setSaveBtns(true, '⏳ Sauvegarde…');
        if (statusEl) { statusEl.className = 'mb-save-status'; statusEl.textContent = ''; }

        var cfg   = window.mbAdmin || {};
        var url   = cfg.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        var nonce = cfg.nonce || '';
        if (!nonce) {
            var nonceEl = document.getElementById('mb_qcm_nonce');
            if (nonceEl) nonce = nonceEl.value;
        }

        $.ajax({
            url:      url,
            type:     'POST',
            dataType: 'json',
            data: {
                action:    'mb_save_questions',
                nonce:     nonce,
                post_id:   postId,
                questions: json,
            },
            success: function (r) {
                if (r && r.success) {
                    markSaved(r.data ? r.data.message : null);
                    updateCount();
                } else {
                    var msg = (r && r.data && r.data.message) ? r.data.message : 'Échec serveur.';
                    markError(msg);
                }
            },
            error: function (xhr) {
                var detail = xhr.responseText ? xhr.responseText.substring(0, 120) : 'sans réponse';
                markError('Erreur réseau (' + xhr.status + ') : ' + detail);
            },
            complete: function () {
                setSaveBtns(false, '💾 Sauvegarder les questions');
            },
        });
    }

    // ── Collect questions from DOM ────────────────────────────────────────────
    function gatherQuestions() {
        var questions = [];
        qList.querySelectorAll('.mb-q-block').forEach(function (block) {
            var textEl   = block.querySelector('.mb-q-text');
            var layoutR  = block.querySelector('.mb-layout:checked');
            var correctR = block.querySelector('.mb-correct-r:checked');
            var corrEl   = block.querySelector('.mb-q-corr');
            if (!textEl) return; // guard against malformed DOM
            questions.push({
                text:    textEl.value,
                layout:  layoutR  ? layoutR.value  : 'grid',
                ans: {
                    a: (block.querySelector('.mb-ans-a') || {}).value || '',
                    b: (block.querySelector('.mb-ans-b') || {}).value || '',
                    c: (block.querySelector('.mb-ans-c') || {}).value || '',
                    d: (block.querySelector('.mb-ans-d') || {}).value || '',
                },
                correct: correctR ? correctR.value : 'a',
                corr:    corrEl ? corrEl.value : '',
            });
        });
        return questions;
    }

    function serialize() {
        if (jsonField) jsonField.value = JSON.stringify(gatherQuestions());
    }

    // ── Build a question block ────────────────────────────────────────────────
    function appendBlock(data) {
        var idx     = qIdx++;
        var ans     = data.ans || {};
        var correct = data.correct || 'a';
        var layout  = data.layout  || 'grid';

        var radios = '';
        ['a', 'b', 'c', 'd'].forEach(function (l) {
            var chk = (l === correct) ? ' checked' : '';
            radios +=
                '<label class="mb-correct-label">' +
                  '<input type="radio" class="mb-correct-r" name="mbcor_' + idx + '" value="' + l + '"' + chk + '>' +
                  '<span class="mb-radio-lbl mb-lbl-' + l + '">' + l.toUpperCase() + '</span>' +
                '</label>';
        });

        var block = document.createElement('div');
        block.className = 'mb-q-block';
        block.innerHTML =
            '<div class="mb-q-block-header">' +
              '<span class="mb-q-num">Q?</span>' +
              '<div class="mb-q-actions">' +
                '<button type="button" class="button button-small mb-move-q" data-dir="up" title="Monter">▲</button>' +
                '<button type="button" class="button button-small mb-move-q" data-dir="down" title="Descendre">▼</button>' +
                '<button type="button" class="button button-small mb-remove-q" title="Supprimer">✕ Supprimer</button>' +
              '</div>' +
            '</div>' +
            '<div class="mb-q-body">' +

              '<label class="mb-field-label">Texte de la question ' +
                '<span class="mb-hint">(HTML + LaTeX : <code>\\(x^2\\)</code> ou <code>\\[...\\]</code>)</span>' +
              '</label>' +
              '<textarea class="mb-q-text widefat" rows="3" placeholder="Ex : Calculer \\(2x+3=7\\), trouver \\(x\\)."></textarea>' +

              '<div style="margin:10px 0 4px">' +
                '<label class="mb-field-label" style="display:inline;margin-right:16px">Disposition</label>' +
                '<label class="mb-layout-radio">' +
                  '<input type="radio" class="mb-layout" name="mblayout_' + idx + '" value="grid"' +
                  (layout !== 'stack' ? ' checked' : '') + '> Grille (2 colonnes)' +
                '</label>&nbsp;&nbsp;' +
                '<label class="mb-layout-radio">' +
                  '<input type="radio" class="mb-layout" name="mblayout_' + idx + '" value="stack"' +
                  (layout === 'stack' ? ' checked' : '') + '> Colonne (1 par ligne)' +
                '</label>' +
              '</div>' +

              '<label class="mb-field-label">Réponses <span class="mb-hint">(LaTeX OK dans chaque case)</span></label>' +
              '<div class="mb-ans-grid">' +
                '<div class="mb-ans-cell">' +
                  '<span class="mb-ans-badge mb-ans-a-bg">A</span>' +
                  '<textarea class="mb-ans-txt mb-ans-a" rows="2" placeholder="Réponse A"></textarea>' +
                '</div>' +
                '<div class="mb-ans-cell">' +
                  '<span class="mb-ans-badge mb-ans-b-bg">B</span>' +
                  '<textarea class="mb-ans-txt mb-ans-b" rows="2" placeholder="Réponse B"></textarea>' +
                '</div>' +
                '<div class="mb-ans-cell">' +
                  '<span class="mb-ans-badge mb-ans-c-bg">C</span>' +
                  '<textarea class="mb-ans-txt mb-ans-c" rows="2" placeholder="Réponse C"></textarea>' +
                '</div>' +
                '<div class="mb-ans-cell">' +
                  '<span class="mb-ans-badge mb-ans-d-bg">D</span>' +
                  '<textarea class="mb-ans-txt mb-ans-d" rows="2" placeholder="Réponse D"></textarea>' +
                '</div>' +
              '</div>' +

              '<div style="margin:12px 0 8px">' +
                '<label class="mb-field-label">Bonne réponse</label>' +
                '<div class="mb-correct-row">' + radios + '</div>' +
              '</div>' +

              '<label class="mb-field-label">Correction ' +
                '<span class="mb-hint">(HTML + LaTeX — s\'affiche après la réponse)</span>' +
              '</label>' +
              '<textarea class="mb-q-corr widefat" rows="5" ' +
                'placeholder="Ex : &lt;p&gt;La bonne réponse est &lt;strong&gt;A&lt;/strong&gt; car \\(2x = 4\\), donc \\(x = 2\\).&lt;/p&gt;">' +
              '</textarea>' +

            '</div>';

        // Set textarea values via .value to avoid HTML-entity issues with LaTeX
        block.querySelector('.mb-q-text').value = data.text  || '';
        block.querySelector('.mb-ans-a').value  = ans.a      || '';
        block.querySelector('.mb-ans-b').value  = ans.b      || '';
        block.querySelector('.mb-ans-c').value  = ans.c      || '';
        block.querySelector('.mb-ans-d').value  = ans.d      || '';
        block.querySelector('.mb-q-corr').value = data.corr  || '';

        qList.appendChild(block);
        renumber();
    }

    function renumber() {
        qList.querySelectorAll('.mb-q-block').forEach(function (b, i) {
            var el = b.querySelector('.mb-q-num');
            if (el) el.textContent = 'Q' + (i + 1);
        });
    }

    function updateCount() {
        if (!countEl) return;
        var n = qList.querySelectorAll('.mb-q-block').length;
        countEl.textContent = n + ' question' + (n !== 1 ? 's' : '');
    }

    // ── Import Panel ─────────────────────────────────────────────────────────
    function initImportPanel() {
        var toggleBtn   = document.getElementById('mb-toggle-import');
        var panel       = document.getElementById('mb-import-panel');
        var doImportBtn = document.getElementById('mb-do-import');
        var clearBtn    = document.getElementById('mb-clear-import');
        var tplBtn      = document.getElementById('mb-insert-template');
        var codeArea    = document.getElementById('mb-import-code');

        if (!panel) return;

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var collapsed = panel.style.display === 'none';
                panel.style.display = collapsed ? '' : 'none';
                toggleBtn.textContent = collapsed ? '▲ Réduire' : '▼ Ouvrir';
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (codeArea) codeArea.value = '';
                setImportStatus('', '');
            });
        }

        if (tplBtn) {
            tplBtn.addEventListener('click', function () {
                if (!codeArea) return;
                codeArea.value = [
                    'const Q = [',
                    '  {',
                    '    num: 1,',
                    '    text: "Calculer \\\\(\\\\dfrac{3}{4} + \\\\dfrac{1}{4}\\\\) :",',
                    '    layout: "grid",',
                    '    ans: {',
                    '      a: "\\\\(\\\\dfrac{1}{2}\\\\)",',
                    '      b: "\\\\(\\\\dfrac{3}{4}\\\\)",',
                    '      c: "\\\\(1\\\\)",',
                    '      d: "\\\\(\\\\dfrac{5}{4}\\\\)"',
                    '    },',
                    '    correct: "c",',
                    '    corr: "<p>Bonne réponse : <strong>C</strong>. Car \\\\(\\\\frac{3+1}{4}=1\\\\).</p>"',
                    '  }',
                    '];'
                ].join('\n');
                setImportStatus('info', 'Exemple chargé — modifiez puis cliquez Charger.');
            });
        }

        if (doImportBtn) {
            doImportBtn.addEventListener('click', function () {
                if (!codeArea) return;
                var raw = codeArea.value.trim();
                if (!raw) {
                    setImportStatus('error', 'Zone vide — collez votre tableau [{...},...] ici.');
                    return;
                }

                var questions = parseImportCode(raw);
                if (!questions || !questions.length) {
                    setImportStatus('error', 'Format non reconnu. Copiez le tableau [{...},...] depuis votre fichier HTML.');
                    return;
                }

                questions.forEach(function (q) { appendBlock(normalizeImported(q)); });
                updateCount();
                markDirty();
                codeArea.value = '';

                var n = questions.length;
                var nLabel = n + ' question' + (n > 1 ? 's' : '') + ' importée' + (n > 1 ? 's' : '');

                var pidEl  = document.getElementById('mb_qcm_id') || document.getElementById('post_ID');
                var postId = pidEl ? (parseInt(pidEl.value, 10) || 0) : 0;
                if (postId) {
                    setImportStatus('info', nLabel + ' — sauvegarde en cours…');
                    saveViaAjax();
                } else {
                    setImportStatus('ok', nLabel + ' ! Publiez le QCM puis cliquez "Sauvegarder les questions".');
                }

                var last = qList.lastElementChild;
                if (last) setTimeout(function () { last.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 150);
            });
        }
    }

    function setImportStatus(type, msg) {
        var el = document.getElementById('mb-import-status');
        if (!el) return;
        el.textContent = msg;
        el.className = 'mb-import-status' + (type ? ' is-' + type : '');
    }

    // ── Parse the pasted JS/JSON array ────────────────────────────────────────
    function parseImportCode(raw) {
        var cleaned = raw.trim()
            .replace(/^\s*(const|let|var)\s+\w+\s*=\s*/, '')
            .replace(/;\s*$/, '');

        // Method 1: new Function
        try {
            /* eslint-disable no-new-func */
            var r1 = (new Function('return (' + cleaned + ')'))();
            /* eslint-enable no-new-func */
            if (Array.isArray(r1) && r1.length) return r1;
        } catch (_) {}

        // Method 2: pure-regex converter (no eval)
        try {
            var json = cleaned.replace(/`([\s\S]*?)`/g, function (_, tmpl) {
                var s = tmpl.replace(/\\([\s\S])/g, function (m, c) {
                    if (c === 'n')  return '\n';
                    if (c === 'r')  return '\r';
                    if (c === 't')  return '\t';
                    if (c === '\\') return '\\';
                    if (c === '`')  return '`';
                    return c;
                });
                return JSON.stringify(s);
            });
            json = json.replace(/([{,]\s*)([a-zA-Z_$][a-zA-Z0-9_$]*)\s*:/g, '$1"$2":');
            var r2 = JSON.parse(json);
            if (Array.isArray(r2) && r2.length) return r2;
        } catch (_) {}

        return null;
    }

    function normalizeImported(q) {
        if (!q || typeof q !== 'object') return {};

        if (q.ans && typeof q.ans === 'object' && typeof q.correct === 'string') {
            return {
                text:    q.text    || '',
                layout:  q.layout  || 'grid',
                ans:     { a: q.ans.a || '', b: q.ans.b || '', c: q.ans.c || '', d: q.ans.d || '' },
                correct: ['a','b','c','d'].indexOf(q.correct) >= 0 ? q.correct : 'a',
                corr:    q.corr || q.correction || '',
            };
        }

        var letters = ['a', 'b', 'c', 'd'];
        var choices  = Array.isArray(q.choices) ? q.choices : [];
        var correctL = letters[parseInt(q.correct, 10)] || 'a';
        return {
            text:    q.question || q.text || '',
            layout:  'grid',
            ans: {
                a: choices[0] || '',
                b: choices[1] || '',
                c: choices[2] || '',
                d: choices[3] || '',
            },
            correct: correctL,
            corr:    q.explanation || q.corr || '',
        };
    }

})(jQuery);
