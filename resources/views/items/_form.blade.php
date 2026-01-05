{{-- Dipakai oleh create & edit --}}
@if ($errors->any())
  <div class="alert alert-danger m-3">
    <div class="fw-bold mb-1">Periksa input:</div>
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@php
  $v = fn($field, $default = '') => old($field, isset($item) ? ($item->{$field} ?? $default) : $default);

  // Unit terpilih (default ke PCS saat create)
  $selectedUnitId = old(
    'unit_id',
    isset($item) ? ($item->unit_id ?? '') : ($defaultUnitId ?? '')
  );

  // Nilai awal tipe & flags
  $currentType = old('item_type', isset($item) ? ($item->item_type ?? 'standard') : 'standard');
  $variantMode = old('variant_type', isset($item) ? ($item->variant_type ?? 'none') : 'none');
  $hasVariants = isset($item)
    ? ($item->relationLoaded('variants') ? $item->variants->isNotEmpty() : $item->variants()->exists())
    : false;
  $hideAttributeCard = !is_null($variantMode) ? ($variantMode !== 'none' || $hasVariants || !isset($item)) : (!isset($item) || $hasVariants);
  $sellableOld = old('sellable', isset($item) ? (int)$item->sellable : 1);
  $purchOld    = old('purchasable', isset($item) ? (int)$item->purchasable : 1);

  // Master Size/Color terpilih
  $selectedSizeId  = old('size_id',  isset($item) ? ($item->size_id ?? '') : '');
  $selectedColorId = old('color_id', isset($item) ? ($item->color_id ?? '') : '');

  // URL saat ini untuk return setelah kelola master
  $returnUrl = request()->fullUrl();
@endphp

