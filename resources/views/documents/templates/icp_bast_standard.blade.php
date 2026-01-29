{{-- resources/views/documents/templates/icp_bast_standard.blade.php --}}
@php
  $payload = $document->payload_json ?? [];
  $workPoints = $payload['work_points'] ?? [];
  $customerSigners = $payload['customer_signers'] ?? [];

  $dateBa = $payload['tanggal_ba'] ?? $document->created_at?->toDateString();
  $dateBaText = $dateBa ? \Illuminate\Support\Carbon::parse($dateBa)->format('d M Y') : '';
  $dateStart = $payload['tanggal_mulai'] ?? null;
  $dateStartText = $dateStart ? \Illuminate\Support\Carbon::parse($dateStart)->format('d M Y') : '';
  $dateProgress = $payload['tanggal_progress'] ?? null;
  $dateProgressText = $dateProgress ? \Illuminate\Support\Carbon::parse($dateProgress)->format('d M Y') : '';

  $customerCount = count($customerSigners);

  $salesUser = $document->salesSigner;
  $icpSignerName = $document->sales_signer_user_id
      ? ($salesUser?->name ?? ($document->creator?->name ?? ''))
      : 'Christian Widargo';
  $icpSignerTitle = $document->sales_signer_user_id
      ? ($document->sales_signature_position ?? '')
      : 'Direktur Utama';
  $autoSignature = $payload['icp_auto_signature'] ?? true;
  $maintenanceNotice = $payload['maintenance_notice'] ?? false;

  $signatures = $document->signatures ?? [];
  $salesSig = $signatures['sales'] ?? null;
  $directorSig = $signatures['director'] ?? null;
  $signaturePath = null;
  if ($document->approved_at && $autoSignature) {
      $signaturePath = $document->sales_signer_user_id
          ? ($salesSig['image_path'] ?? null)
          : ($directorSig['image_path'] ?? null);
  }
  $makeSrc = function ($path) {
      if (!$path) return null;
      return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
  };
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $document->number ?: 'BAST' }}</title>
  <style>
    @page { margin: 0; }
    body {
      margin: 0;
      font-family: DejaVu Sans, Arial, sans-serif;
      color: #1f2937;
      font-size: 12px;
      line-height: 1.5;
    }
    .page {
      position: relative;
      padding: 110px 60px 70px 70px;
      min-height: 100vh;
    }
    .letterhead {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: auto;
      z-index: 0;
    }
    .content {
      position: relative;
      z-index: 1;
    }
    .title {
      text-align: center;
      font-weight: 700;
      font-size: 14px;
      margin-bottom: 12px;
    }
    .meta {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
    }
    .meta td {
      padding: 2px 0;
      vertical-align: top;
    }
    .meta td.label {
      width: 120px;
    }
    .section-title {
      font-weight: 700;
      margin: 12px 0 6px;
      text-transform: uppercase;
      font-size: 11.5px;
    }
    .info-table {
      width: 100%;
      border-collapse: collapse;
    }
    .info-table td {
      padding: 1px 0;
      vertical-align: top;
    }
    .info-table td.label {
      width: 150px;
    }
    .work-list {
      margin: 0;
      padding-left: 18px;
    }
    .work-list li {
      margin: 0 0 4px;
    }
    .closing {
      margin-top: 14px;
    }
    .sign-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 18px;
    }
    .sign-table th {
      text-align: center;
      font-weight: 700;
      padding-bottom: 6px;
    }
    .sign-space {
      height: 55px;
      position: relative;
    }
    .sign-space-icp {
      height: 55px;
    }
    .sign-space-customer {
      height: 35px;
    }
    .sign-name {
      font-weight: 700;
      text-align: center;
    }
    .sign-title {
      text-align: center;
      color: #111827;
      font-weight: 400;
    }
    .sign-name {
      font-weight: 700;
      text-align: center;
    }
    .sign-name-icp {
      font-weight: 700;
      text-align: left;
    }
    .sign-title-icp {
      text-align: left;
      color: #111827;
      font-weight: 400;
    }
    .sign-date {
      margin-top: 14px;
      margin-bottom: 6px;
    }
    .sign-stamp {
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      max-height: 70px;
      z-index: 1;
    }
    .sign-signature {
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      max-height: 70px;
      z-index: 2;
    }
    .signers-grid {
      display: table;
      width: 100%;
      margin-top: 14px;
    }
    .signer-col {
      display: table-cell;
      width: 50%;
      vertical-align: top;
      padding-right: 18px;
    }
    .signer-col:last-child {
      padding-right: 0;
      padding-left: 0;
    }
    .signers-grid-4 {
      display: table;
      width: 100%;
      margin-top: 14px;
    }
    .signer-col-4 {
      display: table-cell;
      width: 25%;
      vertical-align: top;
      padding-right: 12px;
    }
    .signer-col-4:last-child {
      padding-right: 0;
    }
    .signer-list {
      width: 100%;
      border-collapse: collapse;
    }
    .signer-list td {
      padding: 2px 0;
    }
    .text-muted {
      color: #6b7280;
      font-size: 11px;
    }
    .signer-title {
      font-weight: 700;
      margin-bottom: 6px;
      text-align: left;
    }
    .customer-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 12px;
      justify-content: center;
    }
    .customer-cell {
      width: 33.33%;
      min-height: 70px;
      text-align: center;
    }
    .customer-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    .customer-table td {
      width: 33.33%;
      vertical-align: top;
      text-align: left;
      padding: 4px 8px 6px 0;
    }
    .customer-table-4 td {
      width: 25%;
    }
    .customer-table .sign-name,
    .customer-table .sign-title {
      text-align: left;
    }
    .customer-table .sign-space {
      margin-left: 0;
    }
  </style>
