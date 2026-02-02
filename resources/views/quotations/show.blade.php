@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @if(session('ok'))
    <div class="alert alert-success alert-dismissible mb-3" role="alert">
      <div class="d-flex">
        <div>✅ {{ session('ok') }}</div>
        <a class="ms-auto btn-close" data-bs-dismiss="alert" aria-label="Close"></a>
      </div>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible mb-3" role="alert">
      <div class="d-flex">
        <div>⚠️ {{ session('error') }}</div>
        <a class="ms-auto btn-close" data-bs-dismiss="alert" aria-label="Close"></a>
      </div>
    </div>
  @endif

  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Quotation {{ $quotation->number }}</div>
        <div class="text-muted">Tanggal: {{ $quotation->date?->format('d M Y') }}</div>
        <div class="text-muted">Quotation by {{ $quotation->salesUser?->name ?? '-' }}</div>
      </div>

      @php
        $authUser = auth()->user();
        $isAdmin = $authUser && method_exists($authUser, 'hasAnyRole') && $authUser->hasAnyRole(['Admin','SuperAdmin']);
        $isOwner = $authUser && (int)$quotation->sales_user_id === (int)$authUser->id;
        $canDelete = $quotation->status === 'draft' && ($isAdmin || $isOwner);
      @endphp

      <div class="ms-auto btn-list">
        @includeIf('quotations._actions', ['quotation' => $quotation])

        {{-- Force show Create/Repeat SO (debug friendly) --}}
        @if(Route::has('sales-orders.create-from-quotation'))
          @php
            $soCount = method_exists($quotation, 'salesOrders')
              ? $quotation->salesOrders()->count()
              : 0;
          @endphp
          <a href="{{ route('sales-orders.create-from-quotation', $quotation) }}" class="btn btn-primary">
            {{ $soCount ? 'Repeat SO' : 'Create SO' }}
          </a>
        @else
          <span class="badge bg-red-lt">route sales-orders.create-from-quotation missing</span>
        @endif

        @if($canDelete)
          <form action="{{ route('quotations.destroy', $quotation) }}" method="POST"
                onsubmit="return confirm('Hapus quotation ini?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Hapus</button>
          </form>
        @endif

        <a href="{{ route('quotations.index') }}" class="btn btn-secondary">Kembali</a>
      </div>
    </div>

    <div class="card-body">
      {{-- INFO COMPANY & CUSTOMER --}}
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="fw-bold mb-1">Customer</div>
              <div>{{ $quotation->customer?->name ?? '—' }}</div>
              @php
                $contact = $quotation->customer?->contacts()->first();
              @endphp
              @if($contact)
                <div class="text-muted mt-1">
                  PIC: {{ $contact->name }}
                  @if($contact->phone) • {{ $contact->phone }} @endif
                  @if($contact->email) • {{ $contact->email }} @endif
                </div>
              @endif
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between">
                <div>Status</div>
                @php
                  // Mapping status baru
                  $mapClass = [
                    'draft' => 'badge bg-blue',
                    'sent'  => 'badge bg-blue',
                    'won'   => 'badge bg-green',
                  ];
                  $mapText = [
                    'draft' => 'Sent',
                    'sent'  => 'Sent',
                    'won'   => 'Won',
                  ];
                  $badgeClass = $mapClass[$quotation->status] ?? 'badge bg-secondary';
                  $badgeText  = $mapText[$quotation->status] ?? ucfirst($quotation->status);
                @endphp
                <div><span class="{{ $badgeClass }}">{{ $badgeText }}</span></div>
              </div>

              <div class="d-flex justify-content-between mt-2">
                <div>Valid Until</div>
                <div>{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</div>
              </div>
              <div class="d-flex justify-content-between mt-2">
                <div>Currency</div>
                <div>{{ $quotation->currency }}</div>
              </div>
              {{-- SALES NAME --}}
              <div class="d-flex justify-content-between mt-2">
                <div>Sales</div>
                <div>{{ $quotation->salesUser?->name ?? '—' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ITEMS (pakai lines + diskon per-baris) --}}
      <div class="table-responsive mb-3">
        <table class="table">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Deskripsi</th>
              <th class="text-end" style="width:8%">Qty</th>
              <th style="width:8%">Unit</th>
              <th class="text-end" style="width:12%">Unit Price</th>
              <th style="width:16%">Diskon (tipe + nilai)</th>
              <th class="text-end" style="width:12%">Subtotal</th>
              <th class="text-end" style="width:12%">Disc Rp</th>
              <th class="text-end" style="width:12%">Line Total</th>
            </tr>
          </thead>
          <tbody>
            @php
              $isIdr = strtoupper($quotation->currency) === 'IDR';
              $fmt   = fn($n) => number_format((float)$n, 2, ',', '.');
              $money = fn($n) => $isIdr ? ('Rp '.$fmt($n)) : ($fmt($n).' '.strtoupper($quotation->currency));
              $pct   = fn($n) => rtrim(rtrim(number_format((float)$n, 2, ',', '.'), '0'), ',') . '%';
            @endphp

            @forelse($quotation->lines as $ln)
              <tr>
                <td class="fw-500">{{ $ln->name }}</td>
                <td style="white-space: pre-line">{{ $ln->description }}</td>
                <td class="text-end">{{ number_format((float)$ln->qty, 2, ',', '.') }}</td>
                <td>{{ $ln->unit }}</td>
                <td class="text-end">{{ $money($ln->unit_price) }}</td>
                <td>
                  @if(($ln->discount_type ?? 'amount') === 'percent')
                    <span class="badge bg-blue-lt me-1">%</span> {{ $pct($ln->discount_value ?? 0) }}
                  @else
                    <span class="badge bg-gray-lt me-1">IDR</span> {{ $money($ln->discount_value ?? 0) }}
                  @endif
                </td>
                <td class="text-end">{{ $money($ln->line_subtotal ?? ((float)$ln->qty * (float)$ln->unit_price)) }}</td>
                <td class="text-end">- {{ $money($ln->discount_amount ?? 0) }}</td>
                <td class="text-end fw-600">{{ $money($ln->line_total ?? 0) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center text-muted">Tidak ada item.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- TOTALS --}}
      @php
        $lines_subtotal        = (float)($quotation->lines_subtotal ?? 0);
        $total_discount_type   = $quotation->total_discount_type ?? 'amount';
        $total_discount_value  = (float)($quotation->total_discount_value ?? 0);
        $total_discount_amount = (float)($quotation->total_discount_amount ?? 0);
        $taxable_base          = (float)($quotation->taxable_base ?? max($lines_subtotal - $total_discount_amount, 0));
        $tax_percent           = (float)($quotation->tax_percent ?? 0);
        $tax_amount            = (float)($quotation->tax_amount ?? 0);
        $grand_total           = (float)($quotation->total ?? ($taxable_base + $tax_amount));
      @endphp

      <div class="row justify-content-end">
        <div class="col-md-6">
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between">
                <div>Subtotal (setelah diskon per-baris)</div>
                <div>{{ $money($lines_subtotal) }}</div>
              </div>

              <div class="d-flex justify-content-between">
                <div>
                  Diskon Total
                  @if($total_discount_type === 'percent')
                    ({{ $pct($total_discount_value) }})
                  @endif
                </div>
                <div>- {{ $money($total_discount_amount) }}</div>
              </div>

              <div class="d-flex justify-content-between">
                <div>Dasar Pajak</div>
                <div>{{ $money($taxable_base) }}</div>
              </div>

              @if($tax_amount > 0)
                <div class="d-flex justify-content-between">
                  <div>PPN ({{ $fmt($tax_percent) }}%)</div>
                  <div>{{ $money($tax_amount) }}</div>
                </div>
              @else
                <div class="d-flex justify-content-between text-muted">
                  <div>PPN</div>
                  <div>—</div>
                </div>
              @endif

              <hr>
              <div class="d-flex justify-content-between fw-bold">
                <div>Grand Total</div>
                <div>{{ $money($grand_total) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- RELATED SALES ORDERS (Repeat Order) --}}
      @includeIf('quotations.partials._so_list', [
        'quotation' => $quotation,
        // kamu sudah punya tombol Create/Repeat SO di header, jadi hide di partial
        'showRepeatButton' => false,
      ]) 

      {{-- NOTES & TERMS --}}
      <div class="row g-3 mt-2">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header"><div class="card-title m-0">Notes</div></div>
            <div class="card-body" style="white-space: pre-line">{{ $quotation->notes ?: '—' }}</div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header"><div class="card-title m-0">Terms</div></div>
            <div class="card-body" style="white-space: pre-line">{{ $quotation->terms ?: '—' }}</div>
          </div>
        </div>
      </div>
    </div> {{-- /card-body --}}
  </div>
</div>
@endsection