<div class="card-body">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Nama Item</label>
      <input type="text" name="name" value="{{ $v('name') }}" class="form-control" required>
      @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
      <label class="form-label">SKU (opsional)</label>
      <input type="text" name="sku" value="{{ $v('sku') }}" class="form-control">
      @error('sku')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">SKU akan otomatis di-uppercase.</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Unit</label>
      <select name="unit_id" id="unit_id" class="form-select" required>
        <option value="">— Pilih Unit —</option>
        @foreach ($units as $u)
          <option value="{{ $u->id }}" {{ (string)$selectedUnitId === (string)$u->id ? 'selected' : '' }}>
            {{ $u->code }} — {{ $u->name }} @if(!$u->is_active) (nonaktif) @endif
          </option>
        @endforeach
      </select>
      @error('unit_id')<div class="text-danger small">{{ $message }}</div>@enderror
      @isset($defaultUnitId)
        @if(!isset($item))
          <div class="form-hint">Default ke <b>PCS</b> bila tersedia.</div>
        @endif
      @endisset
    </div>

    <div class="col-md-4">
      <label class="form-label">Brand (opsional)</label>
      <select name="brand_id" id="brand_id" class="form-select">
        <option value="">— Tanpa Brand —</option>
        @foreach ($brands as $b)
          <option value="{{ $b->id }}" @selected((int)$v('brand_id') === (int)$b->id)>{{ $b->name }}</option>
        @endforeach
      </select>
      @error('brand_id')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
      <label class="form-label">Harga</label>
      <input type="text" name="price" value="{{ $v('price') }}" class="form-control" inputmode="decimal" autocomplete="off" required>
      @error('price')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">Format ID diterima (mis. <code>1.234,56</code>).</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Stok</label>
      <input type="number" name="stock" value="{{ $v('stock', 0) }}" class="form-control" min="0" step="1" required>
      @error('stock')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
  </div>

  {{-- =================== VARIAN & CUTTING =================== --}}
  <div class="row g-3 mt-3">
    <div class="col-md-4">
      <label class="form-label">Tipe Item</label>
      <select name="item_type" id="item_type" class="form-select" required>
        @foreach (['standard'=>'Standard','kit'=>'Kit/Bundel','cut_raw'=>'Raw Roll (dipotong)','cut_piece'=>'Finished Piece (hasil potong)'] as $k=>$lbl)
          <option value="{{ $k }}" {{ $currentType === $k ? 'selected' : '' }}>{{ $lbl }}</option>
        @endforeach
      </select>
      @error('item_type')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">
        Pilih sesuai perilaku stok.
        <a href="#" id="helpTypeToggle">Lihat penjelasan</a>
      </div>
      <div id="helpTypeBox" class="small text-muted border rounded p-2 mt-2" style="display:none">
        <ul class="mb-0">
          <li><b>Standard</b> — Barang biasa; stok bertambah saat dibeli & berkurang saat dijual/dikirim.</li>
          <li><b>Kit/Bundel</b> — Produk gabungan beberapa item. Stok kit diambil dari komponen saat pengiriman (fase Kit).</li>
          <li><b>Raw Roll (dipotong)</b> — Bahan gulungan/panjang (mis. firehose 60m). Biasanya <i>purchasable</i> saja. Wajib isi <b>Default Roll Length</b>.</li>
          <li><b>Finished Piece (hasil potong)</b> — Item potongan siap jual (mis. 20m/30m). Wajib isi <b>Length per Piece</b>. Disarankan set <b>Parent</b> ke item RAW asalnya.</li>
        </ul>
      </div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Family Code (opsional)</label>
      <input type="text" name="family_code" value="{{ $v('family_code') }}" class="form-control" list="familyCodeList" placeholder="mis. FIREHOSE / TSHIRT">
      @error('family_code')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">Kode pendek untuk mengelompokkan lini produk (ringan, seperti “keluarga”).</div>
      @isset($familyCodes)
        <datalist id="familyCodeList">
          @foreach($familyCodes as $code)
            <option value="{{ $code }}">
          @endforeach
        </datalist>
      @endisset
    </div>

    <div class="col-md-4">
      <label class="form-label">Parent (opsional)</label>
      <select name="parent_id" id="parent_id" class="form-select">
        <option value="">— Tanpa Parent —</option>
        @isset($parents)
          @foreach($parents as $p)
            <option value="{{ $p->id }}" @selected((string)old('parent_id', isset($item)?$item->parent_id:'') === (string)$p->id)>{{ $p->name }}</option>
          @endforeach
        @endisset
      </select>
      @error('parent_id')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">
        Untuk mengelompokkan varian (size/color) ke induk, atau mengikat <b>Finished Piece</b> ke item <b>Raw Roll</b>.
      </div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Sellable</label>
      <input type="hidden" name="sellable" value="0">
      <label class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="sellable" value="1" {{ (int)$sellableOld ? 'checked' : '' }}>
        <span class="form-check-label">Bisa dijual</span>
      </label>
      @error('sellable')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">Contoh: untuk <b>Raw Roll</b>, biasanya non-sellable; yang dijual hasil potongnya.</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Purchasable</label>
      <input type="hidden" name="purchasable" value="0">
      <label class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="purchasable" value="1" {{ (int)$purchOld ? 'checked' : '' }}>
        <span class="form-check-label">Bisa dibeli</span>
      </label>
      @error('purchasable')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">Contoh: <b>Raw Roll</b> biasanya purchasable; <b>Kit</b> bisa diatur non-purchasable.</div>
    </div>

    {{-- Cutting fields --}}
    <div class="col-md-4 row-cut-raw">
      <label class="form-label">Default Roll Length (untuk Raw)</label>
      <input type="text" name="default_roll_length" value="{{ old('default_roll_length', $item->default_roll_length ?? '') }}" class="form-control" inputmode="numeric" autocomplete="off" placeholder="mis. 60">
      @error('default_roll_length')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">Wajib untuk tipe <b>Raw Roll</b> (panjang asal per roll).</div>
    </div>

    <div class="col-md-4 row-cut-piece">
      <label class="form-label">Length per Piece (untuk Finished Piece)</label>
      <input type="text" name="length_per_piece" value="{{ old('length_per_piece', $item->length_per_piece ?? '') }}" class="form-control" inputmode="numeric" autocomplete="off" placeholder="mis. 20 / 30 / 60">
      @error('length_per_piece')<div class="text-danger small">{{ $message }}</div>@enderror
      <div class="form-hint">Wajib untuk tipe <b>Finished Piece</b> (panjang per potongan).</div>
    </div>

    <div class="col-12">
      <label class="form-label">Deskripsi</label>
      <textarea name="description" class="form-control" rows="3">{{ $v('description') }}</textarea>
      @error('description')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

@push('styles')
<style>
  /* tampil/hidden tergantung tipe */
  .row-cut-raw, .row-cut-piece { display:none; }
  .type-cut_raw  .row-cut-raw  { display:block; }
  .type-cut_piece .row-cut-piece{ display:block; }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const sel  = document.getElementById('item_type');
  const form = sel?.closest('form') || document;
  const card = form.querySelector('.card') || form;

  function apply(v){
    card.classList.remove('type-standard','type-kit','type-cut_raw','type-cut_piece');
    card.classList.add('type-'+(v || 'standard'));
  }

  if (sel){
    apply(sel.value);
    sel.addEventListener('change', e => apply(e.target.value));
  }

  // Toggle bantuan tipe
  const toggle = document.getElementById('helpTypeToggle');
  const box    = document.getElementById('helpTypeBox');
  if (toggle && box){
    toggle.addEventListener('click', function(ev){
      ev.preventDefault();
      box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
    });
  }

  // Tom Select (jangan ubah urutan -> gunakan $order bawaan)
  if (window.TomSelect) {
    ['size_id','color_id','unit_id','brand_id','parent_id'].forEach(id => {
      const el = document.getElementById(id) || document.querySelector(`[name="${id}"]`);
      if (el) new TomSelect(el, {
        allowEmptyOption:true,
        create:false,
        sortField: [{field:'$order', direction:'asc'}], // pertahankan urutan dari server (sort_order)
      });
    });
  }
})();
</script>
@endpush
