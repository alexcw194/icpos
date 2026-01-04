import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

function storageAvailable() {
  try {
    const s = window.localStorage;
    const k = "__t__";
    s.setItem(k, "1");
    s.removeItem(k);
    return true;
  } catch {
    return false;
  }
}

window.safeStorage = storageAvailable()
  ? window.localStorage
  : { getItem: () => null, setItem: () => {}, removeItem: () => {}, clear: () => {} };