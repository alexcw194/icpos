@php $soList = $quotation->salesOrders()->latest()->get(); @endphp

@if($soList->count())
  <div class="card mt-3">
    <div class="card-header"><div class="card-title">Related Sales Orders</div></div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>SO Number</th>
            <th>Date</th>
            <th class="text-end">Total</th>
            <th>Status</th>
            <th style="width:1%"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($soList as $so)
            <tr>
              <td>{{ $so->so_number }}</td>
              <td>{{ $so->order_date }}</td>
              <td class="text-end">{{ number_format($so->total,2) }}</td>
              <td>{{ ucfirst(str_replace('_',' ', $so->status)) }}</td>
              <td class="text-end">
                <a href="{{ route('sales-orders.show',$so) }}" class="btn btn-sm btn-link">View</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
