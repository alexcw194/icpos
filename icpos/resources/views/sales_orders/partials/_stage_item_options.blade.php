@php
  // $items harus dikirim dari controller (with('unit:id,code'))
  $ITEM_OPTIONS = ($items ?? collect())->map(function($it){
    return [
      'id'    => $it->id,
      'label' => $it->name,
      'unit'  => optional($it->unit)->code ?? 'pcs',
      'price' => (float)($it->price ?? 0),
    ];
  })->values();
@endphp

<script>
(function(){
  window.SO_ITEM_OPTIONS = @json($ITEM_OPTIONS);
})();
</script>
