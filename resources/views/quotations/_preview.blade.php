{{-- resources/views/quotations/_preview.blade.php --}}
<div class="card">
  {{-- HEADER --}}
  <div class="card-header d-flex align-items-center">
    <div>
      <div class="card-title m-0">{{ $quotation->number }}</div>
      <div class="text-muted small">
        {{ optional($quotation->date)->format('d M Y') }} •
        <span class="badge {{ $quotation->status_badge_class ?? '' }}">
          {{ $quotation->status_label ?? strtoupper($quotation->status) }}
        </span>
      </div>
    </div>

    <div class="ms-auto btn-list">
      <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-warning">Edit</a>

      <div class="btn-group">
        <button class="btn btn-outline dropdown-toggle" data-bs-toggle="dropdown">PDF</button>
        <div class="dropdown-menu dropdown-menu-end">
          <a class="dropdown-item" target="_blank" href="{{ route('quotations.pdf', $quotation) }}">
            Open in New Tab
          </a>
          <a class="dropdown-item" href="{{ route('quotations.pdf-download', $quotation) }}">
            Download PDF
          </a>
          <form action="{{ route('quotations.email', $quotation) }}" method="POST"
                onsubmit="return confirm('Kirim PDF ke {{ $quotation->customer->email ?? 'email customer' }} ?');">
            @csrf
            <button type="submit" class="dropdown-item">E-Mail PDF</button>
          </form>
        </div>
      </div>

      {{-- Aksi status: draft ↔ sent (tanpa "PO") --}}
      @if($quotation->status === 'draft')
        <form class="d-inline" method="post" action="{{ route('quotations.sent',$quotation) }}">
          @csrf
          <button class="btn btn-outline">Mark as Sent</button>
        </form>
      @elseif($quotation->status === 'sent')
        <form class="d-inline" method="post" action="{{ route('quotations.draft',$quotation) }}">
          @csrf
          <button class="btn btn-secondary">Back to Draft</button>
        </form>
      @endif

      {{-- Create / Repeat SO — SELALU tampil --}}
      @if(Route::has('sales-orders.create-from-quotation'))
        @php
          $soCount = method_exists($quotation, 'salesOrders')
            ? $quotation->salesOrders()->count()
            : 0;
        @endphp
        <a href="{{ route('sales-orders.create-from-quotation', $quotation) }}" class="btn btn-primary">
          {{ $soCount ? 'Repeat SO' : 'Create SO' }}
        </a>
      @endif
    </div>
  </div>

  {{-- BODY --}}
  <div class="card-body">
    {{-- Customer / Company --}}
    <div class="row mb-3">
      <div class="col-md-6">
        <div class="text-muted">Customer</div>
        <div class="fw-medium">{{ $quotation->customer->name ?? '-' }}</div>
        @if(!empty($quotation->customer->address))
          <div class="text-muted small">{{ $quotation->customer->address }}</div>
        @endif
      </div>
      <div class="col-md-6">
        <div class="text-muted">Company</div>
        <div class="fw-medium">{{ $quotation->company->alias ?? $quotation->company->name ?? '-' }}</div>
      </div>
    </div>

    {{-- Lines --}}
    <div class="table-responsive mb-3">
      <table class="table table-sm">
        <thead>
          <tr>
            <th style="width:38%">Item</th>
            <th class="text-end" style="width:10%">Qty</th>
            <th style="width:10%">Unit</th>
            <th class="text-end" style="width:14%">Unit Price</th>
            <th class="text-end" style="width:14%">Discount</th>
            <th class="text-end" style="width:14%">Line Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($quotation->lines as $ln)
            <tr>
              <td>
                <div class="fw-medium">{{ $ln->name }}</div>
                @if($ln->description)
                  <div class="text-muted small">{{ $ln->description }}</div>
                @endif
              </td>
              <td class="text-end">
                {{ rtrim(rtrim(number_format((float)$ln->qty, 2, '.', ''), '0'), '.') }}
              </td>
              <td>{{ $ln->unit }}</td>
              <td class="text-end">{{ number_format((float)$ln->unit_price, 2, ',', '.') }}</td>
              <td class="text-end">
                @if(($ln->discount_type ?? 'amount') === 'percent')
                  {{ rtrim(rtrim(number_format((float)$ln->discount_value, 2, '.', ''), '0'), '.') }}%
                @else
                  {{ number_format((float)($ln->discount_amount ?? 0), 2, ',', '.') }}
                @endif
              </td>
              <td class="text-end">{{ number_format((float)$ln->line_total, 2, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted">No lines.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Totals --}}
    <div class="d-flex justify-content-end">
      <div style="min-width:320px">
        <div class="d-flex justify-content-between">
          <span>Subtotal</span>
          <span>{{ number_format((float)$quotation->lines_subtotal, 2, ',', '.') }}</span>
        </div>

        @if(($quotation->total_discount_amount ?? 0) > 0)
          <div class="d-flex justify-content-between">
            <span>Discount</span>
            <span>-{{ number_format((float)$quotation->total_discount_amount, 2, ',', '.') }}</span>
          </div>
        @endif

        <div class="d-flex justify-content-between">
          <span>Taxable</span>
          <span>{{ number_format((float)$quotation->taxable_base, 2, ',', '.') }}</span>
        </div>

        <div class="d-flex justify-content-between">
          <span>Tax ({{ rtrim(rtrim(number_format((float)$quotation->tax_percent, 2, '.', ''), '0'), '.') }}%)</span>
          <span>{{ number_format((float)$quotation->tax_amount, 2, ',', '.') }}</span>
        </div>

        <div class="d-flex justify-content-between fw-bold border-top mt-2 pt-2">
          <span>Total</span>
          <span>
            {{ $quotation->total_idr ?? number_format((float)$quotation->total, 2, ',', '.') }}
          </span>
        </div>
      </div>
    </div>

    {{-- Related Sales Orders --}}
    @includeIf('quotations.partials._so_list', [
      'quotation' => $quotation,
      // di header preview kamu sudah ada tombol Repeat SO,
      // jadi sembunyikan tombol di partial
      'showRepeatButton' => false,
    ])

    {{-- Notes --}}
    @if(!empty($quotation->notes))
      <div class="mt-3">
        <div class="fw-bold">Notes</div>
        <div class="text-muted" style="white-space: pre-line">{{ $quotation->notes }}</div>
      </div>
    @endif
  </div>
</div>
