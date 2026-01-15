@php
  // Hasil: "/api/inventory/rows/search"
  $SEARCH_URL = route('inventory.rows.search', [], false);
@endphp

<script>
(() => {
  'use strict';

  const CFG = {
    linesContainerSelector: '#quotation-lines',
    searchInputId: 'itemQuickSearch',
    fields: {
      row: '.qline', name: '.q-item-name', desc: '.q-item-desc',
      qty: '.q-item-qty', unit: '.q-item-unit', rate: '.q-item-rate',
      idHidden: '.q-item-id', variantHidden: '.q-item-variant-id',
    },
    searchUrl: @json($SEARCH_URL),
  };

  const wrap = document.querySelector(CFG.linesContainerSelector);
  const searchEl = document.getElementById(CFG.searchInputId);
  if (!searchEl) return;

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

  let activeRow = null;
  wrap?.addEventListener('focusin', (e)=>{ const r=e.target.closest(CFG.fields.row); if (r) activeRow=r; });
  const rows = () => Array.from(wrap?.querySelectorAll(CFG.fields.row) || []);
  const lastRow = () => { const r = rows(); return r[r.length-1] || null; };
  const ensureActiveRow = () => STAGE.exists()? null : (activeRow && document.body.contains(activeRow) ? activeRow : lastRow());

  function setValue(el,val){ if(!el) return; el.value = (val ?? ''); el.dispatchEvent(new Event('input',{bubbles:true})); el.dispatchEvent(new Event('change',{bubbles:true})); }
  function setUnitText(el,item){ if(!el) return; const code = String(item.unit_code || 'PCS'); setValue(el, code.toLowerCase()); }

  function applyItem(d){
    if (STAGE.exists()){
      setValue(STAGE.name, d.name);
      STAGE.id && setValue(STAGE.id, d.item_id || d.id || '');
      STAGE.variantId && setValue(STAGE.variantId, d.variant_id || '');
      if (STAGE.desc && !STAGE.desc.value) setValue(STAGE.desc, d.description || '');
      if (STAGE.qty  && (!STAGE.qty.value || STAGE.qty.value === '0')) setValue(STAGE.qty, '1');
      STAGE.unit && setUnitText(STAGE.unit, d);
      STAGE.price && setValue(STAGE.price, d.price);
      return;
    }
    const row = ensureActiveRow(); if (!row) return;
    setValue(row.querySelector(CFG.fields.name), d.name);
    const desc = row.querySelector(CFG.fields.desc); if (desc && !desc.value) setValue(desc, d.description || '');
    const qty  = row.querySelector(CFG.fields.qty);  if (qty  && (!qty.value || qty.value==='0')) setValue(qty, '1');
    setUnitText(row.querySelector(CFG.fields.unit), d);
    const rate = row.querySelector(CFG.fields.rate); if (rate) setValue(rate, d.price);

    const hidItem = row.querySelector(CFG.fields.idHidden);
    const hidVar  = row.querySelector(CFG.fields.variantHidden);
    if (hidItem) hidItem.value = d.item_id || d.id || '';
    if (hidVar)  hidVar.value  = d.variant_id || '';
  }

  if (!window.TomSelect) { console.error('TomSelect not found'); return; }

  const ts = new TomSelect(searchEl, {
    valueField : 'uid',
    labelField : 'label',
    searchField: ['name','sku','label'],
    maxOptions : 30,
    minLength  : 0,
    preload    : 'focus',
    shouldLoad : () => true,
    create     : false,
    persist    : false,
    dropdownParent: 'body',

    load(query, cb){
      const url = `${CFG.searchUrl}?q=${encodeURIComponent(query || '')}&limit=200`;

      fetch(url, {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.text())
        .then(t => {
          // buang BOM & spasi awal yang bikin r.json() gagal
          const clean = t.replace(/^\uFEFF/, '').trimStart();
          let data = [];
          try {
            data = JSON.parse(clean);
          } catch (e) {
            console.error('[quicksearch] JSON parse fail. Raw:', clean, e);
            cb(); return;
          }
          console.log('[quicksearch] data:', data);
          cb(Array.isArray(data) ? data : []);
        })
        .catch(err => {
          console.error('[quicksearch] fetch error', err);
          cb();
        });
    },

    render: {
      option(d, esc){
        const sku = d.sku ? `<small class="text-muted ms-2">${esc(d.sku)}</small>` : '';
        return `<div>${esc(d.label || d.name)} ${sku}</div>`;
      }
    },

    onChange(val){
      const data = this.options[val];
      if (!data) return;

      // Simpan posisi scroll saat ini (mobile sering auto-scroll pas focus pindah)
      const y = window.scrollY;

      applyItem(data);

      // Close dropdown & reset search box
      this.clear(true); this.setTextboxValue(''); this.close();

      // Fokus ke field berikutnya: Description dulu (biar user baca item yang kepilih tanpa “ketarik scroll”)
      const next = (STAGE.desc || wrap?.querySelector(CFG.fields.desc) || STAGE.qty || wrap?.querySelector(CFG.fields.qty));
      if (next) {
        // preventScroll support di mobile modern, fallback ke scroll restore
        try { next.focus({ preventScroll: true }); } catch(e){ next.focus(); }
        setTimeout(() => window.scrollTo(0, y), 0);
      }
    }
  });
})();
</script>
