{{-- resources/views/customers/show.blade.php --}}
@extends('layouts.tabler')

@section('content')
@php
  // profile | contacts | notes | quotations | sales_orders
  $tab = request('tab', 'profile');
  $q   = trim((string) request('q',''));
@endphp

<div class="container-xl">
  {{-- Header judul + tombol --}}
  <div class="d-flex align-items-center mb-3">
    <h2 class="page-title m-0">{{ $customer->name }}</h2>
    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-warning ms-auto">Edit</a>
  </div>

  <div class="row g-3">
    {{-- ============ RAIL KIRI (submenu cantik) ============ --}}
    <div class="col-12 col-lg-3">
      <div class="card subrail-card position-sticky" style="top: 76px">
        <div class="list-group list-group-flush">

          <a href="{{ route('customers.show', [$customer,'tab'=>'profile']) }}"
             class="list-group-item subrail-link d-flex align-items-center {{ $tab==='profile' ? 'active' : '' }}">
            <i class="ti ti-id-badge me-2"></i> <span>Profile</span>
          </a>

          <a href="{{ route('customers.show', [$customer,'tab'=>'contacts']) }}"
             class="list-group-item subrail-link d-flex align-items-center justify-content-between {{ $tab==='contacts' ? 'active' : '' }}">
            <span><i class="ti ti-users me-2"></i> Contacts</span>
            <span class="badge subrail-badge">{{ $customer->contacts_count ?? 0 }}</span>
          </a>

          <a href="{{ route('customers.show', [$customer,'tab'=>'notes']) }}"
             class="list-group-item subrail-link d-flex align-items-center {{ $tab==='notes' ? 'active' : '' }}">
            <i class="ti ti-notes me-2"></i> <span>Notes</span>
          </a>

          <a href="{{ route('customers.show', [$customer,'tab'=>'quotations']) }}"
             class="list-group-item subrail-link d-flex align-items-center justify-content-between {{ $tab==='quotations' ? 'active' : '' }}">
            <span><i class="ti ti-file-description me-2"></i> Quotations</span>
            <span class="badge subrail-badge">{{ $customer->quotations_count ?? 0 }}</span>
          </a>

          {{-- NEW: Sales Orders tab --}}
          <a href="{{ route('customers.show', [$customer,'tab'=>'sales_orders']) }}"
             class="list-group-item subrail-link d-flex align-items-center justify-content-between {{ $tab==='sales_orders' ? 'active' : '' }}">
            <span><i class="ti ti-file-invoice me-2"></i> Sales Orders</span>
            <span class="badge subrail-badge">{{ $customer->sales_orders_count ?? 0 }}</span>
          </a>

        </div>
      </div>
    </div>

    {{-- ============ KONTEN KANAN ============ --}}
    <div class="col-12 col-lg-9">

      {{-- ---------- PROFILE ---------- --}}
      @if($tab === 'profile')
        <div class="card">
          <div class="card-header"><div class="card-title">Company Profile</div></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted">Email</div>
                <div>{{ $customer->email ?: '-' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Phone</div>
                <div>{{ $customer->phone ?: '-' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Jenis</div>
                <div>{{ $customer->jenis->name ?? '-' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted">Website</div>
                <div>{{ $customer->website ?: '-' }}</div>
              </div>
              <div class="col-md-12">
                <div class="text-muted">Address</div>
                <div class="text-wrap">{{ $customer->address ?: '-' }}</div>
                <div class="small text-muted">
                  {{ $customer->city }} {{ $customer->province ? '• '.$customer->province : '' }}
                  {{ $customer->country ? '• '.$customer->country : '' }}
                </div>
              </div>

              <hr class="my-2">

              <div class="col-md-6">
                <div class="fw-bold mb-1">Billing</div>
                <div class="text-wrap">{{ $customer->billing_street ?: '-' }}</div>
                <div class="small text-muted">
                  {{ $customer->billing_city }} {{ $customer->billing_state ? '• '.$customer->billing_state : '' }}
                  {{ $customer->billing_zip ? '• '.$customer->billing_zip : '' }}
                  {{ $customer->billing_country ? '• '.$customer->billing_country : '' }}
                </div>
                @if($customer->billing_notes)
                  <div class="small mt-1"><span class="text-muted">Notes:</span> {{ $customer->billing_notes }}</div>
                @endif
              </div>

              <div class="col-md-6">
                <div class="fw-bold mb-1">Shipping</div>
                <div class="text-wrap">{{ $customer->shipping_street ?: '-' }}</div>
                <div class="small text-muted">
                  {{ $customer->shipping_city }} {{ $customer->shipping_state ? '• '.$customer->shipping_state : '' }}
                  {{ $customer->shipping_zip ? '• '.$customer->shipping_zip : '' }}
                  {{ $customer->shipping_country ? '• '.$customer->shipping_country : '' }}
                </div>
                @if($customer->shipping_notes)
                  <div class="small mt-1"><span class="text-muted">Notes:</span> {{ $customer->shipping_notes }}</div>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endif

      {{-- ---------- CONTACTS ---------- --}}
      @if($tab === 'contacts')
        <div class="card" id="contacts">
          <div class="card-header">
            <div class="card-title">Contacts</div>
            <a href="{{ route('customers.edit',$customer) }}#contacts" class="btn btn-primary btn-sm ms-auto">
              Manage Contacts
            </a>
          </div>
          <div class="table-responsive">
            <table class="table card-table">
              <thead>
                <tr>
                  <th>Name</th><th>Position</th><th>Phone</th><th>Email</th>
                </tr>
              </thead>
              <tbody id="contactsBody">
                @forelse($customer->contacts as $c)
                  <tr>
                    <td class="text-wrap contact-name">{{ $c->first_name }} {{ $c->last_name }}</td>
                    <td class="d-none d-md-table-cell contact-position">{{ $c->position ?: '-' }}</td>
                    <td class="d-none d-md-table-cell contact-phone">{{ $c->phone ?: '-' }}</td>
                    <td class="d-none d-md-table-cell contact-email">{{ $c->email ?: '-' }}</td>
                  </tr>
                @empty
                  <tr class="empty-row"><td colspan="4" class="text-center text-muted">No contacts.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      @endif

      {{-- ---------- NOTES ---------- --}}
      @if($tab === 'notes')
        <div class="card">
          <div class="card-header"><div class="card-title">Notes</div></div>
          <form method="post" action="{{ route('customers.notes',$customer) }}">
            @csrf @method('PATCH')
            <div class="card-body">
              <textarea name="notes" rows="8" class="form-control" placeholder="Catatan internal…">{{ old('notes',$customer->notes) }}</textarea>
              <small class="text-muted">Catatan ini akan muncul saat membuat dokumen untuk customer ini.</small>
            </div>
            <div class="card-footer d-flex">
              <a href="{{ route('customers.show',[$customer,'tab'=>'profile']) }}" class="btn btn-link">Batal</a>
              <button class="btn btn-primary ms-auto">Simpan Notes</button>
            </div>
          </form>
        </div>
      @endif

      {{-- ---------- QUOTATIONS ---------- --}}
      @if($tab === 'quotations')
        <div class="card">
          <div class="card-header">
            <div class="card-title">Quotations</div>
            <div class="ms-auto btn-list">
              <a href="{{ route('quotations.create', ['customer_id'=>$customer->id]) }}" class="btn btn-primary">
                + Create New Quotation
              </a>
            </div>
          </div>

          <div class="card-body border-bottom">
            <form class="row g-2" method="get" action="{{ route('customers.show',$customer) }}">
              <input type="hidden" name="tab" value="quotations">
              <div class="col-md-4">
                <input class="form-control" name="q" value="{{ $q }}" placeholder="Search number…">
              </div>
              <div class="col-md-2">
                <button class="btn btn-outline w-100">Filter</button>
              </div>
            </form>
          </div>

          <div class="table-responsive">
            <table class="table card-table table-vcenter">
              <thead class="d-none d-md-table-header-group">
                <tr>
                  <th>Quotation #</th>
                  <th>Date</th>
                  <th class="text-end">Amount</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($quotations as $qo)
                  @php
                    $coName = $qo->company->alias ?? $qo->company->name ?? null;
                  @endphp
                  <tr>
                    {{-- MOBILE: single cell only --}}
                    <td class="fw-bold d-md-none">
                      <div class="doc-mobile">
                        {{-- Row 1: ID + Status --}}
                        <div class="doc-m-row1">
                          <a class="doc-number" href="{{ route('quotations.index', ['preview' => $qo->id]) }}">
                            {{ $qo->number }}
                          </a>
                          <span class="badge {{ $qo->status_badge_class }}">{{ $qo->status_label }}</span>
                        </div>

                        {{-- Row 2: Entity (Company context for multi-company) --}}
                        <div class="doc-m-row2">
                          {{ $coName ?: '—' }}
                        </div>

                        {{-- Row 3: Date + Total --}}
                        <div class="doc-m-row3">
                          <span class="doc-m-date">{{ optional($qo->date)->format('d M Y') }}</span>
                          <span class="doc-m-total">{{ $qo->total_idr }}</span>
                        </div>

                        {{-- Actions: secondary, predictable, no row click --}}
                        <div class="doc-m-actions">
                          <a class="doc-act" href="{{ route('quotations.pdf',$qo) }}" target="_blank" rel="noopener">Lihat</a>
                          <span class="text-muted"> | </span>
                          <a class="doc-act" href="{{ route('quotations.edit',$qo) }}">Ubah</a>
                        </div>
                      </div>
                    </td>

                    {{-- DESKTOP: original cells preserved --}}
                    <td class="fw-bold d-none d-md-table-cell">
                      <a class="doc-number" href="{{ route('quotations.index', ['preview' => $qo->id]) }}">
                        {{ $qo->number }}
                      </a>
                      <div class="hover-actions">
                        <a class="doc-act" href="{{ route('quotations.pdf',$qo) }}" target="_blank" rel="noopener">View PDF</a>
                        <span class="text-muted"> | </span>
                        <a class="doc-act" href="{{ route('quotations.edit',$qo) }}">Edit</a>
                      </div>
                    </td>
                    <td class="d-none d-md-table-cell">{{ optional($qo->date)->format('d-m-Y') }}</td>
                    <td class="text-end d-none d-md-table-cell">{{ $qo->total_idr }}</td>
                    <td class="d-none d-md-table-cell"><span class="badge {{ $qo->status_badge_class }}">{{ $qo->status_label }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted">No quotations.</td></tr>
                @endforelse
              </tbody>

            </table>
          </div>

          <div class="card-footer">
            {{ $quotations->links() }}
          </div>
        </div>
      @endif

      {{-- ---------- SALES ORDERS (NEW) ---------- --}}
      @if($tab === 'sales_orders')
        <div class="card">
          <div class="card-header">
            <div class="card-title">Sales Orders</div>
            <div class="ms-auto btn-list">
              <a href="{{ route('sales-orders.index') }}" class="btn btn-outline">Open SO List</a>
            </div>
          </div>

          <div class="card-body border-bottom">
            <form class="row g-2" method="get" action="{{ route('customers.show',$customer) }}">
              <input type="hidden" name="tab" value="sales_orders">
              <div class="col-md-4">
                <input class="form-control" name="q" value="{{ $q }}" placeholder="Search SO number…">
              </div>
              <div class="col-md-2">
                <button class="btn btn-outline w-100">Filter</button>
              </div>
            </form>
          </div>

          <div class="table-responsive">
            <table class="table card-table table-vcenter">
              <thead class="d-none d-md-table-header-group">
                <tr>
                  <th>SO Number</th>
                  <th>Date</th>
                  <th class="text-end">Total</th>
                  <th>Status</th>
                  <th style="width:1%"></th>
                </tr>
              </thead>
              <tbody>
                @forelse($salesOrders as $so)
                  @php
                    $stMap = [
                      'open' => 'Open',
                      'partial_delivered' => 'Partial Delivered',
                      'delivered' => 'Delivered',
                      'invoiced' => 'Invoiced',
                      'closed' => 'Closed',
                    ];
                    $stLabel = $stMap[$so->status] ?? ucfirst(str_replace('_',' ', $so->status));
                    $coName = $so->company->alias ?? $so->company->name ?? null;
                  @endphp
                  <tr>
                    {{-- MOBILE --}}
                    <td class="fw-bold d-md-none">
                      <div class="doc-mobile">
                        <div class="doc-m-row1">
                          <a class="doc-number" href="{{ route('sales-orders.show',$so) }}">{{ $so->so_number }}</a>
                          <span class="badge bg-blue-lt text-blue-9">{{ $stLabel }}</span>
                        </div>

                        <div class="doc-m-row2">
                          {{ $coName ?: '—' }}
                          @if(!empty($so->quotation?->number))
                            <span class="text-muted">• {{ $so->quotation->number }}</span>
                          @endif
                        </div>

                        <div class="doc-m-row3">
                          <span class="doc-m-date">{{ \Illuminate\Support\Carbon::parse($so->order_date)->format('d M Y') }}</span>
                          <span class="doc-m-total">Rp {{ number_format((float)$so->total, 2, ',', '.') }}</span>
                        </div>

                        <div class="doc-m-actions">
                          <a class="doc-act" href="{{ route('sales-orders.show',$so) }}">Lihat</a>
                        </div>
                      </div>
                    </td>

                    {{-- DESKTOP --}}
                    <td class="fw-bold d-none d-md-table-cell">
                      <a class="doc-number" href="{{ route('sales-orders.show',$so) }}">{{ $so->so_number }}</a>
                      <div class="hover-actions">
                        <a class="doc-act" href="{{ route('sales-orders.show',$so) }}">View</a>
                      </div>
                    </td>
                    <td class="d-none d-md-table-cell">{{ \Illuminate\Support\Carbon::parse($so->order_date)->format('d-m-Y') }}</td>
                    <td class="text-end d-none d-md-table-cell">Rp {{ number_format((float)$so->total, 2, ',', '.') }}</td>
                    <td class="d-none d-md-table-cell"><span class="badge bg-blue-lt text-blue-9">{{ $stLabel }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted">No sales orders.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @if(($salesOrders ?? null) && method_exists($salesOrders,'links'))
            <div class="card-footer">
              {{ $salesOrders->links() }}
            </div>
          @endif
        </div>
      @endif

    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  /* --- Rail kiri cantik --- */
  .subrail-card{
    border:1px solid rgba(0,0,0,.06);
    box-shadow:0 2px 8px rgba(16,24,40,.04);
    overflow:hidden;
  }
  .subrail-link{
    border:0;
    padding:.65rem .75rem;
    border-left:3px solid transparent;
    transition:background-color .15s ease,border-color .15s ease;
  }
  .subrail-link:hover{ background-color:rgba(99,102,241,.06); }
  .subrail-link.active{
    background:linear-gradient(90deg, rgba(99,102,241,.10), transparent);
    border-left-color:#6366f1;
    color:#111827;
    font-weight:600;
  }
  .subrail-badge{
    background:#eef2ff; color:#4338ca; border-radius:12px;
  }

  /* Aksi yang hanya muncul saat hover baris (desktop) */
  .hover-actions{
    display:none;
    margin-top:.25rem;
    font-size:.85rem;
  }
  tr:hover .hover-actions{ display:block; }
  .doc-number { text-decoration:none; }
  .doc-act { text-decoration:none; }

  .badge-count{ padding:.2rem .45rem; line-height:1; border-radius:.35rem;
    background:rgba(132,204,22,.18); color:#3f6212; border:1px solid rgba(132,204,22,.35);
    box-shadow:0 0 0 1px rgba(255,255,255,.35) inset; font-weight:600; }

  /* Mobile: stacked rows for document list (rulebook) */
  @media (max-width: 767.98px){
    .doc-mobile{
      display:flex;
      flex-direction:column;
      gap:.35rem;
      padding:.1rem 0;
    }
    .doc-m-row1{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:.5rem;
    }
    .doc-m-row2{
      font-weight:600;
      line-height:1.2;
    }
    .doc-m-row3{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:1rem;
    }
    .doc-m-date{
      color: var(--tblr-muted);
      white-space: nowrap;
    }
    .doc-m-total{
      font-weight:700;
      white-space: nowrap;
      text-align:right;
      margin-left:auto;
    }
    .doc-m-actions{
      font-size:.85rem;
    }
  }

  @media (max-width: 991.98px){
    .subrail-card{ position:static !important; top:auto !important; }
  }
</style>
@endpush

@push('scripts')
{{-- (script kontak milikmu tetap, biarkan apa adanya) --}}
@endpush
