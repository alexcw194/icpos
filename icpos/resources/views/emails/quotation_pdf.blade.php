<!doctype html>
<html>
  <body style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#222; line-height:1.45;">
    <p>Yth. {{ $quotation->customer->name }},</p>

    @php
      $coName = $brand['name'] ?? ($quotation->company->name ?? 'Perusahaan kami');
      $qDate  = optional($quotation->date)->format('d M Y') ?? '-';
      $eDate  = optional($quotation->valid_until)->format('d M Y') ?? '-';
      $sig    = trim((string) ($signature ?? ''));  // dari Mailable
    @endphp

    <p>Terlampir <strong>Quotation {{ $quotation->number }}</strong> dari {{ $coName }}.</p>

    <p>
      Quotation Date: {{ $qDate }}<br>
      Expiry Date: {{ $eDate }}
    </p>

    {{-- SIGNATURE: jika user punya signature pakai itu, kalau tidak fallback otomatis --}}
    @if($sig !== '')
      {{-- Pakai nl2br agar newline di textarea jadi <br> dan tampil di semua mail client --}}
      <p style="margin-top:18px; line-height:1.45; mso-line-height-rule:exactly;">
        {!! nl2br(e($sig)) !!}
      </p>
    @else
      <p style="margin-top:18px">Hormat kami,</p>
      <p style="margin:4px 0 0">
        <strong>{{ $sender->name ?? '' }}</strong><br>
        {{ $coName }}<br>
        @if(!empty($sender?->phone)) Telp: {{ $sender->phone }}<br>@endif
        @if(!empty($sender?->whatsapp)) WA: {{ $sender->whatsapp }}<br>@endif
        @if(!empty($sender?->email)) Email: {{ $sender->email }}@endif
      </p>
    @endif
  </body>
</html>
