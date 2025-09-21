<script>
(() => {
  'use strict';
  const LOG = (...a)=>console.log('[quicksearch]', ...a);

  const CFG = {
    linesContainerSelector: '#quotation-lines',
    addLineButtons: ['#btnAddLine', '#addRowBtn'],
    searchInputId: 'itemQuickSearch',
    fields: { row: '.qline', name: '.q-item-name', desc: '.q-item-desc', qty: '.q-item-qty', 
    unit: '.q-item-unit', rate: '.q-item-rate', idHidden: '.q-item-id', variantHidden: '.q-item-variant-id'},
    searchUrl: `{{ route('items.search') }}`
  };

  const wrap = document.querySelector(CFG.linesContainerSelector);
  let searchEl = document.getElementById(CFG.searchInputId);
  if (!searchEl) return;

  // STAGING TARGET (jika ada)
  const STAGE = {
    name : document.getElementById('stage_name'),
    id   : document.getElementById('stage_item_id'),
    variantId: document.getElementById('stage_item_variant_id'),
    desc : document.getElementById('stage_desc'),
    qty  : document.getElementById('stage_qty'),
    unit : document.getElementById('stage_unit'),
    price: document.getElementById('stage_price'),
    exists(){ return !!this.name; }
  };

  // ===== active-row fallback (kalau tidak pakai staging)
  let activeRow = null;
  wrap?.addEventListener('focusin', (e)=>{ const r = e.target.closest(CFG.fields.row); if (r) activeRow = r; });
  const rows = () => Array.from(wrap?.querySelectorAll(CFG.fields.row) || []);
  const lastRow = () => { const r = rows(); return r[r.length-1] || null; };
  function ensureActiveRow(){
    if (STAGE.exists()) return null; // kalau ada staging, kita tidak pakai baris aktif
    if (activeRow && document.body.contains(activeRow)) return activeRow;
    return lastRow();
  }

  function setValue(el, val){ if (!el) return; el.value = (val ?? ''); el.dispatchEvent(new Event('input', {bubbles:true})); el.dispatchEvent(new Event('change', {bubbles:true})); }
  function setUnitText(el, item){ if (!el) return; const code = String(item.unit_code || 'PCS'); setValue(el, code.toLowerCase()); }

  function applyItem(data){
    if (STAGE.exists()) {
      setValue(STAGE.name, data.name);
      STAGE.id && setValue(STAGE.id, data.id);
      STAGE.id && setValue(STAGE.id, data.item_id || '');
      STAGE.variantId && setValue(STAGE.variantId, data.variant_id || '');
      if (STAGE.desc && !STAGE.desc.value) setValue(STAGE.desc, data.description || '');
      if (STAGE.qty  && (!STAGE.qty.value || STAGE.qty.value === '0')) setValue(STAGE.qty, '1');
      STAGE.unit && setUnitText(STAGE.unit, data);
      STAGE.price && setValue(STAGE.price, data.price);
      return;
    }
    const row = ensureActiveRow(); if (!row) return;
    setValue(row.querySelector(CFG.fields.name), data.name);
    const desc = row.querySelector(CFG.fields.desc); if (desc && !desc.value) setValue(desc, data.description || '');
    const qty  = row.querySelector(CFG.fields.qty); if (qty && (!qty.value || qty.value==='0')) setValue(qty, '1');
    setUnitText(row.querySelector(CFG.fields.unit), data);
    const rate = row.querySelector(CFG.fields.rate); if (rate) setValue(rate, data.price);
    // kalau tidak pakai staging, isi juga hidden di baris (jika ada)
    const hidItem = row.querySelector('.q-item-id');
    const hidVar  = row.querySelector('.q-item-variant-id');
    if (hidItem) hidItem.value = data.item_id || '';
    if (hidVar)  hidVar.value  = data.variant_id || '';
  }

  // load TomSelect bila perlu
  function ensureTomSelect(cb){
    if (window.TomSelect) return cb();
    if (!document.querySelector('link[href*="tom-select"]')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css';
      document.head.appendChild(link);
      const style = document.createElement('style'); style.textContent = '.ts-dropdown{z-index:1060}'; document.head.appendChild(style);
    }
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js';
    s.onload = cb;
    document.head.appendChild(s);
  }

  function init(){
    const ts = new TomSelect(searchEl, {
      valueField : 'id',
      valueField : 'name', // unik karena label varian beda (contoh: "Baju — Blue / M")
      labelField : 'label',
      searchField: ['name','sku'],
      maxOptions : 30,
      preload    : 'focus',
      create     : false,
      persist    : false,
      load: (query, cb)=>{
        const url = `${CFG.searchUrl}?q=${encodeURIComponent(query||'')}`;
        fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
          .then(r => r.json()).then(cb).catch(()=>cb());
      },
      render: {
        option: (d, esc)=>{
          const sku = d.sku ? `<small class="text-muted ms-2">${esc(d.sku)}</small>` : '';
          return `<div>${esc(d.label || d.name)} ${sku}</div>`;
        }
      },
      onChange: function(val){
        const data = this.options[val]; if (!data) return;
        applyItem(data);
        this.clear(true); this.setTextboxValue(''); this.close();
        (STAGE.qty || wrap?.querySelector(CFG.fields.qty))?.focus();
      }
    });
    LOG('ready');
  }

  ensureTomSelect(init);
})();
</script>
