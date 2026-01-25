@extends('layouts.tabler')

@section('content')
<div class="page-header d-print-none">
  <div class="container-xl d-flex align-items-center">
    <div>
      <div class="page-pretitle">BQ Line Template</div>
      <h2 class="page-title">{{ $template->name }}</h2>
      @if($template->description)
        <div class="text-muted">{{ $template->description }}</div>
      @endif
    </div>
    <div class="ms-auto btn-list">
      <a href="{{ route('bq-line-templates.index') }}" class="btn">Back</a>
      <a href="{{ route('bq-line-templates.lines.create', $template) }}" class="btn btn-primary">Add Line</a>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @if(session('ok'))       <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if(session('success'))  <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))    <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="card">
      <div class="table-responsive">
        <table class="table card-table table-vcenter">
          <thead>
            <tr>
              <th class="w-1">Order</th>
              <th>Type</th>
              <th>Label</th>
              <th>Defaults</th>
              <th>Editable</th>
              <th class="w-1"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($lines as $line)
              <tr>
                <td>{{ $line->sort_order }}</td>
                <td class="text-muted">{{ ucfirst($line->type) }}</td>
                <td>{{ $line->label }}</td>
                <td>
                  @if($line->type === 'charge')
                    Qty {{ number_format((float)($line->default_qty ?? 0), 2, ',', '.') }}
                    {{ $line->default_unit ?? 'LS' }}
                    @if($line->default_unit_price !== null)
                      &middot; Harga {{ number_format((float)$line->default_unit_price, 2, ',', '.') }}
                    @endif
                  @else
                    {{ number_format((float)($line->percent_value ?? 0), 4, ',', '.') }}%
                    <span class="text-muted">({{ $line->basis_type }})</span>
                  @endif
                </td>
                <td>
                  @if($line->type === 'charge')
                    {{ $line->editable_price ? 'Price' : '-' }}
                  @else
                    {{ $line->editable_percent ? 'Percent' : '-' }}
                  @endif
                  / {{ $line->can_remove ? 'Remove' : 'Locked' }}
                </td>
                <td class="text-nowrap">
                  <form method="post" action="{{ route('bq-line-templates.lines.move-up', [$template, $line]) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm" title="Move up">Up</button>
                  </form>
                  <form method="post" action="{{ route('bq-line-templates.lines.move-down', [$template, $line]) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-sm" title="Move down">Down</button>
                  </form>
                  <a href="{{ route('bq-line-templates.lines.edit', [$template, $line]) }}" class="btn btn-sm">Edit</a>
                  <form method="post" action="{{ route('bq-line-templates.lines.destroy', [$template, $line]) }}" class="d-inline" onsubmit="return confirm('Hapus line ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Hapus</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">Belum ada line.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