</head>
<body>
  @if($letterheadPath)
    <img src="{{ $letterheadPath }}" class="letterhead" alt="Letterhead">
  @endif

  <div class="page">
    <div class="content">
      <div class="title">BERITA ACARA SERAH TERIMA PEKERJAAN</div>

      <table class="meta">
        <tr>
          <td class="label">Nomor</td>
          <td>: {{ $document->number ?? '' }}</td>
          <td class="label">Tanggal</td>
          <td>: {{ $dateBaText }}</td>
        </tr>
        <tr>
          <td class="label">Tempat</td>
          <td>: {{ $payload['kota'] ?? 'Surabaya' }}</td>
          <td></td>
          <td></td>
        </tr>
      </table>

      <div class="section-title">Identitas Pekerjaan</div>
      <table class="info-table">
        <tr>
          <td class="label">Nama Customer</td>
          <td>: {{ $payload['nama_customer'] ?? data_get($document->customer_snapshot, 'name') }}</td>
        </tr>
        <tr>
          <td class="label">Lokasi Pekerjaan</td>
          <td>: {{ $payload['lokasi_pekerjaan'] ?? '-' }}</td>
        </tr>
        <tr>
          <td class="label">Nama Pekerjaan</td>
          <td>: {{ $payload['nama_pekerjaan'] ?? '-' }}</td>
        </tr>
        <tr>
          <td class="label">Referensi Kontrak</td>
          <td>: {{ $payload['jenis_kontrak'] ?? '-' }} {{ $payload['nomor_kontrak'] ?? '' }}</td>
        </tr>
        <tr>
          <td class="label">Tanggal Mulai</td>
          <td>: {{ $dateStartText }}</td>
        </tr>
        <tr>
          <td class="label">Status Pekerjaan</td>
          <td>: {{ $payload['status_pekerjaan'] ?? '-' }}</td>
        </tr>
        <tr>
          <td class="label">Tanggal Progress</td>
          <td>: {{ $dateProgressText }}</td>
        </tr>
      </table>

      <div class="section-title">Ruang Lingkup & Catatan</div>
      <ul class="work-list">
        @foreach($workPoints as $point)
          <li>{{ $point }}</li>
        @endforeach
      </ul>

      <div class="closing">
        Dengan ditandatanganinya Berita Acara ini, Pihak Customer menyatakan telah menerima hasil pekerjaan tersebut dari
        Pihak ICP dalam kondisi baik, sesuai dengan ruang lingkup pekerjaan, dan dapat digunakan sebagaimana mestinya.
      </div>
      <div class="closing">
        Dengan demikian, tanggung jawab pelaksanaan pekerjaan dinyatakan telah diserahterimakan sesuai dengan ketentuan
        yang berlaku.
      </div>
      @if($maintenanceNotice)
        <div class="closing">
          Masa pemeliharaan pekerjaan dimulai sejak tanggal ditandatanganinya Berita Acara Serah Terima ini, sesuai dengan
          ketentuan yang tercantum dalam perjanjian/kontrak yang berlaku.
        </div>
      @endif

      @if($customerCount <= 1)
        <div class="sign-date">Tanggal : ____________________</div>
        <table class="sign-table">
          <tr>
            <th>PIHAK ICP</th>
            <th>PIHAK CUSTOMER</th>
          </tr>
          <tr>
            <td class="sign-space sign-space-icp">
              @if($document->approved_at && $autoSignature)
                @if($stampPath)
                  <img src="{{ $stampPath }}" class="sign-stamp" alt="ICP Stamp">
                @endif
                @if($signaturePath)
                  <img src="{{ $makeSrc($signaturePath) }}" class="sign-signature" alt="ICP Signature">
                @endif
              @endif
            </td>
            <td class="sign-space sign-space-customer"></td>
          </tr>
          <tr>
            <td class="sign-name">{{ $icpSignerName }}</td>
            <td class="sign-name">{{ $customerSigners[0]['name'] ?? '' }}</td>
          </tr>
          <tr>
            <td class="sign-title">{{ $icpSignerTitle }}</td>
            <td class="sign-title">{{ $customerSigners[0]['title'] ?? '' }}</td>
          </tr>
        </table>
      @else
        <div class="sign-date">Tanggal : ____________________</div>
        @if($customerCount > 2)
          <div class="signers-grid-4">
            <div class="signer-col-4">
              <div class="signer-title">PIHAK ICP</div>
              <table class="signer-list">
                <tr>
                  <td>
                    <div class="sign-space sign-space-icp">
                      @if($document->approved_at && $autoSignature)
                        @if($stampPath)
                          <img src="{{ $stampPath }}" class="sign-stamp" alt="ICP Stamp">
                        @endif
                        @if($signaturePath)
                          <img src="{{ $makeSrc($signaturePath) }}" class="sign-signature" alt="ICP Signature">
                        @endif
                      @endif
                    </div>
                  </td>
                </tr>
              </table>
            </div>
            <div class="signer-col-4">
              <div class="signer-title">PIHAK CUSTOMER</div>
              <div class="sign-space sign-space-customer"></div>
            </div>
            <div class="signer-col-4">
              <div class="signer-title">&nbsp;</div>
              <div class="sign-space sign-space-customer"></div>
            </div>
            <div class="signer-col-4">
              <div class="signer-title">&nbsp;</div>
              <div class="sign-space sign-space-customer"></div>
            </div>
          </div>
          <div class="signers-grid-4">
            <div class="signer-col-4">
              <div class="signer-title">&nbsp;</div>
              <div class="sign-name-icp">{{ $icpSignerName }}</div>
              <div class="sign-title-icp">{{ $icpSignerTitle }}</div>
            </div>
            <div class="signer-col-4">
              <div class="signer-title">&nbsp;</div>
              @if(isset($customerSigners[0]))
                <div class="sign-name">{{ $customerSigners[0]['name'] ?? '' }}</div>
                <div class="sign-title">{{ $customerSigners[0]['title'] ?? '' }}</div>
              @endif
            </div>
            <div class="signer-col-4">
              <div class="signer-title">&nbsp;</div>
              @if(isset($customerSigners[1]))
                <div class="sign-name">{{ $customerSigners[1]['name'] ?? '' }}</div>
                <div class="sign-title">{{ $customerSigners[1]['title'] ?? '' }}</div>
              @endif
            </div>
            <div class="signer-col-4">
              <div class="signer-title">&nbsp;</div>
              @if(isset($customerSigners[2]))
                <div class="sign-name">{{ $customerSigners[2]['name'] ?? '' }}</div>
                <div class="sign-title">{{ $customerSigners[2]['title'] ?? '' }}</div>
              @endif
            </div>
          </div>
          @if($customerCount > 3)
            <table class="customer-table customer-table-4" style="margin-top:8px;">
              @foreach(array_chunk(array_slice($customerSigners, 3), 4) as $row)
                <tr>
                  @foreach($row as $signer)
                    <td>
                      <div class="sign-space sign-space-customer"></div>
                  <div class="sign-name">{{ $signer['name'] ?? '' }}</div>
                  <div class="sign-title">{{ $signer['title'] ?? '' }}</div>
                    </td>
                  @endforeach
                  @for($i = count($row); $i < 4; $i++)
                    <td></td>
                  @endfor
                </tr>
              @endforeach
            </table>
          @endif
        @else
          <div class="signers-grid">
            <div class="signer-col">
              <div class="signer-title">PIHAK ICP</div>
              <table class="signer-list">
                <tr>
                  <td>
                    <div class="sign-space sign-space-icp">
                      @if($document->approved_at && $autoSignature)
                        @if($stampPath)
                          <img src="{{ $stampPath }}" class="sign-stamp" alt="ICP Stamp">
                        @endif
                        @if($signaturePath)
                          <img src="{{ $makeSrc($signaturePath) }}" class="sign-signature" alt="ICP Signature">
                        @endif
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <td class="sign-name-icp">{{ $icpSignerName }}</td>
                </tr>
                <tr>
                  <td class="sign-title-icp">{{ $icpSignerTitle }}</td>
                </tr>
              </table>
            </div>
            <div class="signer-col">
              <div class="signer-title">PIHAK CUSTOMER</div>
              <div class="customer-grid">
                @foreach($customerSigners as $signer)
                  <div class="customer-cell">
                    <div class="sign-space sign-space-customer"></div>
                    <div class="sign-name">{{ $signer['name'] ?? '' }}</div>
                    <div class="sign-title">{{ $signer['title'] ?? '' }}</div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        @endif
      @endif
    </div>
  </div>
</body>
</html>
