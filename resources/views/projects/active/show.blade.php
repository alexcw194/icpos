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
          <div class="text-muted">BQ Grand Total</div>
          <div class="fw-bold">Rp {{ number_format((float) $quotation->grand_total, 2, ',', '.') }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Payment Terms - {{ $quotation->number }}</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-vcenter card-table">
        <thead>
          <tr>
            <th>Seq</th>
            <th>Code</th>
            <th>Label</th>
            <th class="text-end">Percent</th>
            <th>Trigger</th>
            <th>Invoice</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($termRows as $row)
            @php
              $term = $row->term;
              $invoice = $row->invoice;
              $trigger = $term->trigger_note ?: strtoupper(str_replace('_', ' ', (string) $term->due_trigger));
            @endphp
            <tr>
              <td>{{ $term->sequence }}</td>
              <td>{{ $term->code }}</td>
              <td>{{ $term->label ?: '-' }}</td>
              <td class="text-end">{{ number_format((float) $term->percent, 2, ',', '.') }}%</td>
              <td>{{ $trigger ?: '-' }}</td>
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
                @if($row->can_create_invoice)
                  @can('create', \App\Models\Invoice::class)
                    <form method="POST" action="{{ route('projects.active.payment-terms.create-invoice', ['project' => $project, 'term' => $term]) }}" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-primary">Create Invoice</button>
                    </form>
                  @else
                    <span class="text-muted">-</span>
                  @endcan
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted">No payment terms on latest won BQ.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
