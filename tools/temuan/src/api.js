// Klien API Temuan — bearer token (localStorage), pola sama dengan PWA karyawan.
export const API = import.meta.env.VITE_API_BASE ?? '/absen/temuan';
export const AUTH_API = import.meta.env.VITE_AUTH_API ?? '/absen/api';
const TKEY = 'temuan_token';

export function getToken() { return localStorage.getItem(TKEY) || ''; }
export function setToken(t) { localStorage.setItem(TKEY, t); }
export function clearToken() { localStorage.removeItem(TKEY); }

async function handle(res) {
  let data = null;
  try { data = await res.json(); } catch { /* non-JSON */ }
  if (res.status === 401) {
    clearToken();
    const err = new Error(data?.message || 'Sesi berakhir, silakan login ulang');
    err.auth = true;
    throw err;
  }
  if (!res.ok || (data && data.status === false)) {
    throw new Error(data?.message || data?.error || `HTTP ${res.status}`);
  }
  return data;
}

export async function apiGet(path, params = {}) {
  const qs = new URLSearchParams(
    Object.fromEntries(Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== ''))
  ).toString();
  const res = await fetch(`${API}${path}${qs ? '?' + qs : ''}`, {
    headers: { Authorization: 'Bearer ' + getToken() },
  });
  return handle(res);
}

export async function apiPost(path, body = {}) {
  const res = await fetch(`${API}${path}`, {
    method: 'POST',
    headers: { Authorization: 'Bearer ' + getToken(), 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return handle(res);
}

export async function apiUpload(path, formData) {
  const res = await fetch(`${API}${path}`, {
    method: 'POST',
    headers: { Authorization: 'Bearer ' + getToken() },
    body: formData,
  });
  return handle(res);
}

export async function login(email, password) {
  const res = await fetch(`${AUTH_API}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const data = await handle(res);
  setToken(data.token);
  return data;
}
