<script>
(function () {
  'use strict';

  /* ===== Helper ===== */
  function toNum(v){ if(v==null) return 0; v=String(v).trim(); if(v==='') return 0;
    v=v.replace(/\s/g,''); const c=v.includes(','), d=v.includes('.');
    if(c&&d){v=v.replace(/\./g,'').replace(',', '.')} else {v=v.replace(',', '.')}
    const n=parseFloat(v); return isNaN(n)?0:n; }
  function rupiah(n){ try{return 'Rp '+new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(n)}
    catch(e){const f=(Math.round(n*100)/100).toFixed(2); const [a,b]=f.split('.'); return 'Rp '+a.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+b} }

  /* ===== Item picker di #stage_name ===== */
  (function initStagePicker(){
    const input = document.getElementById('stage_name');
    if (!input || !window.TomSelect) return;
    const opts = (window.SO_ITEM_OPTIONS || []).map(o => ({
      value: String(o.id), label: o.label, unit: o.unit || 'pcs', price: Number(o.price||0),
    }));
    const ts = new TomSelect(input, {
      options: opts, valueField:'value', labelField:'label', searchField:['label'],
      maxOptions:50, create:false, persist:false, allowEmptyOption:true, dropdownParent:'body',
      render:{ option(d,esc){return `<div class="d-flex justify-content-between"><span>${esc(d.label||'')}</span><span class="text-muted small">${esc(d.unit||'')}</span></div>`; } },
      onChange(val){
        const o = this.options[val];
        document.getElementById('stage_item_id').value = o ? o.value : '';
        document.getElementById('stage_item_variant_id').value = '';
        document.getElementById('stage_unit').value  = o ? (o.unit||'pcs') : 'pcs';
        document.getElementById('stage_price').value = o ? String(o.price||0) : '';
      }
    });
    input.addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ e.preventDefault(); const id=document.getElementById('stage_item_id').value; if(id) document.getElementById('stage_add_btn')?.click(); }});
  })();

  /* ===== Lines table + recalc ===== */
  const body   = document.getElementById('linesBody');
  const rowTpl = document.getElementById('rowTpl');
  let lineIdx  = 0;

  const vLinesSubtotal   = document.getElementById('v_lines_subtotal');
  const vTotalDiscAmt    = document.getElementById('v_total_discount_amount');
  const vTotalDiscHint   = document.getElementById('v_total_disc_hint');
  const vTaxableBase     = document.getElementById('v_taxable_base');
  const vTaxPct          = document.getElementById('v_tax_percent');
  const vTaxAmt          = document.getElementById('v_tax_amount');
  const vTotal           = document.getElementById('v_total');
  const totalControls    = document.querySelector('[data-section="discount-total-controls"]');
  const totalDiscTypeSel = document.getElementById('total_discount_type');
  const totalDiscValInp  = document.getElementById('total_discount_value');
  const totalDiscUnit    = document.getElementById('totalDiscUnit');
  const taxInput         = document.getElementById('tax_percent');

  function recalc() {
    let linesSubtotal = 0;
    body.querySelectorAll('tr[data-line-row]').forEach(tr => {
      const qty   = toNum(tr.querySelector('.qty')?.value || '0');
      const price = toNum(tr.querySelector('.price')?.value || '0');
      const dtSel = tr.querySelector('.disc-type');
      const dvInp = tr.querySelector('.disc-value');
      const dt    = dtSel ? dtSel.value : 'amount';
      const dvRaw = toNum(dvInp?.value || '0');
      const lineSubtotal = qty * price;
      let discAmount = 0;
      if (dt === 'percent') discAmount = Math.min(Math.max(dvRaw,0),100) / 100 * lineSubtotal;
      else                  discAmount = Math.min(Math.max(dvRaw,0), lineSubtotal);
      const lineTotal = Math.max(lineSubtotal - discAmount, 0);
      tr.querySelector('.line_subtotal_view').textContent    = rupiah(lineSubtotal);
      tr.querySelector('.line_disc_amount_view').textContent = rupiah(discAmount);
      tr.querySelector('.line_total_view').textContent       = rupiah(lineTotal);
      linesSubtotal += lineTotal;
    });
    vLinesSubtotal.textContent = rupiah(linesSubtotal);

    const mode = (document.querySelector('input[name="discount_mode"]:checked')?.value) || 'total';
    let tdt  = totalDiscTypeSel?.value || 'amount';
    let tdv  = toNum(totalDiscValInp?.value || '0');
    if (mode === 'per_item') { tdt='amount'; tdv=0; }

    const totalDiscAmount = (tdt === 'percent')
      ? Math.min(Math.max(tdv,0),100) / 100 * linesSubtotal
      : Math.min(Math.max(tdv,0), linesSubtotal);

    vTotalDiscAmt.textContent  = rupiah(totalDiscAmount);
    vTotalDiscHint.textContent = (tdt === 'percent' && mode !== 'per_item') ? '(' + (Math.round(Math.min(Math.max(tdv,0),100)*100)/100).toFixed(2) + '%)' : '';
    const base   = Math.max(linesSubtotal - totalDiscAmount, 0);
    const taxPct = toNum(taxInput.value || '0');
    const taxAmt = base * Math.max(taxPct, 0) / 100;
    const total  = base + taxAmt;
    vTaxableBase.textContent = rupiah(base);
    vTaxPct.textContent      = (Math.round(taxPct * 100) / 100).toFixed(2);
    vTaxAmt.textContent      = rupiah(taxAmt);
    vTotal.textContent       = rupiah(total);
  }

  function addLineFromData(d){
    const tr = document.createElement('tr');
    tr.setAttribute('data-line-row','');
    tr.className = 'qline';
    tr.innerHTML = rowTpl.innerHTML.replace(/__IDX__/g, lineIdx);

    tr.querySelector('.q-item-name').value = d.name || '';
    tr.querySelector('.q-item-id').value   = d.item_id || '';
    tr.querySelector('.q-item-variant-id').value = d.item_variant_id || '';
    tr.querySelector('.q-item-desc').value = d.description || '';
    tr.querySelector('.q-item-qty').value  = String(d.qty || 1);
    tr.querySelector('.q-item-unit').value = d.unit || 'pcs';
    tr.querySelector('.q-item-rate').value = String(d.unit_price || 0);

    const dtSel = tr.querySelector('.disc-type');
    const dvInp = tr.querySelector('.disc-value');
    if (d.discount_type) dtSel.value = d.discount_type;
    if (typeof d.discount_value !== 'undefined') dvInp.value = String(d.discount_value);

    tr.querySelector('.removeRowBtn').addEventListener('click', () => { tr.remove(); recalc(); });
    body.appendChild(tr);
    lineIdx++;
  }

  function addLineFromStage(){
    const d = {
      item_id        : (document.getElementById('stage_item_id').value||'').trim(),
      item_variant_id: (document.getElementById('stage_item_variant_id').value||'').trim(),
      name           : (document.getElementById('stage_name').value||'').trim(),
      description    : document.getElementById('stage_desc').value||'',
      qty            : toNum(document.getElementById('stage_qty').value||'1'),
      unit           : (document.getElementById('stage_unit').value||'pcs').trim(),
      unit_price     : toNum(document.getElementById('stage_price').value||'0'),
      discount_type  : 'amount',
      discount_value : 0,
    };
    if (!d.item_id || !d.name) { alert('Pilih item dulu.'); return; }
    if (d.qty <= 0) { alert('Qty minimal 1.'); return; }
    addLineFromData(d);
    // clear stage
    document.getElementById('stage_item_id').value='';
    document.getElementById('stage_item_variant_id').value='';
    document.getElementById('stage_name').value='';
    document.getElementById('stage_desc').value='';
    document.getElementById('stage_qty').value='1';
    document.getElementById('stage_unit').value='pcs';
    document.getElementById('stage_price').value='';
    recalc();
  }

  // public API buat preload dari quotation/repeat
  window.SO_PRELOAD_LINES = function(lines){
    if (!Array.isArray(lines)) return;
    lines.forEach(addLineFromData);
    recalc();
  };

  // wire events
  document.getElementById('stage_add_btn')?.addEventListener('click', addLineFromStage);
  document.getElementById('stage_clear_btn')?.addEventListener('click', () => {
    document.getElementById('stage_item_id').value='';
    document.getElementById('stage_item_variant_id').value='';
    document.getElementById('stage_name').value='';
    document.getElementById('stage_desc').value='';
    document.getElementById('stage_qty').value='1';
    document.getElementById('stage_unit').value='pcs';
    document.getElementById('stage_price').value='';
  });

  // discount total section
  function toggleTotalControls() {
    const mode = (document.querySelector('input[name="discount_mode"]:checked')?.value) || 'total';
    if (mode === 'per_item') {
      totalControls?.classList.add('d-none');
      totalDiscTypeSel.value = 'amount';
      totalDiscValInp.value  = '0';
      totalDiscUnit.textContent = 'IDR';
    } else {
      totalControls?.classList.remove('d-none');
    }
    recalc();
  }
  document.querySelectorAll('input[name="discount_mode"]').forEach(r => r.addEventListener('change', toggleTotalControls));
  totalDiscTypeSel?.addEventListener('change', () => { totalDiscUnit.textContent = (totalDiscTypeSel.value==='percent') ? '%' : 'IDR'; recalc(); });
  totalDiscValInp?.addEventListener('input', recalc);
  body.addEventListener('input', e => {
    if (e.target.classList.contains('qty') || e.target.classList.contains('price') || e.target.classList.contains('disc-value')) recalc();
  });
  body.addEventListener('change', e => {
    if (e.target.classList.contains('disc-type')) {
      e.target.closest('tr')?.querySelector('.disc-unit').textContent = (e.target.value==='percent') ? '%' : 'IDR';
      recalc();
    }
  });

  // PPN auto-sync
  const selCompany = document.getElementById('company_id');
  function syncTax() {
    const opt = selCompany?.selectedOptions?.[0];
    if (!opt) return;
    const taxable = Number(opt.dataset.taxable) === 1;
    const defTax  = Number(opt.dataset.tax || 0);
    taxInput.value   = taxable ? defTax : 0;
    taxInput.readOnly = !taxable;
    recalc();
  }
  selCompany?.addEventListener('change', syncTax);

  // init
  syncTax(); toggleTotalControls(); recalc();
})();
</script>
