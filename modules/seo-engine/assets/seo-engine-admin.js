/**
 * Rezponz SEO Content Engine – Admin JavaScript
 * Global object: RZPA_SEO { ajaxUrl, nonce, adminUrl, placeholders, aiEnabled }
 */
(function ($) {
  'use strict';

  /* ── Config & helpers ─────────────────────────────────────── */
  const cfg = window.RZPA_SEO || {};

  function ajax(action, data, method) {
    return $.ajax({
      url: cfg.ajaxUrl || (window.ajaxurl || '/wp-admin/admin-ajax.php'),
      method: method || 'POST',
      data: Object.assign({ action: action, nonce: cfg.nonce }, data),
    });
  }

  function esc(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ═══════════════════════════════════════════════════════════
     SEO.tabs
     ═══════════════════════════════════════════════════════════ */
  var SEO = {};

  SEO.tabs = {
    init: function () {
      document.querySelectorAll('.seo-tab-bar').forEach(function (bar) {
        var tabs = bar.querySelectorAll('.seo-tab');
        if (!tabs.length) return;

        tabs.forEach(function (tab) {
          tab.addEventListener('click', function () {
            var tabId = tab.dataset.tab;
            if (tabId) SEO.tabs.activate(bar, tabId);
          });
        });

        // Activate first tab
        var firstTab = tabs[0];
        if (firstTab && firstTab.dataset.tab) {
          SEO.tabs.activate(bar, firstTab.dataset.tab);
        }
      });
    },

    activate: function (tabBar, tabId) {
      // Update tab buttons
      tabBar.querySelectorAll('.seo-tab').forEach(function (tab) {
        tab.classList.toggle('active', tab.dataset.tab === tabId);
      });

      // Find content panes – look in next sibling container or parent
      var scope = tabBar.closest('[data-tabs-scope]') || tabBar.parentElement;
      if (!scope) return;
      scope.querySelectorAll('.seo-tab-content').forEach(function (pane) {
        pane.classList.toggle('active', pane.dataset.tabContent === tabId);
      });
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.charCounter
     ═══════════════════════════════════════════════════════════ */
  SEO.charCounter = {
    init: function () {
      document.querySelectorAll('[data-max-length]').forEach(function (input) {
        var counter = document.createElement('span');
        counter.className = 'seo-char-counter';
        input.after(counter);
        SEO.charCounter.update(input);
        input.addEventListener('input', function () {
          SEO.charCounter.update(input);
        });
      });
    },

    update: function (input) {
      var counter = input.nextElementSibling;
      if (!counter || !counter.classList.contains('seo-char-counter')) return;
      var max = parseInt(input.dataset.maxLength, 10);
      var current = (input.value || '').length;
      counter.textContent = current + ' / ' + max;
      counter.classList.toggle('over-limit', current > max);
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.slug
     ═══════════════════════════════════════════════════════════ */
  SEO.slug = {
    init: function () {
      // Auto-generate from source inputs
      document.querySelectorAll('[data-slug-source]').forEach(function (input) {
        var targetId = input.dataset.slugSource;
        input.addEventListener('input', function () {
          var target = document.getElementById(targetId);
          if (target && !target.dataset.slugLocked) {
            target.value = SEO.slug.fromString(input.value);
          }
        });
      });

      // Manual generate buttons
      document.querySelectorAll('[data-slug-fields]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          SEO.slug.generate(btn);
        });
      });
    },

    fromString: function (str) {
      return String(str)
        .toLowerCase()
        .trim()
        .replace(/[æ]/g, 'ae')
        .replace(/[ø]/g, 'oe')
        .replace(/[å]/g, 'aa')
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '');
    },

    generate: function (btn) {
      var fieldIds = (btn.dataset.slugFields || '').split(',');
      var targetId = btn.dataset.slugTarget;
      if (!targetId) return;

      var parts = fieldIds
        .map(function (id) {
          var el = document.getElementById(id.trim());
          return el ? (el.value || '').trim() : '';
        })
        .filter(Boolean);

      var slug = SEO.slug.fromString(parts.join(' '));
      var target = document.getElementById(targetId);
      if (target) target.value = slug;
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.placeholders
     ═══════════════════════════════════════════════════════════ */
  SEO.placeholders = {
    _lastFocused: null,

    init: function () {
      SEO.placeholders.trackFocus();

      document.querySelectorAll('.placeholder-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
          var field = SEO.placeholders._lastFocused;
          if (!field) return;

          var token = '{' + chip.textContent.trim().replace(/[{}]/g, '') + '}';
          var start = field.selectionStart || 0;
          var end = field.selectionEnd || 0;
          var val = field.value || '';
          field.value = val.slice(0, start) + token + val.slice(end);
          field.selectionStart = field.selectionEnd = start + token.length;
          field.focus();
          field.dispatchEvent(new Event('input', { bubbles: true }));
        });
      });
    },

    trackFocus: function () {
      document.querySelectorAll('.seo-pattern-field').forEach(function (field) {
        field.addEventListener('focus', function () {
          SEO.placeholders._lastFocused = field;
        });
      });
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.dynamicList
     ═══════════════════════════════════════════════════════════ */
  SEO.dynamicList = {
    init: function () {
      document.querySelectorAll('[data-list-type]').forEach(function (container) {
        var addBtn = container.querySelector('.btn-add-item');
        if (addBtn) {
          addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            SEO.dynamicList.addItem(container);
          });
        }

        container.addEventListener('click', function (e) {
          var removeBtn = e.target.closest('.dynamic-item-remove');
          if (removeBtn) {
            SEO.dynamicList.removeItem(removeBtn);
          }
        });

        SEO.dynamicList.initSortable(container);
      });
    },

    _buildItem: function (type) {
      var item = document.createElement('div');
      item.className = 'dynamic-item';

      var handle = '<span class="drag-handle" title="Drag to reorder">&#8942;&#8942;</span>';
      var fields = '';

      switch (type) {
        case 'faq':
          fields =
            '<div class="item-fields">' +
            '<input type="text" name="faq_question[]" placeholder="Spørgsmål..." />' +
            '<textarea name="faq_answer[]" rows="2" placeholder="Svar..."></textarea>' +
            '</div>';
          break;
        case 'values':
          fields =
            '<div class="item-fields">' +
            '<input type="text" name="value_point[]" placeholder="Værdipunkt..." />' +
            '</div>';
          break;
        case 'links':
          fields =
            '<div class="item-fields">' +
            '<input type="url" name="link_url[]" placeholder="https://..." />' +
            '<input type="text" name="link_text[]" placeholder="Linktekst..." />' +
            '</div>';
          break;
        case 'keywords':
          fields =
            '<div class="item-fields">' +
            '<input type="text" name="keyword[]" placeholder="Søgeord..." />' +
            '</div>';
          break;
        default:
          fields =
            '<div class="item-fields">' +
            '<input type="text" name="item[]" placeholder="Værdi..." />' +
            '</div>';
      }

      var removeBtn = '<button type="button" class="dynamic-item-remove" title="Fjern">&times;</button>';
      item.innerHTML = handle + fields + removeBtn;
      return item;
    },

    addItem: function (container) {
      var type = container.dataset.listType || 'generic';
      var item = SEO.dynamicList._buildItem(type);
      var addBtn = container.querySelector('.btn-add-item');
      container.insertBefore(item, addBtn || null);

      // Focus first input
      var firstInput = item.querySelector('input, textarea');
      if (firstInput) firstInput.focus();
    },

    removeItem: function (btn) {
      var item = btn.closest('.dynamic-item');
      if (item) {
        item.style.opacity = '0';
        item.style.transition = 'opacity .15s';
        setTimeout(function () { item.remove(); }, 150);
      }
    },

    serialize: function (container) {
      var type = container.dataset.listType;
      var items = container.querySelectorAll('.dynamic-item');
      var result = [];

      items.forEach(function (item) {
        switch (type) {
          case 'faq': {
            var q = item.querySelector('[name="faq_question[]"]');
            var a = item.querySelector('[name="faq_answer[]"]');
            if (q && q.value) result.push({ question: q.value, answer: a ? a.value : '' });
            break;
          }
          case 'links': {
            var url = item.querySelector('[name="link_url[]"]');
            var text = item.querySelector('[name="link_text[]"]');
            if (url && url.value) result.push({ url: url.value, text: text ? text.value : '' });
            break;
          }
          default: {
            var inp = item.querySelector('input');
            if (inp && inp.value) result.push(inp.value);
          }
        }
      });

      return result;
    },

    initSortable: function (container) {
      if (typeof $.fn.sortable === 'undefined') return;
      $(container).sortable({
        items: '.dynamic-item',
        handle: '.drag-handle',
        axis: 'y',
        placeholder: 'dynamic-item sortable-placeholder',
        tolerance: 'pointer',
        cursor: 'grabbing',
      });
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.preview
     ═══════════════════════════════════════════════════════════ */
  SEO.preview = {
    init: function () {
      $(document).on('click', '#btn-preview-template', function (e) {
        e.preventDefault();
        SEO.preview.request();
      });
    },

    request: function () {
      var form = document.getElementById('seo-template-form');
      if (!form) return;

      var data = {};
      $(form).serializeArray().forEach(function (field) {
        data[field.name] = field.value;
      });

      // Attach dynamic lists
      document.querySelectorAll('[data-list-type]').forEach(function (list) {
        var key = list.dataset.listKey || list.dataset.listType;
        data[key] = SEO.dynamicList.serialize(list);
      });

      SEO.preview.loading(true);

      ajax('rzpa_seo_preview_template', data)
        .done(function (res) {
          SEO.preview.render(res.data || res);
        })
        .fail(function () {
          SEO.notices.show('Forhåndsvisning mislykkedes. Prøv igen.', 'error');
        })
        .always(function () {
          SEO.preview.loading(false);
        });
    },

    render: function (result) {
      var area = document.getElementById('seo-preview-area');
      if (!area) return;

      var titleMax = 60;
      var descMax  = 160;

      var titleLen  = (result.meta_title  || '').length;
      var descLen   = (result.meta_desc   || '').length;

      var html =
        '<div class="seo-card" style="margin-bottom:14px">' +
        '<div class="seo-label">Sidetitel (H1)</div>' +
        '<div style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:10px">' + esc(result.h1 || '') + '</div>' +
        '<div class="seo-label">Meta-titel <span class="seo-char-counter ' + (titleLen > titleMax ? 'over-limit' : '') + '">' + titleLen + '/' + titleMax + '</span></div>' +
        '<div style="color:var(--text);margin-bottom:10px">' + esc(result.meta_title || '') + '</div>' +
        '<div class="seo-label">Meta-beskrivelse <span class="seo-char-counter ' + (descLen > descMax ? 'over-limit' : '') + '">' + descLen + '/' + descMax + '</span></div>' +
        '<div style="color:var(--text-muted);font-size:13px">' + esc(result.meta_desc || '') + '</div>' +
        '</div>';

      if (result.content) {
        html +=
          '<div class="seo-card" style="margin-bottom:14px">' +
          '<div class="seo-label">Indhold (forhåndsvisning)</div>' +
          '<div class="preview-content-wrap" style="max-height:400px;overflow-y:auto;color:var(--text);font-size:13px;line-height:1.7">' +
          result.content +
          '</div></div>';
      }

      if (result.quality) {
        html += '<div class="seo-card"><div class="seo-label">Kvalitetstjek</div>';
        var qc = document.createElement('div');
        qc.innerHTML = html;
        area.innerHTML = html;
        var qContainer = document.createElement('div');
        qContainer.className = 'quality-check-list';
        area.querySelector('.seo-card:last-child').appendChild(qContainer);
        SEO.quality.renderChecks(result.quality.checks || [], qContainer);
        SEO.quality.renderScore(result.quality.score || 0, area.querySelector('.seo-card:last-child'));
        return;
      }

      area.innerHTML = html;
    },

    loading: function (show) {
      var area = document.getElementById('seo-preview-area');
      var btn  = document.getElementById('btn-preview-template');
      if (area) area.classList.toggle('seo-loading-overlay', show);
      if (btn) {
        btn.disabled = show;
        if (show) {
          btn.dataset.origText = btn.textContent;
          btn.innerHTML = '<span class="btn-spinner"></span> Genererer...';
        } else {
          btn.innerHTML = btn.dataset.origText || 'Forhåndsvis';
        }
      }
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.csvImport
     ═══════════════════════════════════════════════════════════ */
  SEO.csvImport = {
    _headers:  [],
    _examples: [],
    _mapping:  {},

    init: function () {
      var dropzone  = document.querySelector('.csv-dropzone');
      var fileInput = document.getElementById('csv-file-input');

      if (dropzone) {
        dropzone.addEventListener('click', function () {
          if (fileInput) fileInput.click();
        });
        dropzone.addEventListener('dragover', function (e) {
          e.preventDefault();
          dropzone.classList.add('drag-over');
        });
        dropzone.addEventListener('dragleave', function () {
          dropzone.classList.remove('drag-over');
        });
        dropzone.addEventListener('drop', function (e) {
          e.preventDefault();
          dropzone.classList.remove('drag-over');
          var file = e.dataTransfer.files[0];
          if (file) SEO.csvImport.handleFile(file);
        });
      }

      if (fileInput) {
        fileInput.addEventListener('change', function () {
          if (fileInput.files[0]) SEO.csvImport.handleFile(fileInput.files[0]);
        });
      }

      $(document).on('click', '#btn-csv-mapping', function (e) {
        e.preventDefault();
        SEO.csvImport.submitMapping();
      });

      $(document).on('click', '#btn-csv-import', function (e) {
        e.preventDefault();
        SEO.csvImport.submitImport();
      });
    },

    handleFile: function (file) {
      var formData = new FormData();
      formData.append('action', 'rzpa_seo_csv_parse');
      formData.append('nonce', cfg.nonce);
      formData.append('csv_file', file);

      $.ajax({
        url: cfg.ajaxUrl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
      })
        .done(function (res) {
          var data = res.data || {};
          SEO.csvImport._headers  = data.headers  || [];
          SEO.csvImport._examples = data.examples || [];
          SEO.csvImport.renderMapping(data.headers, data.examples);
        })
        .fail(function () {
          SEO.notices.show('Kunne ikke læse CSV-filen.', 'error');
        });
    },

    renderMapping: function (headers, examples) {
      var wrap = document.getElementById('csv-mapping-wrap');
      if (!wrap) return;

      var dbFields = (cfg.csvFields || [
        { value: '', label: '-- Ignorer --' },
        { value: 'title', label: 'Titel' },
        { value: 'slug', label: 'Slug' },
        { value: 'description', label: 'Beskrivelse' },
        { value: 'keywords', label: 'Søgeord' },
        { value: 'city', label: 'By' },
        { value: 'category', label: 'Kategori' },
      ]);

      var rows = headers.map(function (header, i) {
        var example = (examples[0] || [])[i] || '';
        var opts = dbFields
          .map(function (f) {
            return '<option value="' + esc(f.value) + '">' + esc(f.label) + '</option>';
          })
          .join('');

        return '<tr>' +
          '<td><strong>' + esc(header) + '</strong></td>' +
          '<td style="color:var(--text-muted);font-size:12px">' + esc(example) + '</td>' +
          '<td><select data-col="' + i + '" class="csv-col-map">' + opts + '</select></td>' +
          '</tr>';
      });

      wrap.innerHTML =
        '<table class="csv-mapping-table">' +
        '<thead><tr><th>CSV-kolonne</th><th>Eksempel</th><th>Tilknyt til felt</th></tr></thead>' +
        '<tbody>' + rows.join('') + '</tbody>' +
        '</table>' +
        '<div style="margin-top:12px">' +
        '<button id="btn-csv-mapping" class="seo-btn seo-btn-secondary">Forhåndsvis import &rarr;</button>' +
        '</div>';

      wrap.style.display = 'block';
    },

    submitMapping: function () {
      var mapping = {};
      document.querySelectorAll('.csv-col-map').forEach(function (sel) {
        if (sel.value) mapping[sel.dataset.col] = sel.value;
      });
      SEO.csvImport._mapping = mapping;

      ajax('rzpa_seo_csv_preview', {
        headers:  JSON.stringify(SEO.csvImport._headers),
        examples: JSON.stringify(SEO.csvImport._examples),
        mapping:  JSON.stringify(mapping),
      })
        .done(function (res) {
          var data = res.data || {};
          SEO.csvImport.renderPreview(data.rows || [], data.errors || []);
        })
        .fail(function () {
          SEO.notices.show('Forhåndsvisning mislykkedes.', 'error');
        });
    },

    renderPreview: function (rows, errors) {
      var wrap = document.getElementById('csv-preview-wrap');
      if (!wrap) return;

      var errorMap = {};
      errors.forEach(function (e) { errorMap[e.row] = e.message; });

      var fields = Object.values(SEO.csvImport._mapping);
      var headerCells = fields.map(function (f) { return '<th>' + esc(f) + '</th>'; }).join('');

      var bodyRows = rows.slice(0, 10).map(function (row, idx) {
        var hasError = !!errorMap[idx];
        var cells = fields.map(function (f) { return '<td>' + esc(row[f] || '') + '</td>'; }).join('');
        var errorCell = hasError
          ? '<td colspan="' + fields.length + '" class="csv-error-msg">' + esc(errorMap[idx]) + '</td>'
          : '';
        return '<tr class="' + (hasError ? 'csv-error-row' : '') + '">' + cells + '</tr>' +
          (hasError ? '<tr class="csv-error-row"><td colspan="' + fields.length + '"><span class="csv-error-msg">' + esc(errorMap[idx]) + '</span></td></tr>' : '');
      });

      wrap.innerHTML =
        '<table class="csv-preview-table">' +
        '<thead><tr>' + headerCells + '</tr></thead>' +
        '<tbody>' + bodyRows.join('') + '</tbody>' +
        '</table>' +
        '<p style="font-size:12px;color:var(--text-muted);margin-top:8px">Viser de første ' + Math.min(rows.length, 10) + ' rækker (' + rows.length + ' total)</p>' +
        '<div style="margin-top:12px">' +
        '<button id="btn-csv-import" class="seo-btn seo-btn-primary">Importér ' + rows.length + ' rækker</button>' +
        '</div>';

      wrap.style.display = 'block';
    },

    submitImport: function () {
      var btn = document.getElementById('btn-csv-import');
      if (btn) { btn.disabled = true; btn.textContent = 'Importerer...'; }

      var total   = 0;
      var current = 0;

      ajax('rzpa_seo_csv_import', {
        headers: JSON.stringify(SEO.csvImport._headers),
        mapping: JSON.stringify(SEO.csvImport._mapping),
      })
        .done(function (res) {
          var data = res.data || {};
          SEO.notices.show(
            'Import fuldført: ' + (data.imported || 0) + ' importeret, ' + (data.skipped || 0) + ' sprunget over.',
            'success'
          );
        })
        .fail(function () {
          SEO.notices.show('Import mislykkedes.', 'error');
          if (btn) { btn.disabled = false; btn.textContent = 'Importér'; }
        });
    },

    updateProgress: function (current, total) {
      var bar   = document.querySelector('.csv-import-progress .gen-bar-fill');
      var label = document.querySelector('.csv-import-progress .gen-bar-label');
      if (bar)   bar.style.width = (total > 0 ? Math.round((current / total) * 100) : 0) + '%';
      if (label) label.textContent = current + ' / ' + total;
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.bulkSelect
     ═══════════════════════════════════════════════════════════ */
  SEO.bulkSelect = {
    init: function () {
      $(document).on('change', '.seo-select-all', function () {
        SEO.bulkSelect.toggleAll(this.checked);
      });
      $(document).on('change', '.seo-row-checkbox', function () {
        SEO.bulkSelect.updateCounter();
      });
    },

    toggleAll: function (checked) {
      document.querySelectorAll('.seo-row-checkbox').forEach(function (cb) {
        cb.checked = checked;
      });
      SEO.bulkSelect.updateCounter();
    },

    getSelected: function () {
      return Array.from(document.querySelectorAll('.seo-row-checkbox:checked')).map(function (cb) {
        return cb.value;
      });
    },

    updateCounter: function () {
      var count   = SEO.bulkSelect.getSelected().length;
      var counter = document.querySelector('.seo-bulk-counter');
      if (counter) counter.textContent = count + ' valgte';

      var bulkActions = document.querySelector('.seo-bulk-actions');
      if (bulkActions) bulkActions.style.display = count > 0 ? 'flex' : 'none';
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.linkSuggestions
     ═══════════════════════════════════════════════════════════ */
  SEO.linkSuggestions = {
    _searchTimer: null,

    init: function () {
      var searchInput = document.getElementById('link-post-search');
      var findBtn     = document.getElementById('btn-find-links');

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          clearTimeout(SEO.linkSuggestions._searchTimer);
          var q = searchInput.value.trim();
          if (q.length < 2) return;
          SEO.linkSuggestions._searchTimer = setTimeout(function () {
            SEO.linkSuggestions.search(q);
          }, 350);
        });
      }

      if (findBtn) {
        findBtn.addEventListener('click', function (e) {
          e.preventDefault();
          var postId = document.getElementById('link-source-post-id');
          if (postId && postId.value) {
            SEO.linkSuggestions.findSuggestions(postId.value);
          }
        });
      }

      $(document).on('click', '.btn-insert-link-block', function (e) {
        e.preventDefault();
        var btn    = e.currentTarget;
        var postId = btn.dataset.postId;
        var links  = JSON.parse(btn.dataset.links || '[]');
        SEO.linkSuggestions.insertLinkBlock(postId, links);
      });
    },

    search: function (query) {
      ajax('rzpa_seo_search_posts', { q: query })
        .done(function (res) {
          var posts    = (res.data || {}).posts || [];
          var wrap     = document.getElementById('link-post-results');
          if (!wrap) return;
          wrap.innerHTML = posts.map(function (p) {
            return '<div class="link-post-option" data-id="' + esc(p.ID) + '" style="padding:6px 10px;cursor:pointer;border-radius:5px;transition:background .15s" ' +
              'onmouseover="this.style.background=\'var(--bg-300)\'" onmouseout="this.style.background=\'\'">' +
              esc(p.post_title) + ' <span style="color:var(--text-muted);font-size:11px">(' + esc(p.ID) + ')</span></div>';
          }).join('') || '<div style="padding:8px;color:var(--text-muted);font-size:12px">Ingen resultater</div>';

          wrap.querySelectorAll('.link-post-option').forEach(function (opt) {
            opt.addEventListener('click', function () {
              var sourceId = document.getElementById('link-source-post-id');
              if (sourceId) sourceId.value = opt.dataset.id;
              var input = document.getElementById('link-post-search');
              if (input) input.value = opt.textContent.split('(')[0].trim();
              wrap.innerHTML = '';
            });
          });
        });
    },

    findSuggestions: function (postId) {
      var btn = document.getElementById('btn-find-links');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="btn-spinner"></span> Søger...'; }

      ajax('rzpa_seo_get_link_suggestions', { post_id: postId })
        .done(function (res) {
          var suggestions = (res.data || {}).suggestions || [];
          SEO.linkSuggestions.renderSuggestions(suggestions);
        })
        .fail(function () {
          SEO.notices.show('Kunne ikke hente linkforslag.', 'error');
        })
        .always(function () {
          if (btn) { btn.disabled = false; btn.textContent = 'Find forslag'; }
        });
    },

    renderSuggestions: function (suggestions) {
      var wrap = document.getElementById('link-suggestions-wrap');
      if (!wrap) return;

      if (!suggestions.length) {
        wrap.innerHTML = '<p style="color:var(--text-muted);font-size:13px">Ingen relevante linkforslag fundet.</p>';
        return;
      }

      wrap.innerHTML = suggestions.map(function (s) {
        var score = Math.round((s.score || 0) * 100);
        var color = SEO.quality.scoreColor(score);

        return '<div class="link-suggestion-item">' +
          '<div class="suggestion-info">' +
          '<div class="suggestion-title">' + esc(s.title || '') + '</div>' +
          '<div class="suggestion-url">' + esc(s.url || '') + '</div>' +
          '</div>' +
          '<div class="suggestion-bar-wrap">' +
          '<div class="relevance-bar"><div class="relevance-fill" style="width:' + score + '%;background:' + color + '"></div></div>' +
          '</div>' +
          '<span class="relevance-score" style="color:' + color + ';border-color:' + color + '40;background:' + color + '18">' + score + '%</span>' +
          '<button type="button" class="seo-btn seo-btn-secondary btn-insert-link-block" style="font-size:11px;padding:4px 10px" ' +
          'data-post-id="' + esc(s.post_id || '') + '" data-links=\'[]\'>&rarr; Indsæt</button>' +
          '</div>';
      }).join('');
    },

    insertLinkBlock: function (postId, links) {
      ajax('rzpa_seo_insert_link_block', { post_id: postId, links: JSON.stringify(links) })
        .done(function (res) {
          var data = res.data || {};
          SEO.notices.show(data.message || 'Linkblok indsat.', 'success');
        })
        .fail(function () {
          SEO.notices.show('Kunne ikke indsætte linkblok.', 'error');
        });
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.generate
     ═══════════════════════════════════════════════════════════ */
  SEO.generate = {
    bulkProgress: { current: 0, total: 0 },
    _bulkResults: { good: 0, bad: 0, warn: 0 },

    init: function () {
      $(document).on('click', '.btn-seo-generate-single', function (e) {
        e.preventDefault();
        var btn       = e.currentTarget;
        var datasetId = btn.dataset.datasetId;
        var status    = btn.dataset.targetStatus || 'draft';
        if (datasetId) SEO.generate.singleGenerate(datasetId, status, btn);
      });

      $(document).on('click', '#btn-seo-bulk-generate', function (e) {
        e.preventDefault();
        var ids    = SEO.bulkSelect.getSelected();
        var status = (document.getElementById('bulk-target-status') || {}).value || 'draft';
        if (!ids.length) {
          SEO.notices.show('Vælg mindst ét datasæt.', 'warning');
          return;
        }
        SEO.generate.startBulk(ids, status);
      });
    },

    singleGenerate: function (datasetId, status, btn) {
      if (btn) {
        btn.disabled = true;
        btn.dataset.origText = btn.innerHTML;
        btn.innerHTML = '<span class="btn-spinner"></span>';
      }

      ajax('rzpa_seo_generate_single', { dataset_id: datasetId, status: status })
        .done(function (res) {
          var data = res.data || {};
          SEO.generate.updateRowStatus(datasetId, data);
          SEO.notices.show(data.message || 'Indhold genereret.', 'success');
        })
        .fail(function () {
          SEO.notices.show('Generering mislykkedes for #' + datasetId, 'error');
        })
        .always(function () {
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.origText || 'Generer';
          }
        });
    },

    startBulk: function (ids, status) {
      SEO.generate.bulkProgress = { current: 0, total: ids.length };
      SEO.generate._bulkResults = { good: 0, bad: 0, warn: 0 };

      var progressArea = document.getElementById('seo-bulk-progress');
      if (progressArea) {
        progressArea.innerHTML =
          '<div class="seo-gen-progress">' +
          '<div class="gen-title">Genererer indhold&hellip;</div>' +
          '<div class="gen-bar-wrap"><div class="gen-bar-fill" id="bulk-bar-fill" style="width:0%"></div></div>' +
          '<div class="gen-bar-label" id="bulk-bar-label">0 / ' + ids.length + '</div>' +
          '</div>';
        progressArea.style.display = 'block';
      }

      var resultsArea = document.getElementById('seo-bulk-results');
      if (resultsArea) resultsArea.style.display = 'none';

      SEO.generate._processNext(ids, 0, status);
    },

    _processNext: function (ids, index, status) {
      if (index >= ids.length) {
        SEO.generate._showBulkSummary();
        return;
      }

      var id = ids[index];
      SEO.generate.bulkProgress.current = index + 1;
      SEO.generate._updateBulkBar();

      ajax('rzpa_seo_generate_single', { dataset_id: id, status: status })
        .done(function (res) {
          var data = res.data || {};
          SEO.generate.updateRowStatus(id, data);
          if (data.quality_score >= 70)      SEO.generate._bulkResults.good++;
          else if (data.quality_score >= 40) SEO.generate._bulkResults.warn++;
          else                               SEO.generate._bulkResults.bad++;
        })
        .fail(function () {
          SEO.generate._bulkResults.bad++;
          SEO.generate.updateRowStatus(id, { status: 'failed' });
        })
        .always(function () {
          SEO.generate._processNext(ids, index + 1, status);
        });
    },

    _updateBulkBar: function () {
      var p     = SEO.generate.bulkProgress;
      var pct   = p.total > 0 ? Math.round((p.current / p.total) * 100) : 0;
      var bar   = document.getElementById('bulk-bar-fill');
      var label = document.getElementById('bulk-bar-label');
      if (bar)   bar.style.width = pct + '%';
      if (label) label.textContent = p.current + ' / ' + p.total;
    },

    _showBulkSummary: function () {
      var r = SEO.generate._bulkResults;
      var p = SEO.generate.bulkProgress;

      var resultsArea = document.getElementById('seo-bulk-results');
      if (resultsArea) {
        resultsArea.innerHTML =
          '<div class="seo-gen-results">' +
          '<div class="results-title">Bulk-generering fuldført (' + p.total + ' i alt)</div>' +
          '<div class="gen-stats-row">' +
          '<div class="gen-stat gen-stat-good"><span class="stat-icon">&#10003;</span><span class="stat-count">' + r.good + '</span><span class="stat-label">Gode</span></div>' +
          '<div class="gen-stat gen-stat-warn"><span class="stat-icon">&#9888;</span><span class="stat-count">' + r.warn + '</span><span class="stat-label">Kræver gennemgang</span></div>' +
          '<div class="gen-stat gen-stat-bad"><span class="stat-icon">&#10007;</span><span class="stat-count">' + r.bad + '</span><span class="stat-label">Fejlede</span></div>' +
          '</div></div>';
        resultsArea.style.display = 'block';
      }
    },

    updateRowStatus: function (datasetId, result) {
      var row = document.querySelector('[data-dataset-id="' + datasetId + '"]');
      if (!row) return;

      var statusCell = row.querySelector('.dataset-status-cell');
      if (statusCell && result.status) {
        statusCell.innerHTML = '<span class="status-badge status-' + esc(result.status) + '">' + esc(result.status) + '</span>';
      }

      var qualityCell = row.querySelector('.dataset-quality-cell');
      if (qualityCell && result.quality_score !== undefined) {
        var score = Math.round(result.quality_score);
        var color = SEO.quality.scoreColor(score);
        qualityCell.innerHTML = '<span style="font-weight:700;color:' + color + '">' + score + '</span>';
      }
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.quality
     ═══════════════════════════════════════════════════════════ */
  SEO.quality = {
    renderScore: function (score, container) {
      score = Math.max(0, Math.min(100, Math.round(score)));
      var color = SEO.quality.scoreColor(score);
      var r = 36, cx = 44, cy = 44;
      var circ = 2 * Math.PI * r;
      var offset = circ - (score / 100) * circ;

      var ring =
        '<div class="quality-score-ring" style="width:88px;height:88px">' +
        '<svg width="88" height="88" viewBox="0 0 88 88">' +
        '<circle class="ring-bg" cx="' + cx + '" cy="' + cy + '" r="' + r + '"/>' +
        '<circle class="ring-fill" cx="' + cx + '" cy="' + cy + '" r="' + r + '" ' +
        'stroke="' + color + '" ' +
        'stroke-dasharray="' + circ.toFixed(2) + '" ' +
        'stroke-dashoffset="' + offset.toFixed(2) + '"/>' +
        '</svg>' +
        '<span class="ring-text" style="position:absolute;font-size:18px;font-weight:800;color:' + color + '">' + score + '</span>' +
        '</div>' +
        '<div style="font-size:11px;color:var(--text-muted);text-align:center;margin-top:4px">Kvalitetsscore</div>';

      var scoreEl = document.createElement('div');
      scoreEl.className = 'quality-score';
      scoreEl.innerHTML = ring;
      container.prepend(scoreEl);
    },

    renderChecks: function (checks, container) {
      container.innerHTML = checks.map(function (check) {
        var pass   = check.pass !== false;
        var cls    = pass ? 'check-pass' : 'check-fail';
        var icon   = pass ? '&#10003;' : '&#10007;';
        return '<div class="check-item ' + cls + '">' +
          '<span class="check-icon">' + icon + '</span>' +
          '<span class="check-msg">' + esc(check.message || '') + '</span>' +
          '</div>';
      }).join('');
    },

    scoreColor: function (score) {
      if (score >= 80) return '#34d399';
      if (score >= 60) return '#CCFF00';
      if (score >= 40) return '#fb923c';
      return '#f87171';
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.settings
     ═══════════════════════════════════════════════════════════ */
  SEO.settings = {
    init: function () {
      var rewriteBase = document.getElementById('seo-rewrite-base');
      if (rewriteBase) {
        rewriteBase.addEventListener('change', function () {
          var warning = document.getElementById('rewrite-base-warning');
          if (warning) {
            warning.style.display = 'block';
          }
        });
      }

      $(document).on('click', '#btn-test-ai', function (e) {
        e.preventDefault();
        SEO.settings.testAI();
      });

      $(document).on('click', '#btn-flush-rewrite', function (e) {
        e.preventDefault();
        SEO.settings.confirmFlushRewrite();
      });
    },

    testAI: function () {
      var btn       = document.getElementById('btn-test-ai');
      var indicator = document.querySelector('.ai-status-indicator');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="btn-spinner"></span> Tester...'; }

      ajax('rzpa_seo_test_ai_connection', {})
        .done(function (res) {
          var ok = res.success && (res.data || {}).connected;
          if (indicator) {
            indicator.className = 'ai-status-indicator ' + (ok ? 'connected' : 'disconnected');
            indicator.innerHTML =
              '<span class="status-dot"></span>' +
              (ok ? 'Forbundet' : 'Ikke forbundet');
          }
          SEO.notices.show(
            ok ? 'AI-forbindelse OK.' : 'AI-forbindelse fejlede: ' + ((res.data || {}).message || 'Ukendt fejl.'),
            ok ? 'success' : 'error'
          );
        })
        .fail(function () {
          SEO.notices.show('AI-test mislykkedes.', 'error');
          if (indicator) {
            indicator.className = 'ai-status-indicator disconnected';
            indicator.innerHTML = '<span class="status-dot"></span>Ikke forbundet';
          }
        })
        .always(function () {
          if (btn) { btn.disabled = false; btn.textContent = 'Test forbindelse'; }
        });
    },

    confirmFlushRewrite: function () {
      if (!window.confirm('Flush rewrite-regler? Siden vil midlertidigt gå ned for besøgende.')) return;

      ajax('rzpa_seo_flush_rewrite', {})
        .done(function () {
          SEO.notices.show('Rewrite-regler er opdateret.', 'success');
        })
        .fail(function () {
          SEO.notices.show('Flush mislykkedes.', 'error');
        });
    },
  };

  /* ═══════════════════════════════════════════════════════════
     SEO.notices
     ═══════════════════════════════════════════════════════════ */
  SEO.notices = {
    show: function (message, type) {
      type = type || 'info';

      var icons = {
        success: '&#10003;',
        error:   '&#10007;',
        warning: '&#9888;',
        info:    '&#8505;',
      };

      var wrap = document.getElementById('seo-notices-wrap') ||
                 document.querySelector('.rzpa-seo-wrap') ||
                 document.body;

      var notice = document.createElement('div');
      notice.className = 'seo-notice seo-notice-' + type;
      notice.innerHTML =
        '<span class="notice-icon">' + (icons[type] || '') + '</span>' +
        '<span class="notice-text">' + esc(message) + '</span>' +
        '<button type="button" class="notice-dismiss" title="Luk">&times;</button>';

      notice.querySelector('.notice-dismiss').addEventListener('click', function () {
        SEO.notices.dismiss(notice);
      });

      wrap.prepend(notice);

      // Auto-dismiss success after 5s
      if (type === 'success') {
        setTimeout(function () { SEO.notices.dismiss(notice); }, 5000);
      }
    },

    dismiss: function (notice) {
      notice.style.transition = 'opacity .2s';
      notice.style.opacity = '0';
      setTimeout(function () { notice.remove(); }, 210);
    },

    fromURL: function () {
      try {
        var params = new URLSearchParams(window.location.search);
        if (params.get('updated')) {
          SEO.notices.show(decodeURIComponent(params.get('updated')), 'success');
        }
        if (params.get('error')) {
          SEO.notices.show(decodeURIComponent(params.get('error')), 'error');
        }
      } catch (e) {
        // URLSearchParams not supported – skip
      }
    },
  };

  /* ═══════════════════════════════════════════════════════════
     Log context toggle (event delegation)
     ═══════════════════════════════════════════════════════════ */
  $(document).on('click', '.log-context-toggle', function () {
    var btn  = this;
    var json = btn.nextElementSibling;
    if (json && json.classList.contains('log-context-json')) {
      json.classList.toggle('visible');
      btn.textContent = json.classList.contains('visible') ? 'Skjul kontekst' : 'Vis kontekst';
    }
  });

  /* ═══════════════════════════════════════════════════════════
     Init
     ═══════════════════════════════════════════════════════════ */
  $(document).ready(function () {
    SEO.tabs.init();
    SEO.charCounter.init();
    SEO.slug.init();
    SEO.placeholders.init();
    SEO.dynamicList.init();
    SEO.preview.init();
    SEO.csvImport.init();
    SEO.bulkSelect.init();
    SEO.linkSuggestions.init();
    SEO.generate.init();
    SEO.settings.init();
    SEO.notices.fromURL();
  });

  /* ── Public API ──────────────────────────────────────────── */
  window.RZPA_SEO_Admin = SEO;

})(jQuery);
