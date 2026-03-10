@extends('layouts.tabler')

@section('content')
<div class="container-xl">
  <div class="page-header d-print-none">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">{{ $project->code }}</div>
        <h2 class="page-title">{{ $project->name }}</h2>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        @if($salesOrder)
          <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-outline-secondary">SO Project</a>
        @endif
        <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-secondary">Project Detail</a>
        <a href="{{ route('projects.active.index') }}" class="btn btn-outline-secondary">Back</a>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Customer</div>
          <div class="fw-semibold">{{ $project->customer->name ?? '-' }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Company</div>
          <div class="fw-semibold">{{ $project->company->alias ?? $project->company->name ?? '-' }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Latest Won BQ</div>
          <div class="fw-semibold">{{ $quotation->number }}</div>
          <div class="text-muted">{{ optional($quotation->won_at)->format('d M Y H:i') ?: optional($quotation->quotation_date)->format('d M Y') }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">SO Project</div>
          <div class="fw-semibold">{{ $salesOrder->so_number ?? '-' }}</div>
          <div class="text-muted text-capitalize">{{ $salesOrder->status ?? '-' }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Contract Value (Billing Base)</div>
          <div class="h2 mb-0">Rp {{ number_format((float) ($salesOrder->contract_value ?? 0), 2, ',', '.') }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Operational Scope Total</div>
          <div class="h2 mb-0">Rp {{ number_format((float) ($salesOrder->total ?? 0), 2, ',', '.') }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Billing Terms - {{ $salesOrder->so_number }}</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Seq</th>
            <th>Code</th>
            <th class="text-end">Percent</th>
            <th>Billing Draft</th>
            <th>Invoice</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($termRows as $row)
            @php
              $term = $row->term;
              $billing = $row->billing;
              $invoice = $row->invoice;
            @endphp
            <tr>
              <td>{{ $term->seq }}</td>
              <td>{{ $term->top_code }}</td>
              <td class="text-end">{{ number_format((float) $term->percent, 2, ',', '.') }}%</td>
              <td>
                @if($billing)
                  <a href="{{ route('billings.show', $billing) }}" class="fw-semibold">
                    {{ $billing->inv_number ?: ($billing->pi_number ?: ('DRAFT-'.$billing->id)) }}
                  </a>
                  <div class="text-muted text-uppercase">{{ $billing->mode ?: 'draft' }}</div>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td>
                @if($invoice)
                  <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold">{{ $invoice->number }}</a>
                  <div class="text-muted">{{ optional($invoice->date)->format('d M Y') }}</div>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td>
                <span class="badge {{ $row->status_class }}">{{ $row->status }}</span>
              </td>
              <td class="text-end">
                @if($row->can_create_billing_draft)
                  <form method="POST" action="{{ route('projects.active.billing-terms.create-draft', ['project' => $project, 'term' => $term]) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">Create Billing Draft</button>
                  </form>
                @elseif($billing)
                  <a href="{{ route('billings.show', $billing) }}" class="btn btn-sm btn-outline-primary">Open Billing</a>
                @elseif($invoice)
                  <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary">Open Invoice</a>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">No billing terms on SO Project.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Operational Variance (Planned vs PO vs GR vs Delivery)</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Line</th>
            <th class="text-end">Planned</th>
            <th class="text-end">PO</th>
            <th class="text-end">GR</th>
            <th class="text-end">Delivered</th>
            <th class="text-end">Variance PO</th>
            <th class="text-end">Variance Delivery</th>
          </tr>
        </thead>
        <tbody>
          @forelse($executionRows as $row)
            @php
              $poVarianceClass = $row['ordered_variance_state'] === 'balanced' ? 'text-muted' : ($row['ordered_variance_state'] === 'excess' ? 'text-success' : 'text-danger');
              $deliveryVarianceClass = $row['delivered_variance_state'] === 'balanced' ? 'text-muted' : ($row['delivered_variance_state'] === 'excess' ? 'text-success' : 'text-danger');
            @endphp
            <tr>
              <td>
                <div class="fw-semibold">{{ $row['line_name'] ?: '-' }}</div>
                @if(!empty($row['line_description']))
                  <div class="text-muted small">{{ $row['line_description'] }}</div>
                @endif
              </td>
              <td class="text-end">{{ number_format((float) $row['planned_qty'], 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) $row['po_ordered_qty'], 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) $row['gr_received_qty'], 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) $row['delivered_qty'], 2, ',', '.') }}</td>
              <td class="text-end {{ $poVarianceClass }}">{{ number_format((float) $row['ordered_variance_qty'], 2, ',', '.') }}</td>
              <td class="text-end {{ $deliveryVarianceClass }}">{{ number_format((float) $row['delivered_variance_qty'], 2, ',', '.') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">No operational lines.</td>
            </tr>
          @endforelse
        </tbody>
        @if(!empty($executionRows))
          <tfoot>
            <tr class="fw-semibold">
              <td>Total</td>
              <td class="text-end">{{ number_format((float) ($executionTotals['planned_qty'] ?? 0), 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) ($executionTotals['po_ordered_qty'] ?? 0), 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) ($executionTotals['gr_received_qty'] ?? 0), 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) ($executionTotals['delivered_qty'] ?? 0), 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) ($executionTotals['ordered_variance_qty'] ?? 0), 2, ',', '.') }}</td>
              <td class="text-end">{{ number_format((float) ($executionTotals['delivered_variance_qty'] ?? 0), 2, ',', '.') }}</td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endsection
