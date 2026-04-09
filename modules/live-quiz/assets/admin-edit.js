/* =============================================================================
   Rezponz Live Quiz — Admin Question Builder
   ============================================================================= */
(function () {
  'use strict';

  const cfg    = window.rzlqEdit || {};
  let questions = Array.isArray(cfg.questions) ? cfg.questions : [];

  const OPTION_COLORS = ['#e85a10', '#3b82f6', '#eab308', '#22c55e'];
  const OPTION_SHAPES = ['▲', '◆', '●', '■'];

  const TYPE_LABELS = {
    multiple_choice: 'Flervalg',
    true_false:      'Sandt/Falsk',
    yes_no:          'Ja/Nej',
    poll:            'Afstemning',
    slider:          'Skala',
  };

  function generateId() {
    return Math.random().toString(36).slice(2, 8);
  }

  function defaultQuestion() {
    return {
      _id:        generateId(),
      type:       'multiple_choice',
      text:       '',
      image_id:   0,
      _image_url: '',
      time_limit: 20,
      points:     1000,
      options: [
        { id: 0, text: '', correct: false },
        { id: 1, text: '', correct: false },
        { id: 2, text: '', correct: false },
        { id: 3, text: '', correct: false },
      ],
    };
  }

  // ── Render ─────────────────────────────────────────────────────────────────

  function render() {
    const container = document.getElementById('rzlq-qbuilder');
    if (!container) return;

    let html = '';

    questions.forEach((q, idx) => {
      html += renderQuestion(q, idx);
    });

    html += `<button type="button" class="rzlq-add-q-btn" onclick="rzlqAddQuestion()">
               + Tilføj spørgsmål
             </button>`;

    container.innerHTML = html;
    attachListeners();
  }

  function renderQuestion(q, idx) {
    const num   = idx + 1;
    const label = TYPE_LABELS[q.type] || q.type;
    const preview = q.text
      ? escHtml(q.text.slice(0, 70)) + (q.text.length > 70 ? '…' : '')
      : '<em>Intet spørgsmål endnu</em>';
    const previewClass = q.text ? '' : ' empty';

    let bodyHtml = renderQuestionBody(q, idx);

    return `
    <div class="rzlq-q-card" id="rzlq-q-${q._id || idx}" data-idx="${idx}">
      <div class="rzlq-q-header" onclick="rzlqToggle(${idx})">
        <span class="rzlq-drag-handle" title="Træk for at flytte">⋮⋮</span>
        <span class="rzlq-q-num">${num}</span>
        <span class="rzlq-q-preview${previewClass}">${preview}</span>
        <span class="rzlq-q-type-badge">${label}</span>
        <button type="button" class="rzlq-q-del" onclick="event.stopPropagation();rzlqDeleteQuestion(${idx})" title="Slet">✕</button>
        <span class="rzlq-q-toggle">▼</span>
      </div>
      <div class="rzlq-q-body">
        ${bodyHtml}
      </div>
    </div>`;
  }

  function renderQuestionBody(q, idx) {
    const imgHtml = `
      <div class="rzlq-img-wrap">
        <div class="rzlq-img-preview" onclick="rzlqPickImage(${idx})">
          ${q._image_url
            ? `<img src="${escAttr(q._image_url)}" alt="">`
            : '🖼️'}
        </div>
        <button type="button" class="rzlq-img-btn" onclick="rzlqPickImage(${idx})">Tilføj billede/GIF</button>
        ${q.image_id ? `<button type="button" class="rzlq-img-remove" onclick="rzlqRemoveImage(${idx})" title="Fjern">✕</button>` : ''}
      </div>`;

    const baseFields = `
      <div class="rzlq-field-row">
        <div class="rzlq-field-group grow">
          <label class="rzlq-label">Spørgsmål</label>
          <input class="rzlq-input" type="text" placeholder="Skriv dit spørgsmål her…"
                 value="${escAttr(q.text)}"
                 oninput="rzlqSetField(${idx}, 'text', this.value)">
        </div>
      </div>

      <div class="rzlq-field-row">
        <div class="rzlq-field-group">
          <label class="rzlq-label">Type</label>
          <select class="rzlq-select" onchange="rzlqSetType(${idx}, this.value)">
            ${Object.entries(TYPE_LABELS).map(([v,l]) =>
              `<option value="${v}" ${q.type===v?'selected':''}>${l}</option>`
            ).join('')}
          </select>
        </div>
        <div class="rzlq-field-group">
          <label class="rzlq-label">Tid (sek.)</label>
          <select class="rzlq-select" onchange="rzlqSetField(${idx},'time_limit',+this.value)">
            ${[5,10,15,20,30,45,60,90,120].map(t =>
              `<option value="${t}" ${q.time_limit==t?'selected':''}>${t}s</option>`
            ).join('')}
          </select>
        </div>
        <div class="rzlq-field-group">
          <label class="rzlq-label">Point</label>
          <select class="rzlq-select" onchange="rzlqSetField(${idx},'points',+this.value)">
            ${[[0,'Ingen'],[500,'500'],[1000,'1000'],[2000,'2000']].map(([v,l]) =>
              `<option value="${v}" ${q.points==v?'selected':''}>${l}</option>`
            ).join('')}
          </select>
        </div>
      </div>
      ${imgHtml}`;

    if (q.type === 'slider') {
      return baseFields + `
        <div class="rzlq-field-row">
          <div class="rzlq-field-group">
            <label class="rzlq-label">Min</label>
            <input class="rzlq-input" type="number" value="${q.min||1}" style="width:80px"
                   oninput="rzlqSetField(${idx},'min',+this.value)">
          </div>
          <div class="rzlq-field-group">
            <label class="rzlq-label">Max</label>
            <input class="rzlq-input" type="number" value="${q.max||10}" style="width:80px"
                   oninput="rzlqSetField(${idx},'max',+this.value)">
          </div>
          <div class="rzlq-field-group">
            <label class="rzlq-label">Korrekt svar</label>
            <input class="rzlq-input" type="number" value="${q.correct||5}" style="width:90px"
                   oninput="rzlqSetField(${idx},'correct',+this.value)">
          </div>
          <div class="rzlq-field-group">
            <label class="rzlq-label">Tolerance ±</label>
            <input class="rzlq-input" type="number" value="${q.tolerance||0}" style="width:80px" min="0"
                   oninput="rzlqSetField(${idx},'tolerance',+this.value)">
          </div>
        </div>`;
    }

    if (q.type === 'true_false' || q.type === 'yes_no') {
      const [opt1, opt2] = q.type === 'true_false'
        ? [{id:0,text:'Sandt'},{id:1,text:'Falsk'}]
        : [{id:0,text:'Ja'},{id:1,text:'Nej'}];
      const opts = q.options && q.options.length >= 2 ? q.options : [
        {id:0,text:opt1.text,correct:false},{id:1,text:opt2.text,correct:false}
      ];
      return baseFields + `
        <div class="rzlq-options">
          <div class="rzlq-options-label">Rigtigt svar</div>
          ${opts.slice(0,2).map((o,oi) => `
            <div class="rzlq-option-row">
              <div class="rzlq-option-color" style="background:${OPTION_COLORS[oi]}">${OPTION_SHAPES[oi]}</div>
              <span style="flex:1;font-size:14px;font-weight:500">${escHtml(o.text||opt1.text)}</span>
              <input type="checkbox" class="rzlq-correct-cb" ${o.correct?'checked':''}
                     onchange="rzlqSetOptionCorrect(${idx},${oi},this.checked)">
              <label class="rzlq-correct-label">Korrekt</label>
            </div>`).join('')}
        </div>`;
    }

    // Multiple choice or poll
    const showCorrect = q.type !== 'poll';
    const opts = q.options || [];
    const canAdd = opts.length < 4;

    return baseFields + `
      <div class="rzlq-options">
        <div class="rzlq-options-label">Svarmuligheder${showCorrect ? ' (marker det/de korrekte)' : ''}</div>
        ${opts.map((o, oi) => `
          <div class="rzlq-option-row">
            <div class="rzlq-option-color" style="background:${OPTION_COLORS[oi]||'#888'}">${OPTION_SHAPES[oi]||'○'}</div>
            <input class="rzlq-option-input" type="text" placeholder="Svar ${oi+1}…"
                   value="${escAttr(o.text)}"
                   oninput="rzlqSetOption(${idx},${oi},'text',this.value)">
            ${showCorrect ? `
              <input type="checkbox" class="rzlq-correct-cb" ${o.correct?'checked':''}
                     onchange="rzlqSetOptionCorrect(${idx},${oi},this.checked)">
              <label class="rzlq-correct-label">Korrekt</label>
            ` : ''}
            ${opts.length > 2 ? `<button type="button" class="rzlq-option-del" onclick="rzlqRemoveOption(${idx},${oi})">✕</button>` : ''}
          </div>`).join('')}
        ${canAdd ? `<button type="button" class="rzlq-add-option-btn" onclick="rzlqAddOption(${idx})">+ Tilføj svarmulighed</button>` : ''}
      </div>`;
  }

  // ── Public API ─────────────────────────────────────────────────────────────

  window.rzlqAddQuestion = function () {
    questions.push(defaultQuestion());
    render();
    // Open the new question
    const card = document.querySelector(`.rzlq-q-card[data-idx="${questions.length-1}"]`);
    if (card) card.classList.add('open');
  };

  window.rzlqDeleteQuestion = function (idx) {
    if (!confirm('Slet dette spørgsmål?')) return;
    questions.splice(idx, 1);
    render();
  };

  window.rzlqToggle = function (idx) {
    const card = document.querySelector(`.rzlq-q-card[data-idx="${idx}"]`);
    if (card) card.classList.toggle('open');
  };

  window.rzlqSetField = function (idx, key, val) {
    if (questions[idx]) questions[idx][key] = val;
    // Update preview text without full re-render
    if (key === 'text') {
      const prev = document.querySelector(`.rzlq-q-card[data-idx="${idx}"] .rzlq-q-preview`);
      if (prev) {
        prev.className = 'rzlq-q-preview' + (val ? '' : ' empty');
        prev.innerHTML = val
          ? escHtml(val.slice(0, 70)) + (val.length > 70 ? '…' : '')
          : '<em>Intet spørgsmål endnu</em>';
      }
    }
  };

  window.rzlqSetType = function (idx, type) {
    const q = questions[idx];
    if (!q) return;
    q.type = type;
    if (type === 'true_false') {
      q.options = [{id:0,text:'Sandt',correct:false},{id:1,text:'Falsk',correct:false}];
    } else if (type === 'yes_no') {
      q.options = [{id:0,text:'Ja',correct:false},{id:1,text:'Nej',correct:false}];
    } else if (type === 'slider') {
      q.min = 1; q.max = 10; q.correct = 5; q.tolerance = 0;
      delete q.options;
    } else {
      if (!q.options || q.options.length < 2) {
        q.options = [
          {id:0,text:'',correct:false},{id:1,text:'',correct:false},
          {id:2,text:'',correct:false},{id:3,text:'',correct:false},
        ];
      }
    }
    render();
    const card = document.querySelector(`.rzlq-q-card[data-idx="${idx}"]`);
    if (card) card.classList.add('open');
  };

  window.rzlqSetOption = function (idx, oi, key, val) {
    if (questions[idx] && questions[idx].options && questions[idx].options[oi]) {
      questions[idx].options[oi][key] = val;
    }
  };

  window.rzlqSetOptionCorrect = function (idx, oi, checked) {
    const q = questions[idx];
    if (!q || !q.options) return;
    // For single-correct types (true_false, yes_no), deselect others
    if (q.type === 'true_false' || q.type === 'yes_no') {
      q.options.forEach((o, i) => o.correct = i === oi && checked);
    } else {
      q.options[oi].correct = checked;
    }
  };

  window.rzlqAddOption = function (idx) {
    const q = questions[idx];
    if (!q || !q.options || q.options.length >= 4) return;
    q.options.push({ id: q.options.length, text: '', correct: false });
    render();
    const card = document.querySelector(`.rzlq-q-card[data-idx="${idx}"]`);
    if (card) card.classList.add('open');
  };

  window.rzlqRemoveOption = function (idx, oi) {
    const q = questions[idx];
    if (!q || !q.options || q.options.length <= 2) return;
    q.options.splice(oi, 1);
    q.options.forEach((o, i) => o.id = i);
    render();
    const card = document.querySelector(`.rzlq-q-card[data-idx="${idx}"]`);
    if (card) card.classList.add('open');
  };

  window.rzlqPickImage = function (idx) {
    var frame = wp.media({ title: 'Vælg billede/GIF', button: { text: 'Vælg' }, multiple: false });
    frame.on('select', function () {
      var att = frame.state().get('selection').first().toJSON();
      questions[idx].image_id   = att.id;
      questions[idx]._image_url = att.url;
      render();
      const card = document.querySelector(`.rzlq-q-card[data-idx="${idx}"]`);
      if (card) card.classList.add('open');
    });
    frame.open();
  };

  window.rzlqRemoveImage = function (idx) {
    questions[idx].image_id   = 0;
    questions[idx]._image_url = '';
    render();
    const card = document.querySelector(`.rzlq-q-card[data-idx="${idx}"]`);
    if (card) card.classList.add('open');
  };

  // Serialize before form submit
  window.rzlqSerialize = function () {
    const el = document.getElementById('rzlq-questions-json');
    if (el) el.value = JSON.stringify(questions);
  };

  function attachListeners() {
    // Auto-serialize on any input change within the builder
    document.getElementById('rzlq-qbuilder')?.addEventListener('input', () => {
      window.rzlqSerialize();
    });
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function escAttr(s) { return escHtml(s); }

  // ── Init ───────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    // Ensure _id on loaded questions
    questions.forEach((q, i) => { if (!q._id) q._id = 'q' + i; });
    render();
    window.rzlqSerialize(); // pre-fill hidden field on load

    // Serialize before form submit
    const form = document.getElementById('rzlq-edit-form');
    if (form) form.addEventListener('submit', window.rzlqSerialize);
  });

})();
