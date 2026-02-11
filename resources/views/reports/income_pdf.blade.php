<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Income Report</title>
  <style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 11px; color:#111; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    h2 { font-size: 13px; margin: 18px 0 8px; }
    .muted { color: #666; font-size: 10px; }
    .summary { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .summary td { border: 1px solid #ddd; padding: 6px; }
    .grid { width: 100%; border-collapse: collapse; }
    .grid th, .grid td { border: 1px solid #ccc; padding: 5px; }
    .grid th { background: #f4f4f4; }
    .right { text-align: right; }
  </style>
</head>
<body>
@php
  use Carbon\Carbon;
  $money = fn($n) => 'Rp ' . number_format((float) $n, 2, ',', '.');
@endphp

<h1>Income Report (Cash + Accrual)</h1>
<div class="muted">
  Period: {{ $filters['start_date']->format('d M Y') }} - {{ $filters['end_date']->format('d M Y') }} |
  Basis: {{ strtoupper($filters['basis']) }} |
  Generated: {{ Carbon::now()->format('d M Y H:i') }}
</div>

<table class="summary">
  <tr>
    <td><strong>Cash Total</strong><br>{{ $money($summary['cash_total'] ?? 0) }}</td>
    <td><strong>Accrual Total</strong><br>{{ $money($summary['accrual_total'] ?? 0) }}</td>
    <td><strong>Gap</strong><br>{{ $money($summary['delta'] ?? 0) }}</td>
    <td><strong>Unpaid Balance</strong><br>{{ $money($summary['unpaid_balance'] ?? 0) }}</td>
  </tr>
</table>

<h2>Daily Cash vs Accrual</h2>
<table class="grid">
  <thead>
    <tr>
      <th>Date</th>
      <th class="right">Cash</th>
      <th class="right">Accrual</th>
      <th class="right">Delta</th>
    </tr>
  </thead>
  <tbody>
    @forelse($dailyRows as $row)
      <tr>
        <td>{{ Carbon::parse($row->day)->format('d M Y') }}</td>
        <td class="right">{{ $money($row->cash_amount) }}</td>
        <td class="right">{{ $money($row->accrual_amount) }}</td>
        <td class="right">{{ $money($row->delta) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="4" class="right">No data.</td>
      </tr>
    @endforelse
  </tbody>
</table>

<h2>Invoice Details</h2>
<table class="grid">
  <thead>
    <tr>
      <th>No</th>
      <th>Company</th>
      <th>Customer</th>
      <th>Invoice Date</th>
      <th>Paid Date</th>
      <th>Status</th>
      <th>Basis</th>
      <th class="right">Total</th>
      <th class="right">Paid Amount</th>
    </tr>
  </thead>
  <tbody>
    @forelse($details as $inv)
      @php
        $tags = [];
        if ((int) ($inv->in_cash ?? 0) === 1) {
          $tags[] = 'cash';
        }
        if ((int) ($inv->in_accrual ?? 0) === 1) {
          $tags[] = 'accrual';
        }
      @endphp
      <tr>
        <td>{{ $inv->number ?? $inv->id }}</td>
        <td>{{ $inv->company?->alias ?: ($inv->company?->name ?? '-') }}</td>
        <td>{{ $inv->customer?->name ?? '-' }}</td>
        <td>{{ optional($inv->date)->format('d M Y') ?? '-' }}</td>
        <td>{{ optional($inv->paid_at)->format('d M Y') ?? '-' }}</td>
        <td>{{ strtoupper((string) $inv->status) }}</td>
        <td>{{ $tags ? implode('+', $tags) : '-' }}</td>
        <td class="right">{{ $money($inv->total ?? 0) }}</td>
        <td class="right">{{ $money($inv->paid_amount ?? $inv->total ?? 0) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="9" class="right">No data.</td>
      </tr>
    @endforelse
  </tbody>
</table>
</body>
</html>
