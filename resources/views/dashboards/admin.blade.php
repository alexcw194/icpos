@extends('layouts.tabler')

@section('content')
@php
  use Carbon\Carbon;

  $money = fn($n) => 'Rp ' . number_format((float) $n, 2, ',', '.');
  $companyLabel = fn($c) => $c ? ($c->alias ?: $c->name) : '-';
  $today = Carbon::now()->startOfDay();
@endphp

<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Admin Command Center</div>
        <h2 class="page-title">Dashboard</h2>
      </div>
      @if($companies->count() > 1)
        <div class="col-auto ms-auto">
          <form method="GET">
            <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="">All Companies</option>
              @foreach($companies as $co)
                <option value="{{ $co->id }}" @selected((string)$companyId === (string)$co->id)>
                  {{ $co->alias ?: $co->name }}
                </option>
              @endforeach
            </select>
          </form>
        </div>
      @endif
    </div>
  </div>

  <div class="row row-deck row-cards mb-3">
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Sent (MTD)</div>
          <div class="h2 m-0">{{ number_format($qSentMtdCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Won (MTD)</div>
          <div class="h2 m-0">{{ number_format($qWonMtdCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Won Revenue</div>
          <div class="h2 m-0">{{ $money($qWonMtdAmount) }}</div>
        </div>
      </div>
    </div>
    @hasanyrole('Admin|SuperAdmin')
      <div class="col-6 col-md-2">
        <div class="card card-sm">
          <div class="card-body">
            <div class="subheader">SO Revenue (YTD)</div>
            <div class="h2 m-0">{{ $money($soRevenueYtd ?? 0) }}</div>
            <div class="text-muted small">Booked Sales Orders</div>
          </div>
        </div>
      </div>
    @endhasanyrole
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Sent Pipeline</div>
          <div class="h2 m-0">{{ $money($qSentPipelineMtdAmount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Sent &gt; 7d</div>
          <div class="h2 m-0">{{ number_format($qSentAging7dCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">SO Open</div>
          <div class="h2 m-0">{{ number_format($soOpenCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">SO Due 7d</div>
          <div class="h2 m-0">{{ number_format($soDue7Count) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">AR Outstanding</div>
          <div class="h2 m-0">{{ $money($arOutstandingAmount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Overdue Inv.</div>
          <div class="h2 m-0">{{ number_format($overdueInvoiceCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">TT Pending</div>
          <div class="h2 m-0">{{ number_format($ttPendingCount) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card card-sm">
        <div class="card-body">
          <div class="subheader">Negative Stock</div>
          <div class="h2 m-0 text-danger">{{ number_format($negativeStockCount) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Sent Quotations Aging &gt; 7 Days</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Quotation No</th>
                <th>Customer</th>
                <th>Company</th>
                <th>Sent At</th>
                <th class="text-end">Age (days)</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              @forelse($sentAgingQuotes as $q)
                <tr>
                  <td>
                    <a href="{{ route('quotations.show', $q) }}" class="text-decoration-none">
                      {{ $q->number }}
                    </a>
                  </td>
                  <td>{{ $q->customer->name ?? '-' }}</td>
                  <td>{{ $companyLabel($q->company) }}</td>
                  <td>{{ $q->sent_at ? $q->sent_at->format('d M Y') : '-' }}</td>
                  <td class="text-end">{{ $q->age_days ?? '-' }}</td>
                  <td class="text-end">{{ $money($q->total ?? 0) }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted">No data.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      @if($soHasDeadline)
        <div class="card mb-3">
          <div class="card-header">
            <h3 class="card-title">SO Overdue</h3>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table">
              <thead>
                <tr>
                  <th>SO No</th>
                  <th>Customer</th>
                  <th>Company</th>
                  <th>Deadline</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($soOverdue as $so)
                  <tr>
                    <td>
                      <a href="{{ route('sales-orders.show', $so) }}" class="text-decoration-none">
                        {{ $so->number ?? $so->so_number ?? $so->id }}
                      </a>
                    </td>
                    <td>{{ $so->customer->name ?? '-' }}</td>
                    <td>{{ $companyLabel($so->company) }}</td>
                    <td>{{ $so->deadline ? $so->deadline->format('d M Y') : '-' }}</td>
                    <td>
                      <span class="badge bg-orange-lt text-orange-9">
                        {{ ucfirst(str_replace('_', ' ', $so->status)) }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted">No data.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">SO Due Soon (Next 7 Days)</h3>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table">
              <thead>
                <tr>
                  <th>SO No</th>
                  <th>Customer</th>
                  <th>Company</th>
                  <th>Deadline</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($soDueSoon as $so)
                  <tr>
                    <td>
                      <a href="{{ route('sales-orders.show', $so) }}" class="text-decoration-none">
                        {{ $so->number ?? $so->so_number ?? $so->id }}
                      </a>
                    </td>
                    <td>{{ $so->customer->name ?? '-' }}</td>
                    <td>{{ $companyLabel($so->company) }}</td>
                    <td>{{ $so->deadline ? $so->deadline->format('d M Y') : '-' }}</td>
                    <td>
                      <span class="badge bg-blue-lt text-blue-9">
                        {{ ucfirst(str_replace('_', ' ', $so->status)) }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted">No data.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      @else
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">SO Open (Recent)</h3>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table">
              <thead>
                <tr>
                  <th>SO No</th>
                  <th>Customer</th>
                  <th>Company</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($soRecentOpen as $so)
                  <tr>
                    <td>
                      <a href="{{ route('sales-orders.show', $so) }}" class="text-decoration-none">
                        {{ $so->number ?? $so->so_number ?? $so->id }}
                      </a>
                    </td>
                    <td>{{ $so->customer->name ?? '-' }}</td>
                    <td>{{ $companyLabel($so->company) }}</td>
                    <td>
                      <span class="badge bg-blue-lt text-blue-9">
                        {{ ucfirst(str_replace('_', ' ', $so->status)) }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted">No data.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>

    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">Overdue Invoices (Posted, Unpaid)</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Invoice No</th>
                <th>Customer</th>
                <th>Company</th>
                <th>Due Date</th>
                <th class="text-end">Days</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              @forelse($overdueInvoices as $inv)
                @php
                  $due = $inv->due_date ? Carbon::parse($inv->due_date)->startOfDay() : null;
                  $days = $due ? $due->diffInDays($today, false) : null;
                @endphp
                <tr>
                  <td>
                    <a href="{{ route('invoices.show', $inv) }}" class="text-decoration-none">
                      {{ $inv->number ?? $inv->id }}
                    </a>
                  </td>
                  <td>{{ $inv->customer->name ?? '-' }}</td>
                  <td>{{ $companyLabel($inv->company) }}</td>
                  <td>{{ $inv->due_date ? Carbon::parse($inv->due_date)->format('d M Y') : '-' }}</td>
                  <td class="text-end">{{ $days === null ? '-' : $days }}</td>
                  <td class="text-end">{{ $money($inv->total ?? 0) }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted">No data.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title">TT Pending (Receipt Missing)</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Invoice No</th>
                <th>Customer</th>
                <th>Company</th>
                <th>Date</th>
                <th class="text-end">Total</th>
                <th>Receipt</th>
              </tr>
            </thead>
            <tbody>
              @forelse($ttPendingInvoices as $inv)
                <tr>
                  <td>
                    <a href="{{ route('invoices.show', $inv) }}" class="text-decoration-none">
                      {{ $inv->number ?? $inv->id }}
                    </a>
                  </td>
                  <td>{{ $inv->customer->name ?? '-' }}</td>
                  <td>{{ $companyLabel($inv->company) }}</td>
                  <td>{{ optional($inv->date)->format('d M Y') ?? '-' }}</td>
                  <td class="text-end">{{ $money($inv->total ?? 0) }}</td>
                  <td><span class="badge bg-orange-lt text-orange-9">Missing</span></td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted">No data.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Inventory Negative Stock</h3>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter card-table">
            <thead>
              <tr>
                <th>Warehouse</th>
                <th>SKU</th>
                <th>Item / Variant</th>
                <th class="text-end">Qty Balance</th>
              </tr>
            </thead>
            <tbody>
              @forelse($negativeStockRows as $row)
                @php
                  $sku = $row->variant->sku ?? $row->item->sku ?? '-';
                  $variantLabel = $row->variant ? ($row->variant->label ?? $row->variant->sku) : null;
                  $name = $row->item->name ?? '-';
                  if ($variantLabel) $name .= ' - '.$variantLabel;
                @endphp
                <tr>
                  <td>{{ $row->warehouse->name ?? '-' }}</td>
                  <td>{{ $sku }}</td>
                  <td>{{ $name }}</td>
                  <td class="text-end text-danger">{{ number_format((float) $row->qty_balance, 2, ',', '.') }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-muted">No data.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  @if($companyStats->isNotEmpty())
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Cross-Company Performance (MTD)</h3>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table">
          <thead>
            <tr>
              <th>Company</th>
              <th class="text-end">Won MTD</th>
              <th class="text-end">Sent Pipeline</th>
              <th class="text-end">AR Outstanding</th>
              <th class="text-end">Overdue</th>
              <th class="text-end">SO Open</th>
              <th class="text-end">Negative Stock</th>
            </tr>
          </thead>
          <tbody>
            @foreach($companyStats as $row)
              <tr>
                <td>{{ $companyLabel($row->company) }}</td>
                <td class="text-end">{{ $money($row->won_mtd_amount) }}</td>
                <td class="text-end">{{ $money($row->sent_pipeline_amount) }}</td>
                <td class="text-end">{{ $money($row->ar_outstanding_amount) }}</td>
                <td class="text-end">{{ number_format($row->overdue_count) }}</td>
                <td class="text-end">{{ number_format($row->so_open_count) }}</td>
                <td class="text-end">{{ number_format($row->negative_stock_count) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>
@endsection
