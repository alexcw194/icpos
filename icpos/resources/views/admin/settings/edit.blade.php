{{-- resources/views/admin/settings/edit.blade.php --}}
@extends('layouts.tabler')

@section('content')
@php
  $logoPath = $s['company.logo_path'] ?? null;
  $logoUrl  = $logoPath ? asset('storage/'.$logoPath) : null;

  $encSaved = $s['mail.encryption'] ?? '';
  $encView  = $encSaved === '' ? 'null' : $encSaved;

  $policy   = $s['mail.username_policy'] ?? 'default_email';
@endphp

<div class="container-xl">
  {{-- >>> PENTING: method=POST tanpa @method('PATCH') <<< --}}
  <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="card">
    @csrf

    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title">Global Settings</div>
      @if(session('ok'))
        <span class="badge bg-success">{{ session('ok') }}</span>
      @endif
    </div>

    <div class="card-body">
      {{-- ================= COMPANY ================= --}}
      <h3 class="card-title mb-2">Company</h3>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Company Name</label>
          <input type="text" name="company_name" class="form-control"
                 value="{{ old('company_name', $s['company.name'] ?? '') }}" required>
          @error('company_name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Company Email</label>
          <input type="email" name="company_email" class="form-control"
                 value="{{ old('company_email', $s['company.email'] ?? '') }}">
          @error('company_email')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">Company Phone</label>
          <input type="text" name="company_phone" class="form-control"
                 value="{{ old('company_phone', $s['company.phone'] ?? '') }}">
          @error('company_phone')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Company Logo</label>
          <input type="file" name="company_logo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          @error('company_logo')<div class="text-danger small">{{ $message }}</div>@enderror
          <div class="form-hint">Maks. 1MB. Format: JPG/PNG/WebP.</div>
          @if($logoUrl)
            <div class="mt-2 d-flex align-items-center gap-3">
              <img src="{{ $logoUrl }}" alt="Logo" style="height:56px;object-fit:contain;border:1px solid rgba(0,0,0,.06);padding:4px;border-radius:8px;">
              <span class="text-muted small">Logo saat ini</span>
            </div>
          @endif
        </div>

        <div class="col-12">
          <label class="form-label">Company Address</label>
          <textarea name="company_address" rows="3" class="form-control">{{ old('company_address', $s['company.address'] ?? '') }}</textarea>
          @error('company_address')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
      </div>

      <hr class="my-4">

      {{-- ================= MAIL (GLOBAL) ================= --}}
      <h3 class="card-title mb-2">Email / SMTP (Global)</h3>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">SMTP Host</label>
          <input type="text" name="mail_host" class="form-control"
                 value="{{ old('mail_host', $s['mail.host'] ?? '') }}">
          @error('mail_host')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
          <label class="form-label">SMTP Port</label>
          <input type="number" name="mail_port" class="form-control"
                 value="{{ old('mail_port', $s['mail.port'] ?? '587') }}">
          @error('mail_port')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
          <label class="form-label">Encryption</label>
          <select name="mail_encryption" class="form-select">
            <option value="tls"  {{ old('mail_encryption',$encView)==='tls'  ? 'selected' : '' }}>TLS</option>
            <option value="ssl"  {{ old('mail_encryption',$encView)==='ssl'  ? 'selected' : '' }}>SSL</option>
            <option value="null" {{ old('mail_encryption',$encView)==='null' ? 'selected' : '' }}>Tanpa enkripsi</option>
          </select>
          @error('mail_encryption')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">Default "From" Address</label>
          <input type="email" name="mail_from_address" class="form-control"
                 value="{{ old('mail_from_address', $s['mail.from.address'] ?? '') }}">
          @error('mail_from_address')<div class="text-danger small">{{ $message }}</div>@enderror
          <div class="form-hint">Saat user mengirim email, sistem akan memakai email user sebagai From.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Default "From" Name</label>
          <input type="text" name="mail_from_name" class="form-control"
                value="{{ old('mail_from_name', $s['mail.from.name'] ?? '') }}">
          @error('mail_from_name')<div class="text-danger small">{{ $message }}</div>@enderror
          <div class="form-hint">Kosongkan untuk memakai <b>nama user pengirim</b> saat mengirim email.</div>
        </div>
      </div>

      <div class="alert alert-info mt-3">
        <strong>Catatan:</strong> Username & Password SMTP tidak disetel di sini.
        Masing-masing user mengisi di halaman <b>Profil</b>.
      </div>

      <hr class="my-4">

      {{-- ================= USERNAME POLICY ================= --}}
      <h3 class="card-title mb-2">Username Policy</h3>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">SMTP Username Policy</label>
          <select name="mail_username_policy" class="form-select">
            <option value="force_email"   {{ old('mail_username_policy',$policy)==='force_email'   ? 'selected' : '' }}>
              Paksa pakai email user
            </option>
            <option value="default_email" {{ old('mail_username_policy',$policy)==='default_email' ? 'selected' : '' }}>
              Default email user, boleh override
            </option>
            <option value="custom_only"   {{ old('mail_username_policy',$policy)==='custom_only'   ? 'selected' : '' }}>
              Wajib username custom
            </option>
          </select>
          @error('mail_username_policy')<div class="text-danger small">{{ $message }}</div>@enderror

          <div class="form-hint mt-2">
            • <b>Paksa</b>: username = email user (UI profil tanpa field username).<br>
            • <b>Default</b>: username = email user, user boleh override di Profil.<br>
            • <b>Wajib custom</b>: user harus mengisi username.
          </div>
        </div>
      </div>
    </div>

    {{-- Footer standar ICPOS --}}
    @include('layouts.partials.form_footer', [
      'cancelUrl'    => route('dashboard'),
      'cancelLabel'  => 'Batal',
      'cancelInline' => true,   // tampil di kanan: Batal | Simpan
      'buttons'      => [
        ['type' => 'submit', 'label' => 'Simpan', 'class' => 'btn btn-primary'],
      ],
    ])
  </form>
</div>
@endsection
