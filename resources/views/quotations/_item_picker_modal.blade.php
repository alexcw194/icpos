{{-- resources/views/quotations/_item_picker_modal.blade.php --}}
<div class="modal fade" id="itemPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Cari item</label>
        <input id="itemPickerInput" type="text" placeholder="Ketik nama/SKU..." autocomplete="off">
        <div class="form-hint">Pilih dari daftar untuk menambahkan ke quotation.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  'use strict';

  // ========= Context-aware mapping (SO vs Quotation) =========
  const isSO = !!document.getElementById('linesBody'); // SO create page has #linesBody

  const CFG = isSO ? {
    // === selectors for Sales Order page ===
    addLineButtonSelector : '#btnAddLine',
    linesContainerSelector: '#linesBody',
    fields: {
      row : 'tr.line',
      name: 'input[name$="[name]"]',
      desc: 'textarea[name$="[description]"]',
      qty : '.qty',
      unit: 'input[name$="[unit]"]',
      rate: '.price'
    },
    searchUrl: `{{ route('items.search') }}`
  } : {
    // === fallback: old Quotation selectors (biar tetap kerja di halaman quotation) ===
    addLineButtonSelector : '#btnAddLine',
    linesContainerSelector: '#quotation-lines',
    fields: {
      row : '.qline',
      name: '.q-item-name',
      desc: '.q-item-desc',
      qty : '.q-item-qty',
      unit: '.q-item-unit',
      rate: '.q-item-rate',
      idHidden     : '.q-item-id',
      variantHidden: '.q-item-variant-id'
    },
    searchUrl: `{{ route('items.search') }}`
  };

  // ===== Helpers =====
  function ensureTomSelect(cb){
    if (window.TomSelect) return cb();
    if (!document.querySelector('link[href*="tom-select"]')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css';
      document.head.appendChild(link);
      const style = document.createElement('style');
      style.textContent = '.ts-dropdown{z-index:9999}';
      document.head.appendChild(style);
    }
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js';
    s.onload = cb;
    document.head.appendChild(s);
  }

  function addNewRowAndGet(){
    document.querySelector(CFG.addLineButtonSelector)?.click();
    const wrap = document.querySelector(CFG.linesContainerSelector);
    const rows = wrap ? wrap.querySelectorAll(CFG.fields.row) : [];
    return rows.length ? rows[rows.length-1] : null;
  }

  function setValue(el, val){
    if (!el) return;
    el.value = (val ?? '');
    el.dispatchEvent(new Event('input', {bubbles:true}));
    el.dispatchEvent(new Event('change', {bubbles:true}));
  }

  function applyItem(item){
    const row = addNewRowAndGet();
    if (!row) return;

    // item_id & variant_id (quotation only)
    if (CFG.fields.idHidden || CFG.fields.variantHidden) {
      const hidItem = row.querySelector(CFG.fields.idHidden || 'input[name$="[item_id]"]');
      const hidVar = row.querySelector(CFG.fields.variantHidden || 'input[name$="[item_variant_id]"]');
      setValue(hidItem, item.item_id || '');
      setValue(hidVar, item.variant_id || '');
    }

    // name / description
    setValue(row.querySelector(CFG.fields.name), item.name || '');
    const descEl = row.querySelector(CFG.fields.desc);
    if (descEl && !descEl.value) setValue(descEl, item.description || '');

    // qty default 1
    const qtyEl = row.querySelector(CFG.fields.qty);
    if (qtyEl && (!qtyEl.value || +qtyEl.value === 0)) setValue(qtyEl, '1');

    // unit (SO pakai input text)
    const unitEl = row.querySelector(CFG.fields.unit);
    if (unitEl) {
      const code = (item.unit_code || 'PCS');
      setValue(unitEl, unitEl.tagName === 'SELECT'
        ? (item.unit_id ?? unitEl.value)
        : code);
    }

    // unit price
    const rateEl = row.querySelector(CFG.fields.rate);
    if (rateEl) setValue(rateEl, item.price ?? 0);

    // tutup modal
    bootstrap.Modal.getOrCreateInstance(document.getElementById('itemPickerModal')).hide();
  }

  document.addEventListener('DOMContentLoaded', function(){
    const modalEl = document.getElementById('itemPickerModal');
    const openBtn = document.getElementById('btnOpenItemPicker');

    if (openBtn) {
      openBtn.addEventListener('click', () => {
        const m = bootstrap.Modal.getOrCreateInstance(modalEl);
        m.show();
        setTimeout(() => document.getElementById('itemPickerInput')?.focus(), 150);
      });
    }

    // F2: buka modal
    document.addEventListener('keydown', function(e){
      if (e.key === 'F2') {
        e.preventDefault();
        const m = bootstrap.Modal.getOrCreateInstance(modalEl);
        m.show();
        setTimeout(() => document.getElementById('itemPickerInput')?.focus(), 150);
      }
    });

    ensureTomSelect(() => {
      const input = document.getElementById('itemPickerInput');
      if (!input) return;

      new TomSelect(input, {
        valueField : 'uid',
        labelField : 'label',
        searchField: ['name','sku'],
        dropdownParent: modalEl, // penting agar dropdown muncul di atas modal
        maxOptions : 30,
        preload    : 'focus',
        create     : false,
        persist    : false,
        load: (query, cb)=>{
          const url = `${CFG.searchUrl}?q=${encodeURIComponent(query||'')}`;
          fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r => r.json())
            .then(json => cb(json))
            .catch(()=>cb());
        },
        render: {
          option: (data, esc)=>{
            const sku = data.sku ? `<small class="text-muted ms-2">${esc(data.sku)}</small>` : '';
            return `<div>${esc(data.label || data.name)} ${sku}</div>`;
          }
        },
        onChange: function(val){
          // Guard: jangan nambah baris saat clear/blurring
          if (!val) return;
          const data = this.options[val];
          if (!data) return;
          applyItem(data);
          // clear tanpa memicu onChange kedua
          this.clear(true);
        }
      });
    });
  });
})();
</script>
@endpush
