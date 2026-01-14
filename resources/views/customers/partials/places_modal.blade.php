{{-- resources/views/customers/partials/places_modal.blade.php --}}
<div class="modal fade" id="placesModal" tabindex="-1" aria-labelledby="placesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="placesModalLabel">Cari di Google Places</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="placesQuery">Pencarian</label>
        <input type="text" id="placesQuery" class="form-control" placeholder="Ketik min 3 huruf..." autocomplete="off">
        <div id="placesHint" class="form-hint mt-1">Min 3 karakter.</div>
        <div id="placesResults" class="list-group mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(() => {
  if (window.__placesBound) return;
  window.__placesBound = true;

  function bindPlaces() {
    const btn = document.getElementById('btnPlaces');
    const modalEl = document.getElementById('placesModal');

    if (!btn || !modalEl || !window.bootstrap || !bootstrap.Modal) {
      setTimeout(bindPlaces, 100);
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const queryInput = document.getElementById('placesQuery');
    const hint = document.getElementById('placesHint');
    const results = document.getElementById('placesResults');
    const PLACES_URL = @json(route('places.search'));

    let debounceTimer = null;
    let lastQuery = '';
    let activeController = null;

    const setHint = (text, className) => {
      if (!hint) return;
      hint.textContent = text;
      hint.className = `form-hint mt-1 ${className || 'text-muted'}`;
    };

    const clearResults = () => {
      if (!results) return;
      results.innerHTML = '';
    };

    const resetModal = () => {
      if (queryInput) queryInput.value = '';
      clearResults();
      setHint('Min 3 karakter.');
    };

    const fillField = (selector, value) => {
      const el = document.querySelector(selector);
      const next = (value || '').toString().trim();
      if (!el || !next) return;
      el.value = next;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const normalizeItem = (item) => {
      const v = (item && item.value) ? item.value : (item || {});
      return {
        name: v.name || v.title || '',
        website: v.website || '',
        address: v.address || v.formatted_address || '',
        city: v.city || '',
        province: v.province || v.state || '',
        country: v.country || '',
        phone: v.phone || v.formatted_phone_number || '',
      };
    };

    const renderItems = (items) => {
      clearResults();
      if (!Array.isArray(items) || !items.length) {
        setHint('Tidak ada hasil.');
        return;
      }
      setHint('Klik hasil untuk memilih.');

      items.forEach((item) => {
        const data = normalizeItem(item);

        const btnEl = document.createElement('button');
        btnEl.type = 'button';
        btnEl.className = 'list-group-item list-group-item-action';

        const title = document.createElement('div');
        title.className = 'fw-semibold';
        title.textContent = data.name || '(tanpa nama)';

        const meta = document.createElement('div');
        meta.className = 'text-muted small';
        meta.textContent = data.address || '';

        btnEl.appendChild(title);
        if (meta.textContent) btnEl.appendChild(meta);

        btnEl.addEventListener('click', () => {
          fillField('input[name="name"]', data.name);
          fillField('input[name="website"]', data.website);
          fillField('textarea[name="address"], input[name="address"]', data.address);
          fillField('input[name="city"]', data.city);
          fillField('input[name="province"]', data.province);
          fillField('input[name="country"]', data.country);
          fillField('input[name="phone"]', data.phone);
          modal.hide();
        });

        results.appendChild(btnEl);
      });
    };

    const runSearch = (query) => {
      const q = (query || '').trim();
      lastQuery = q;

      if (q.length < 3) {
        clearResults();
        setHint('Min 3 karakter.');
        return;
      }

      setHint('Loading...');
      if (activeController) activeController.abort();
      activeController = new AbortController();

      fetch(`${PLACES_URL}?q=${encodeURIComponent(q)}`, {
        headers: { 'Accept': 'application/json' },
        signal: activeController.signal
      })
        .then((res) => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return res.json();
        })
        .then((data) => {
          if (q !== lastQuery) return;
          const items = Array.isArray(data?.items) ? data.items : (Array.isArray(data) ? data : []);
          renderItems(items);
        })
        .catch((err) => {
          if (err.name === 'AbortError') return;
          clearResults();
          setHint('Gagal mengambil data.', 'text-danger');
        });
    };

    btn.addEventListener('click', () => {
      resetModal();

      const customerNameEl = document.querySelector('input[name="name"]');
      const customerName = (customerNameEl?.value || '').trim();

      modal.show();

      setTimeout(() => {
        if (!queryInput) return;

        if (customerName.length) {
          queryInput.value = customerName;

          if (customerName.length >= 3) {
            runSearch(customerName);
          } else {
            clearResults();
            setHint('Min 3 karakter.');
          }
        }

        queryInput.focus();
        queryInput.select();
      }, 180);
    });

    if (queryInput) {
      queryInput.addEventListener('input', (e) => {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => runSearch(e.target.value), 300);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindPlaces);
  } else {
    bindPlaces();
  }
})();
</script>
@endpush
