{{-- Stage row: ketik & pilih item + entry sementara --}}
<div id="stageWrap" class="card mb-3">
  <div class="card-body py-2">
    <div class="row g-2 align-items-center">
      <div class="col-xxl-4 col-lg-5">
        <input id="stage_name" type="text" class="form-control" placeholder="Ketik nama/SKU lalu pilih…">
        <input id="stage_item_id" type="hidden">
        <input id="stage_item_variant_id" type="hidden">
      </div>
      <div class="col-xxl-3 col-lg-4">
        <textarea id="stage_desc" class="form-control" rows="1" placeholder="Deskripsi (opsional)"></textarea>
      </div>
      <div class="col-auto" style="width:8ch">
        <input id="stage_qty" type="text" class="form-control text-end" inputmode="decimal" value="1">
      </div>
      <div class="col-auto" style="width:7ch">
        <input id="stage_unit" type="text" class="form-control" value="pcs" readonly>
      </div>
      <div class="col-xxl-2 col-lg-2">
        <input id="stage_price" type="text" class="form-control text-end" inputmode="decimal" placeholder="0">
      </div>
      <div class="col-auto">
        <button type="button" id="stage_add_btn" class="btn btn-primary">Tambah</button>
        <button type="button" id="stage_clear_btn" class="btn btn-link">Kosongkan</button>
      </div>
    </div>
  </div>
</div>
