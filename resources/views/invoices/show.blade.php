@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  @php
    $prefScope = $prefScope ?? ((($invoice->tax_percent ?? 0) > 0) ? 'ppn' : 'non_ppn');
    $invStatus = strtolower((string) ($invoice->status ?? 'draft'));
    $banks = $banks ?? \App\Models\Bank::query()
      ->when(Schema::hasColumn('banks', 'company_id'), fn($q) => $q->where('company_id', $invoice->company_id))
      ->where('is_active', true)
      ->orderBy('code')->orderBy('name')
      ->get();

    $selectedBankId = old('paid_bank_id');
    if (!$selectedBankId && !empty($invoice->paid_bank)) {
      $needle = strtolower((string) $invoice->paid_bank);
      foreach ($banks as $bankOption) {
        $bankNeedle = strtolower((string) ($bankOption->code ?: $bankOption->name));
        if ($bankNeedle !== '' && str_contains($needle, $bankNeedle)) {
          $selectedBankId = $bankOption->id;
          break;
        }
      }
    }

    $paymentState = 'Draft';
    $paymentClass = 'bg-secondary-lt text-dark';
    if ($invStatus === 'paid' || $invoice->paid_at) {
      $paymentState = 'Paid';
      $paymentClass = 'bg-green-lt text-green';
    } elseif (in_array($invStatus, ['posted', 'invoiced', 'sent'], true)) {
      $paymentState = 'Unpaid';
      $paymentClass = 'bg-yellow-lt text-dark';
    }
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Invoice {{ $invoice->number ?? ('#'.$invoice->id) }}</h2>
    <div class="d-flex flex-wrap gap-2">
      @if($invStatus === 'draft')
        <a href="{{ route('invoices.pdf.proforma', $invoice) }}" target="_blank" class="btn btn-outline-secondary">
          PDF Proforma
        </a>
        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="btn btn-outline-primary">
          PDF Invoice
        </a>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-post-invoice">
          Post Invoice
        </button>
      @elseif($invStatus === 'posted')
        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="btn btn-outline-primary">
          PDF Invoice
        </a>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-update-receipt">
          Update Due Date / Upload TT
        </button>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-mark-paid">
          Mark as Paid
        </button>
      @elseif($invStatus === 'paid')
        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="btn btn-outline-primary">
          PDF Invoice
        </a>
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-mark-paid">
          Update Payment
        </button>
      @else
        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="btn btn-outline-primary">
          PDF Invoice
        </a>
      @endif
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-muted">Company</div>
          <div class="fw-semibold">{{ $invoice->company->alias ?? $invoice->company->name }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Customer</div>
          <div class="fw-semibold">{{ $invoice->customer->name ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Invoice Date</div>
          <div class="fw-semibold">{{ $invoice->date?->format('Y-m-d') ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Status</div>
          <div class="fw-semibold text-uppercase">{{ $invoice->status ?? 'draft' }}</div>
        </div>

        @if($invoice->salesOrder)
          <div class="col-md-3">
            <div class="text-muted">Sales Order</div>
            <div class="fw-semibold">
              <a href="{{ route('sales-orders.show', $invoice->salesOrder) }}">
                {{ $invoice->salesOrder->so_number ?? ('#'.$invoice->salesOrder->id) }}
              </a>
            </div>
          </div>
        @endif
        @if($invoice->billingTerm)
          <div class="col-md-3">
            <div class="text-muted">TOP Code</div>
            <div class="fw-semibold">{{ $invoice->billingTerm->top_code }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted">Percent</div>
            <div class="fw-semibold">{{ number_format((float) $invoice->billingTerm->percent, 2) }}%</div>
          </div>
        @endif

        <div class="col-md-3">
          <div class="text-muted">Due Date</div>
          <div class="fw-semibold">{{ optional($invoice->due_date)->format('Y-m-d') ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Total</div>
          <div class="fw-bold">{{ number_format((float) $invoice->total, 2, ',', '.') }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Payment Status</div>
          <div><span class="badge {{ $paymentClass }}">{{ $paymentState }}</span></div>
        </div>

        <div class="col-md-3">
          <div class="text-muted">Tanggal Bayar</div>
          <div class="fw-semibold">{{ $invoice->paid_at?->format('Y-m-d') ?? '-' }}</div>
        </div>
        <div class="col-md-3">
          <div class="text-muted">Amount Dibayar</div>
          <div class="fw-semibold">{{ $invoice->paid_amount ? number_format((float) $invoice->paid_amount, 2, ',', '.') : '-' }}</div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Bank / Ref Transfer</div>
          <div class="fw-semibold">
            {{ $invoice->paid_bank ?: '-' }}
            @if($invoice->paid_ref)
              <span class="text-muted">({{ $invoice->paid_ref }})</span>
            @endif
          </div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Catatan Pembayaran</div>
          <div class="fw-semibold">{{ $invoice->payment_notes ?: '-' }}</div>
        </div>
        <div class="col-md-6">
          <div class="text-muted">Tanda Terima</div>
          @if(!empty($invoice->receipt_path))
            <a href="{{ asset('storage/'.$invoice->receipt_path) }}" target="_blank" class="small">Lihat file</a>
          @else
            <span class="text-muted small">Belum diunggah</span>
          @endif
        </div>
      </div>
    </div>
  </div>

  @if($invoice->quotation)
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Lines (dari Quotation {{ $invoice->quotation->number }})</h3>
      </div>
      <div class="table-responsive">
        <table class="table card-table">
          <thead>
          <tr>
            <th>Deskripsi</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Harga</th>
            <th class="text-end">Total</th>
          </tr>
          </thead>
          <tbody>
          @foreach($invoice->quotation->items as $ln)
            <tr>
              <td>{{ $ln->name }}</td>
              <td class="text-end">{{ $ln->qty }} {{ $ln->unit }}</td>
              <td class="text-end">{{ number_format((float) $ln->unit_price, 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) $ln->line_total, 2, ',', '.') }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if($invoice->relationLoaded('lines'))
    <div class="card mt-3">
      <div class="card-header">Invoice Lines</div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead>
          <tr>
            <th>#</th>
            <th>Description</th>
            <th class="text-end">Qty</th>
            <th>Unit</th>
            <th class="text-end">Price</th>
            <th class="text-end">Disc</th>
            <th class="text-end">Subtotal</th>
            <th class="text-end">Line Total</th>
          </tr>
          </thead>
          <tbody>
          @foreach($invoice->lines as $i => $ln)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td>{{ $ln->description }}</td>
              <td class="text-end">{{ number_format((float) $ln->qty, 2) }}</td>
              <td>{{ strtoupper($ln->unit) }}</td>
              <td class="text-end">{{ number_format((float) $ln->unit_price, 2) }}</td>
              <td class="text-end">{{ number_format((float) $ln->discount_amount, 2) }}</td>
              <td class="text-end">{{ number_format((float) $ln->line_subtotal, 2) }}</td>
              <td class="text-end fw-bold">{{ number_format((float) $ln->line_total, 2) }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if($invStatus === 'draft')
    <div class="modal fade" id="modal-post-invoice" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('invoices.post', $invoice) }}" enctype="multipart/form-data">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Post Invoice</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Due Date (optional)</label>
              <input type="date" name="due_date" class="form-control"
                     value="{{ optional($invoice->due_date)->toDateString() ?? now()->addDays(30)->toDateString() }}">
            </div>
            <div class="mb-3">
              <label class="form-label">Upload Tanda Terima (optional)</label>
              <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
              <div class="form-text">PDF/JPG/PNG, max 4 MB.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Catatan (optional)</label>
              <textarea name="note" rows="3" class="form-control"></textarea>
            </div>
            <div class="alert alert-info mb-0">
              Setelah diposting, status menjadi <strong>Posted</strong>.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success">Post Invoice</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  @if($invStatus === 'posted')
    <div class="modal fade" id="modal-update-receipt" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('invoices.update-receipt', $invoice) }}" enctype="multipart/form-data">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Update Due Date / Tanda Terima</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">New Due Date (optional)</label>
              <input type="date" name="due_date" class="form-control" value="{{ optional($invoice->due_date)->toDateString() }}">
            </div>
            <div class="mb-3">
              <label class="form-label">Upload/Replace Tanda Terima (optional)</label>
              <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
              <div class="form-text">PDF/JPG/PNG, max 4 MB.</div>
            </div>
            <div class="alert alert-info mb-0">
              Perubahan ini hanya memperbarui due date dan/atau file tanda terima.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  @if(in_array($invStatus, ['posted', 'paid'], true))
    @php $hasBanks = $banks->isNotEmpty(); @endphp
    <div class="modal fade" id="modal-mark-paid" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('invoices.mark-paid', $invoice) }}">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">{{ $invStatus === 'paid' ? 'Update Payment' : 'Close Invoice (Paid)' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Tanggal Bayar</label>
                <input type="date" name="paid_at" class="form-control" value="{{ old('paid_at', optional($invoice->paid_at)->toDateString() ?? now()->toDateString()) }}" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" name="paid_amount" class="form-control"
                       value="{{ old('paid_amount', number_format((float) ($invoice->paid_amount ?? $invoice->total), 2, '.', '')) }}" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Bank</label>
                <select name="paid_bank_id" class="form-select" required @disabled(!$hasBanks)>
                  <option value="" disabled @selected(empty($selectedBankId))>- Pilih Bank -</option>
                  @foreach($banks as $b)
                    @php
                      $label = trim(($b->code ?: $b->name).' '.($b->account_no ? '- '.$b->account_no : ''));
                      $isSelected = (string) $selectedBankId === (string) $b->id
                        || (empty($selectedBankId) && ($b->tax_scope ?? null) === $prefScope);
                    @endphp
                    <option value="{{ $b->id }}" @selected($isSelected)>{{ $label }}</option>
                  @endforeach
                </select>
                @if(!$hasBanks)
                  <div class="form-text text-danger">Bank aktif untuk company ini belum tersedia.</div>
                @endif
              </div>

              <div class="col-md-6">
                <label class="form-label">Ref/No. Transfer (opsional)</label>
                <input type="text" name="paid_ref" class="form-control" placeholder="No. bukti / VA / ref" value="{{ old('paid_ref', $invoice->paid_ref) }}">
              </div>

              <div class="col-12">
                <label class="form-label">Catatan (opsional)</label>
                <textarea name="payment_notes" class="form-control" rows="2">{{ old('payment_notes', $invoice->payment_notes) }}</textarea>
              </div>
            </div>
            <div class="alert alert-success mt-3 mb-0">
              Simpan pembayaran untuk menandai invoice sebagai paid.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success" @disabled(!$hasBanks)>
              {{ $invStatus === 'paid' ? 'Update Paid' : 'Confirm Paid' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
</div>
@endsection
