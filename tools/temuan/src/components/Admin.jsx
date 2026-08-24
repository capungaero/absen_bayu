import React, { useEffect, useState, useCallback } from 'react';
import { apiGet, apiPost } from '../api.js';

export default function Admin({ me, onSessionEnd }) {
  return (
    <div>
      <RosterAdmin
        icon="🕵️" title="Inspector" label="inspector"
        desc="Hanya mitra yang terdaftar sebagai inspector (dan admin) yang bisa memposting temuan."
        listPath="/inspectors" addPath="/inspector_add" delPath="/inspector_delete"
        onSessionEnd={onSessionEnd}
      />
      <CategoryAdmin onSessionEnd={onSessionEnd} />
      <TypeAdmin onSessionEnd={onSessionEnd} />
      <DivisionAdmin onSessionEnd={onSessionEnd} />
      <WaContactAdmin onSessionEnd={onSessionEnd} />
      <LocationAdmin me={me} onSessionEnd={onSessionEnd} />
      <ConfigAdmin onSessionEnd={onSessionEnd} />
    </div>
  );
}

function RosterAdmin({ icon, title, label, desc, listPath, addPath, delPath, onSessionEnd }) {
  const [rows, setRows] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [pick, setPick] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [i, e] = await Promise.all([
        apiGet(listPath),
        apiGet('/employees'),
      ]);
      setRows(i.rows);
      setEmployees(e.rows);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    }
  }, [listPath, onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const add = async () => {
    if (!pick) return;
    setBusy(true);
    setError('');
    try {
      await apiPost(addPath, { user_id: pick });
      setPick('');
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const remove = async (row) => {
    if (!window.confirm(`Hapus ${row.name} dari daftar ${label}?`)) return;
    try {
      await apiPost(delPath, { id: row.id });
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  const registered = new Set(rows.map((i) => Number(i.user_id)));

  return (
    <div className="admin-section">
      <h3>{icon} {title}</h3>
      <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 12 }}>{desc}</p>
      {error && <div className="alert alert-error">{error}</div>}
      <div className="filter-row">
        <select value={pick} onChange={(e) => setPick(e.target.value)} style={{ flex: 1, minWidth: 200 }}>
          <option value="">— pilih mitra —</option>
          {employees.filter((u) => !registered.has(Number(u.id))).map((u) => (
            <option key={u.id} value={u.id}>{u.name}{u.position_name ? ` (${u.position_name})` : ''}</option>
          ))}
        </select>
        <button className="btn btn-primary btn-sm" onClick={add} disabled={busy || !pick}>+ Tambah {title}</button>
      </div>
      <div className="table-wrap">
        <table className="loc-table">
          <thead>
            <tr><th>Nama</th><th>Posisi</th><th>Cabang</th><th></th></tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                <td>{row.name}</td>
                <td>{row.position_name || '-'}</td>
                <td>{row.branch_name || '-'}</td>
                <td><button className="btn btn-danger" onClick={() => remove(row)}>Hapus</button></td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr><td colSpan="4" style={{ color: 'var(--muted)' }}>Belum ada {label} terdaftar.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function CategoryAdmin({ onSessionEnd }) {
  const [categories, setCategories] = useState([]);
  const [editing, setEditing] = useState(null);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const d = await apiGet('/categories', { all: 1 });
      setCategories(d.rows);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    }
  }, [onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const remove = async (c) => {
    if (!window.confirm(`Hapus jenis "${c.name}"? (kalau sudah dipakai nama temuan, hanya dinonaktifkan)`)) return;
    try {
      await apiPost('/category_delete', { id: c.id });
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  const save = async () => {
    if (!editing.name.trim()) return;
    try {
      await apiPost('/category_save', { id: editing.id || null, name: editing.name.trim(), is_active: editing.is_active ? 1 : 0 });
      setEditing(null);
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  return (
    <div className="admin-section">
      <h3>🗂️ Jenis (Kategori)</h3>
      <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 12 }}>
        Kategori besar temuan, mis. Kebersihan, Disiplin, Rak. Tiap Jenis bisa punya beberapa
        Nama Temuan di bawahnya (lihat bagian berikutnya).
      </p>
      {error && <div className="alert alert-error">{error}</div>}
      <button className="btn btn-primary btn-sm" style={{ marginBottom: 12 }}
        onClick={() => setEditing({ name: '', is_active: 1 })}>
        + Tambah Jenis
      </button>
      <div className="table-wrap">
        <table className="loc-table">
          <thead><tr><th>Nama Jenis</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {categories.map((c) => (
              <tr key={c.id} className={Number(c.is_active) ? '' : 'inactive'}>
                <td>{c.name}</td>
                <td>{Number(c.is_active) ? 'Aktif' : 'Nonaktif'}</td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <button className="btn btn-outline btn-sm" onClick={() => setEditing(c)}>Edit</button>{' '}
                  <button className="btn btn-danger" onClick={() => remove(c)}>Hapus</button>
                </td>
              </tr>
            ))}
            {categories.length === 0 && (
              <tr><td colSpan="3" style={{ color: 'var(--muted)' }}>Belum ada jenis.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {editing && (
        <div className="modal-back" onClick={() => setEditing(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <h3>{editing.id ? 'Edit Jenis' : 'Tambah Jenis'}</h3>
            <div className="field">
              <label>Nama jenis</label>
              <input type="text" value={editing.name} onChange={(e) => setEditing({ ...editing, name: e.target.value })} placeholder="Contoh: Kebersihan" />
            </div>
            <label className="check-row">
              <input type="checkbox" checked={!!Number(editing.is_active)} onChange={(e) => setEditing({ ...editing, is_active: e.target.checked ? 1 : 0 })} />
              Aktif
            </label>
            <div className="modal-actions">
              <button className="btn btn-outline btn-sm" onClick={() => setEditing(null)}>Batal</button>
              <button className="btn btn-primary btn-sm" onClick={save}>Simpan</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function TypeAdmin({ onSessionEnd }) {
  const [types, setTypes] = useState([]);
  const [categories, setCategories] = useState([]);
  const [editing, setEditing] = useState(null);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const [t, c] = await Promise.all([
        apiGet('/types', { all: 1 }),
        apiGet('/categories', { all: 1 }),
      ]);
      setTypes(t.rows);
      setCategories(c.rows);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    }
  }, [onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const remove = async (t) => {
    if (!window.confirm(`Hapus nama temuan "${t.name}"? (kalau sudah dipakai temuan, hanya dinonaktifkan)`)) return;
    try {
      await apiPost('/type_delete', { id: t.id });
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  return (
    <div className="admin-section">
      <h3>🏷️ Nama Temuan</h3>
      <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 12 }}>
        Label spesifik di bawah sebuah Jenis, mis. "Rak kotor", "WC tidak bersih". Tiap nama temuan diatur:
        target-nya objek (lokasi) atau individu (mitra), perlu ditindaklanjuti PJ/Pengawas atau satu arah,
        dan wajib-tidaknya foto saat lapor & saat lapor selesai.
      </p>
      {error && <div className="alert alert-error">{error}</div>}
      {categories.length === 0 && (
        <div className="alert alert-error">Belum ada Jenis (kategori). Tambah dulu di bagian sebelumnya.</div>
      )}
      <button className="btn btn-primary btn-sm" style={{ marginBottom: 12 }} disabled={categories.length === 0}
        onClick={() => setEditing({ category_id: categories[0]?.id || '', name: '', target_mode: 'objek', requires_action: 1, require_photo_initial: 1, require_photo_done: 1, is_active: 1 })}>
        + Tambah Nama Temuan
      </button>
      <div className="table-wrap">
        <table className="loc-table">
          <thead>
            <tr><th>Jenis</th><th>Nama Temuan</th><th>Target</th><th>Bisa Dikerjakan</th><th>Foto Lapor</th><th>Foto Selesai</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            {types.map((t) => (
              <tr key={t.id} className={Number(t.is_active) ? '' : 'inactive'}>
                <td>{t.category_name || '-'}</td>
                <td>{t.name}</td>
                <td>{t.target_mode === 'individu' ? 'Individu (mitra)' : 'Objek (lokasi)'}</td>
                <td>{Number(t.requires_action) ? 'Ya' : 'Satu arah'}</td>
                <td>{Number(t.require_photo_initial) ? 'Wajib' : 'Opsional'}</td>
                <td>{Number(t.require_photo_done) ? 'Wajib' : 'Opsional'}</td>
                <td>{Number(t.is_active) ? 'Aktif' : 'Nonaktif'}</td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <button className="btn btn-outline btn-sm" onClick={() => setEditing({ ...t, category_id: t.category_id || '' })}>Edit</button>{' '}
                  <button className="btn btn-danger" onClick={() => remove(t)}>Hapus</button>
                </td>
              </tr>
            ))}
            {types.length === 0 && (
              <tr><td colSpan="8" style={{ color: 'var(--muted)' }}>Belum ada nama temuan.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {editing && (
        <TypeModal
          initial={editing}
          categories={categories}
          onClose={() => setEditing(null)}
          onSaved={() => { setEditing(null); load(); }}
          onSessionEnd={onSessionEnd}
        />
      )}
    </div>
  );
}

function TypeModal({ initial, categories, onClose, onSaved, onSessionEnd }) {
  const [form, setForm] = useState(initial);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const save = async () => {
    if (!form.name.trim()) { setError('Nama temuan wajib diisi'); return; }
    if (!form.category_id) { setError('Jenis (kategori) wajib dipilih'); return; }
    setBusy(true);
    setError('');
    try {
      await apiPost('/type_save', {
        id: form.id || null,
        category_id: form.category_id,
        name: form.name.trim(),
        target_mode: form.target_mode || 'objek',
        requires_action: form.requires_action ? 1 : 0,
        require_photo_initial: form.require_photo_initial ? 1 : 0,
        require_photo_done: form.require_photo_done ? 1 : 0,
        is_active: form.is_active ? 1 : 0,
      });
      onSaved();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
      setBusy(false);
    }
  };

  return (
    <div className="modal-back" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <h3>{form.id ? 'Edit Nama Temuan' : 'Tambah Nama Temuan'}</h3>
        {error && <div className="alert alert-error">{error}</div>}
        <div className="field">
          <label>Jenis (kategori)</label>
          <select value={form.category_id} onChange={(e) => set('category_id', e.target.value)}>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>
        <div className="field">
          <label>Nama temuan</label>
          <input type="text" value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="Contoh: Rak kotor" />
        </div>
        <div className="field">
          <label>Target saat lapor</label>
          <select value={form.target_mode || 'objek'} onChange={(e) => set('target_mode', e.target.value)}>
            <option value="objek">Objek — pilih lokasi</option>
            <option value="individu">Individu — pilih mitra (bisa lebih dari satu)</option>
          </select>
          <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>
            {form.target_mode === 'individu'
              ? 'Saat lapor: pilih cabang, mitra yang ditag (bisa banyak), dan Pengawas opsional yang juga boleh menyelesaikan.'
              : 'Saat lapor: pilih lokasi/kode area seperti biasa.'}
          </div>
        </div>
        <label className="check-row">
          <input type="checkbox" checked={!!Number(form.requires_action)} onChange={(e) => set('requires_action', e.target.checked ? 1 : 0)} />
          Perlu ditindaklanjuti (matikan = satu arah, langsung tercatat selesai, cuma bisa dilihat)
        </label>
        <label className="check-row">
          <input type="checkbox" checked={!!Number(form.require_photo_initial)} onChange={(e) => set('require_photo_initial', e.target.checked ? 1 : 0)} />
          Wajib foto saat lapor
        </label>
        <label className="check-row">
          <input type="checkbox" checked={!!Number(form.require_photo_done)} onChange={(e) => set('require_photo_done', e.target.checked ? 1 : 0)} />
          Wajib foto saat Lapor Selesai
        </label>
        <label className="check-row">
          <input type="checkbox" checked={!!Number(form.is_active)} onChange={(e) => set('is_active', e.target.checked ? 1 : 0)} />
          Aktif
        </label>
        <div className="modal-actions">
          <button className="btn btn-outline btn-sm" onClick={onClose} disabled={busy}>Batal</button>
          <button className="btn btn-primary btn-sm" onClick={save} disabled={busy}>{busy ? 'Menyimpan…' : 'Simpan'}</button>
        </div>
      </div>
    </div>
  );
}

function DivisionAdmin({ onSessionEnd }) {
  const [divisions, setDivisions] = useState([]);
  const [editing, setEditing] = useState(null);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const d = await apiGet('/divisions', { all: 1 });
      setDivisions(d.rows);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    }
  }, [onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const remove = async (d) => {
    if (!window.confirm(`Hapus divisi "${d.name}"? (kalau masih dipakai area, hanya dinonaktifkan)`)) return;
    try {
      await apiPost('/division_delete', { id: d.id });
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  const save = async () => {
    if (!editing.name.trim()) return;
    try {
      await apiPost('/division_save', { id: editing.id || null, name: editing.name.trim(), is_active: editing.is_active ? 1 : 0 });
      setEditing(null);
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  return (
    <div className="admin-section">
      <h3>🏷️ Divisi</h3>
      <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 12 }}>
        Divisi kerja (mis. Finance, ME, Pramuniaga) — penanda area, tidak membatasi visibilitas.
        Pengawas hanya melihat temuan di area yang ditugaskan kepadanya.
      </p>
      {error && <div className="alert alert-error">{error}</div>}
      <button className="btn btn-primary btn-sm" style={{ marginBottom: 12 }}
        onClick={() => setEditing({ name: '', is_active: 1 })}>
        + Tambah Divisi
      </button>
      <div className="table-wrap">
        <table className="loc-table">
          <thead><tr><th>Nama Divisi</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {divisions.map((d) => (
              <tr key={d.id} className={Number(d.is_active) ? '' : 'inactive'}>
                <td>{d.name}</td>
                <td>{Number(d.is_active) ? 'Aktif' : 'Nonaktif'}</td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <button className="btn btn-outline btn-sm" onClick={() => setEditing(d)}>Edit</button>{' '}
                  <button className="btn btn-danger" onClick={() => remove(d)}>Hapus</button>
                </td>
              </tr>
            ))}
            {divisions.length === 0 && (
              <tr><td colSpan="3" style={{ color: 'var(--muted)' }}>Belum ada divisi.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {editing && (
        <div className="modal-back" onClick={() => setEditing(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <h3>{editing.id ? 'Edit Divisi' : 'Tambah Divisi'}</h3>
            <div className="field">
              <label>Nama divisi</label>
              <input type="text" value={editing.name} onChange={(e) => setEditing({ ...editing, name: e.target.value })} placeholder="Contoh: Finance, ME, Pramuniaga" />
            </div>
            <label className="check-row">
              <input type="checkbox" checked={!!Number(editing.is_active)} onChange={(e) => setEditing({ ...editing, is_active: e.target.checked ? 1 : 0 })} />
              Aktif
            </label>
            <div className="modal-actions">
              <button className="btn btn-outline btn-sm" onClick={() => setEditing(null)}>Batal</button>
              <button className="btn btn-primary btn-sm" onClick={save}>Simpan</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function WaContactAdmin({ onSessionEnd }) {
  const [contacts, setContacts] = useState([]);
  const [editing, setEditing] = useState(null);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const d = await apiGet('/wa_contacts', { all: 1 });
      setContacts(d.rows);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    }
  }, [onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const remove = async (c) => {
    if (!window.confirm(`Hapus kontak "${c.name}"?`)) return;
    try {
      await apiPost('/wa_contact_delete', { id: c.id });
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  const save = async () => {
    if (!editing.name.trim() || !editing.phone.trim()) return;
    try {
      await apiPost('/wa_contact_save', {
        id: editing.id || null,
        name: editing.name.trim(),
        phone: editing.phone.trim(),
        is_active: editing.is_active ? 1 : 0,
      });
      setEditing(null);
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  return (
    <div className="admin-section">
      <h3>📱 Kontak Notifikasi WA</h3>
      <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 12 }}>
        Nomor HP + nama yang bisa dipilih sebagai penerima notif WA untuk Kode Area tertentu
        (di bagian Kode Area, PJ &amp; Pengawas Area di bawah).
      </p>
      {error && <div className="alert alert-error">{error}</div>}
      <button className="btn btn-primary btn-sm" style={{ marginBottom: 12 }}
        onClick={() => setEditing({ name: '', phone: '', is_active: 1 })}>
        + Tambah Kontak
      </button>
      <div className="table-wrap">
        <table className="loc-table">
          <thead><tr><th>Nama</th><th>Nomor HP</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {contacts.map((c) => (
              <tr key={c.id} className={Number(c.is_active) ? '' : 'inactive'}>
                <td>{c.name}</td>
                <td>{c.phone}</td>
                <td>{Number(c.is_active) ? 'Aktif' : 'Nonaktif'}</td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <button className="btn btn-outline btn-sm" onClick={() => setEditing(c)}>Edit</button>{' '}
                  <button className="btn btn-danger" onClick={() => remove(c)}>Hapus</button>
                </td>
              </tr>
            ))}
            {contacts.length === 0 && (
              <tr><td colSpan="4" style={{ color: 'var(--muted)' }}>Belum ada kontak.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {editing && (
        <div className="modal-back" onClick={() => setEditing(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <h3>{editing.id ? 'Edit Kontak' : 'Tambah Kontak'}</h3>
            <div className="field">
              <label>Nama</label>
              <input type="text" value={editing.name} onChange={(e) => setEditing({ ...editing, name: e.target.value })} placeholder="Contoh: Pak Budi (Owner)" />
            </div>
            <div className="field">
              <label>Nomor HP</label>
              <input type="text" value={editing.phone} onChange={(e) => setEditing({ ...editing, phone: e.target.value })} placeholder="Contoh: 6281234567890" />
            </div>
            <label className="check-row">
              <input type="checkbox" checked={!!Number(editing.is_active)} onChange={(e) => setEditing({ ...editing, is_active: e.target.checked ? 1 : 0 })} />
              Aktif
            </label>
            <div className="modal-actions">
              <button className="btn btn-outline btn-sm" onClick={() => setEditing(null)}>Batal</button>
              <button className="btn btn-primary btn-sm" onClick={save}>Simpan</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function LocationAdmin({ me, onSessionEnd }) {
  const [branches, setBranches] = useState([]);
  const [locations, setLocations] = useState([]);
  const [editing, setEditing] = useState(null);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const [b, l] = await Promise.all([
        apiGet('/branches'),
        apiGet('/locations', { all: 1 }),
      ]);
      setBranches(b.rows);
      setLocations(l.rows);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    }
  }, [onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const remove = async (loc) => {
    if (!window.confirm(`Hapus lokasi "${loc.name}"? (kalau sudah dipakai temuan, hanya dinonaktifkan)`)) return;
    try {
      await apiPost('/location_delete', { id: loc.id });
      load();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  return (
    <div className="admin-section">
      <h3>📍 Kode Area, PJ & Pengawas Area</h3>
      <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 12 }}>
        Kode area = lokasi temuan (mis. Rak 1, WC, Gudang). Tiap area bisa punya lebih dari satu PJ
        dan lebih dari satu Pengawas (tanggung jawab bersama) — semua merespon temuan area itu.
      </p>
      {error && <div className="alert alert-error">{error}</div>}
      <button className="btn btn-primary btn-sm" style={{ marginBottom: 12 }}
        onClick={() => setEditing({ branch_id: me.branch_id || (branches[0]?.id ?? ''), name: '', pj_user_ids: [], spv_user_ids: [], primary_spv_id: null, contact_ids: [], division_id: '', is_active: 1 })}>
        + Tambah Kode Area
      </button>
      <div className="table-wrap">
        <table className="loc-table">
          <thead>
            <tr><th>Kode Area</th><th>Cabang</th><th>Divisi</th><th>PJ Area</th><th>Pengawas Area</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            {locations.map((l) => (
              <tr key={l.id} className={Number(l.is_active) ? '' : 'inactive'}>
                <td>{l.name}</td>
                <td>{l.branch_name}</td>
                <td>{l.division_name || <i style={{ color: 'var(--muted)' }}>—</i>}</td>
                <td>{l.pj_names || <i style={{ color: 'var(--muted)' }}>belum ada</i>}</td>
                <td>
                  {l.primary_spv_name ? (
                    <>
                      <b>{l.primary_spv_name}</b> <span style={{ fontSize: 11, color: 'var(--muted)' }}>(Utama)</span>
                      {l.backup_spv_names && (
                        <div style={{ fontSize: 12, color: 'var(--muted)' }}>Backup: {l.backup_spv_names}</div>
                      )}
                    </>
                  ) : <i style={{ color: 'var(--muted)' }}>belum ada</i>}
                </td>
                <td>{Number(l.is_active) ? 'Aktif' : 'Nonaktif'}</td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <button className="btn btn-outline btn-sm" onClick={() => setEditing({
                    ...l,
                    pj_user_ids: l.pj_user_ids ? l.pj_user_ids.split(',').map(Number) : [],
                    spv_user_ids: l.spv_user_ids ? l.spv_user_ids.split(',').map(Number) : [],
                    primary_spv_id: l.primary_spv_id ? Number(l.primary_spv_id) : null,
                    contact_ids: l.contact_ids ? l.contact_ids.split(',').map(Number) : [],
                    division_id: l.division_id || '',
                  })}>Edit</button>{' '}
                  <button className="btn btn-danger" onClick={() => remove(l)}>Hapus</button>
                </td>
              </tr>
            ))}
            {locations.length === 0 && (
              <tr><td colSpan="7" style={{ color: 'var(--muted)' }}>Belum ada lokasi.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {editing && (
        <LocationModal
          initial={editing}
          branches={branches}
          onClose={() => setEditing(null)}
          onSaved={() => { setEditing(null); load(); }}
          onSessionEnd={onSessionEnd}
        />
      )}
    </div>
  );
}

function LocationModal({ initial, branches, onClose, onSaved, onSessionEnd }) {
  const [divisions, setDivisions] = useState([]);
  const [contacts, setContacts] = useState([]);
  const [form, setForm] = useState(initial);
  const [employees, setEmployees] = useState([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!form.branch_id) { setEmployees([]); return; }
    apiGet('/employees', { branch_id: form.branch_id }).then((d) => setEmployees(d.rows)).catch(() => {});
  }, [form.branch_id]);

  useEffect(() => {
    apiGet('/divisions').then((d) => setDivisions(d.rows)).catch(() => {});
    apiGet('/wa_contacts').then((d) => setContacts(d.rows)).catch(() => {});
  }, []);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  // Notif WA pakai NO KERJA (bukan no pribadi di master karyawan -- karyawan
  // dilarang bawa HP). Saat menugaskan orang yang belum punya no kerja
  // tersimpan, minta input dulu; batal input = batal centang.
  const askWorkPhone = (uid) => {
    const emp = employees.find((u) => Number(u.id) === uid);
    if (emp?.work_phone || (form.work_phones || {})[uid]) return true;
    const no = window.prompt(
      `No. WA KERJA untuk ${emp?.name || 'karyawan ini'} (bukan no pribadi):`, '');
    const phone = (no || '').trim();
    if (!phone) return false;
    setForm((f) => ({ ...f, work_phones: { ...(f.work_phones || {}), [uid]: phone } }));
    return true;
  };

  const togglePj = (uid) => {
    const has = (form.pj_user_ids || []).includes(uid);
    if (!has && !askWorkPhone(uid)) return;
    setForm((f) => {
      const cur = f.pj_user_ids || [];
      return { ...f, pj_user_ids: has ? cur.filter((x) => x !== uid) : [...cur, uid] };
    });
  };

  const toggleSpv = (uid) => {
    const has = (form.spv_user_ids || []).includes(uid);
    if (!has && !askWorkPhone(uid)) return;
    setForm((f) => {
      const cur = f.spv_user_ids || [];
      const next = has ? cur.filter((x) => x !== uid) : [...cur, uid];
      let primary = f.primary_spv_id;
      if (has && primary === uid) primary = next[0] ?? null; // Utama dihapus -> pindah ke sisa pertama
      if (!has && next.length === 1) primary = uid; // orang pertama otomatis jadi Utama
      return { ...f, spv_user_ids: next, primary_spv_id: primary };
    });
  };

  const toggleContact = (cid) => {
    setForm((f) => {
      const cur = f.contact_ids || [];
      const has = cur.includes(cid);
      return { ...f, contact_ids: has ? cur.filter((x) => x !== cid) : [...cur, cid] };
    });
  };

  const save = async () => {
    if (!form.name.trim()) { setError('Nama lokasi wajib diisi'); return; }
    setBusy(true);
    setError('');
    try {
      await apiPost('/location_save', {
        id: form.id || null,
        branch_id: form.branch_id,
        name: form.name.trim(),
        pj_user_ids: form.pj_user_ids || [],
        spv_user_ids: form.spv_user_ids || [],
        primary_spv_id: form.primary_spv_id || null,
        contact_ids: form.contact_ids || [],
        division_id: form.division_id || null,
        is_active: form.is_active ? 1 : 0,
        work_phones: form.work_phones || {},
      });
      onSaved();
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
      setBusy(false);
    }
  };

  return (
    <div className="modal-back" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <h3>{form.id ? 'Edit Kode Area' : 'Tambah Kode Area'}</h3>
        {error && <div className="alert alert-error">{error}</div>}
        <div className="field">
          <label>Cabang</label>
          <select value={form.branch_id} onChange={(e) => set('branch_id', e.target.value)} disabled={branches.length <= 1}>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.branch_name}</option>)}
          </select>
        </div>
        <div className="field">
          <label>Kode area / nama lokasi</label>
          <input type="text" value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="Contoh: Rak 1, WC, Gudang" />
        </div>
        <div className="field">
          <label>PJ Area (penanggung jawab — bisa lebih dari satu)</label>
          <div className="pj-checklist">
            {employees.map((u) => (
              <label key={u.id} className="check-row" style={{ marginBottom: 4 }}>
                <input
                  type="checkbox"
                  checked={(form.pj_user_ids || []).includes(Number(u.id))}
                  onChange={() => togglePj(Number(u.id))}
                />
                {u.name}{u.position_name ? ` (${u.position_name})` : ''}
                <span style={{ fontSize: 11, color: 'var(--muted)', marginLeft: 6 }}>
                  {(form.work_phones || {})[Number(u.id)] || u.work_phone
                    ? `📱 ${(form.work_phones || {})[Number(u.id)] || u.work_phone}` : 'belum ada no kerja'}
                </span>
              </label>
            ))}
            {employees.length === 0 && <div style={{ fontSize: 12, color: 'var(--muted)' }}>Pilih cabang dulu.</div>}
          </div>
          {(form.pj_user_ids || []).length === 0 && (
            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>Belum ada PJ dipilih.</div>
          )}
        </div>
        <div className="field">
          <label>Pengawas Area (bisa lebih dari satu)</label>
          <div className="pj-checklist">
            {employees.map((u) => (
              <label key={u.id} className="check-row" style={{ marginBottom: 4 }}>
                <input
                  type="checkbox"
                  checked={(form.spv_user_ids || []).includes(Number(u.id))}
                  onChange={() => toggleSpv(Number(u.id))}
                />
                {u.name}{u.position_name ? ` (${u.position_name})` : ''}
                <span style={{ fontSize: 11, color: 'var(--muted)', marginLeft: 6 }}>
                  {(form.work_phones || {})[Number(u.id)] || u.work_phone
                    ? `📱 ${(form.work_phones || {})[Number(u.id)] || u.work_phone}` : 'belum ada no kerja'}
                </span>
              </label>
            ))}
            {employees.length === 0 && <div style={{ fontSize: 12, color: 'var(--muted)' }}>Pilih cabang dulu.</div>}
          </div>
          {(form.spv_user_ids || []).length === 0 && (
            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>Belum ada Pengawas dipilih.</div>
          )}
          {(form.spv_user_ids || []).length > 1 && (
            <div style={{ marginTop: 8 }}>
              <label style={{ fontSize: 12, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>
                Pengawas Utama (dipakai notif WA &amp; rekap laporan; sisanya jadi backup)
              </label>
              {(form.spv_user_ids || []).map((uid) => {
                const emp = employees.find((e) => Number(e.id) === uid);
                return (
                  <label key={uid} className="check-row" style={{ marginBottom: 4 }}>
                    <input
                      type="radio"
                      name="primary_spv"
                      checked={form.primary_spv_id === uid}
                      onChange={() => set('primary_spv_id', uid)}
                    />
                    {emp ? `${emp.name}${emp.position_name ? ` (${emp.position_name})` : ''}` : uid}
                  </label>
                );
              })}
            </div>
          )}
        </div>
        <div className="field">
          <label>Divisi (opsional, penanda area)</label>
          <select value={form.division_id} onChange={(e) => set('division_id', e.target.value)}>
            <option value="">— tanpa divisi —</option>
            {divisions.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
          </select>
        </div>
        <div className="field">
          <label>Notifikasi WA (kontak tambahan, opsional)</label>
          <div className="pj-checklist">
            {contacts.map((c) => (
              <label key={c.id} className="check-row" style={{ marginBottom: 4 }}>
                <input
                  type="checkbox"
                  checked={(form.contact_ids || []).includes(Number(c.id))}
                  onChange={() => toggleContact(Number(c.id))}
                />
                {c.name} ({c.phone})
              </label>
            ))}
            {contacts.length === 0 && (
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                Belum ada kontak. Tambah di bagian "Kontak Notifikasi WA" di atas.
              </div>
            )}
          </div>
          <p style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>
            Selain Pengawas Utama area ini, kontak yang dicentang juga akan dapat notif WA.
          </p>
        </div>
        <label className="check-row">
          <input type="checkbox" checked={!!Number(form.is_active)} onChange={(e) => set('is_active', e.target.checked ? 1 : 0)} />
          Aktif
        </label>
        <div className="modal-actions">
          <button className="btn btn-outline btn-sm" onClick={onClose} disabled={busy}>Batal</button>
          <button className="btn btn-primary btn-sm" onClick={save} disabled={busy}>{busy ? 'Menyimpan…' : 'Simpan'}</button>
        </div>
      </div>
    </div>
  );
}

function ConfigAdmin({ onSessionEnd }) {
  const [cfg, setCfg] = useState(null);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);
  const [testPhone, setTestPhone] = useState('');
  const [testMsg, setTestMsg] = useState('');
  const [testBusy, setTestBusy] = useState(false);
  const [testResult, setTestResult] = useState(null);

  useEffect(() => {
    apiGet('/config').then((d) => setCfg(d.config)).catch((err) => {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    });
  }, [onSessionEnd]);

  if (!cfg) return <div className="admin-section"><h3>📱 Notifikasi WA</h3>{error && <div className="alert alert-error">{error}</div>}</div>;

  const set = (k, v) => setCfg((c) => ({ ...c, [k]: v }));

  const save = async () => {
    setBusy(true);
    setError('');
    setSuccess('');
    try {
      await apiPost('/save_config', {
        notify_enabled: Number(cfg.notify_enabled),
        notify_done_enabled: Number(cfg.notify_done_enabled),
        target_phones: cfg.target_phones || '',
      });
      setSuccess('Konfigurasi tersimpan.');
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const testSend = async () => {
    if (!testPhone.trim()) { setTestResult({ ok: false, message: 'Isi nomor HP dulu' }); return; }
    setTestBusy(true);
    setTestResult(null);
    try {
      const data = await apiPost('/test_send', { phone: testPhone.trim(), message: testMsg.trim() });
      setTestResult({ ok: !!data.status, message: data.message });
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setTestResult({ ok: false, message: err.message });
    } finally {
      setTestBusy(false);
    }
  };

  return (
    <div className="admin-section">
      <h3>📱 Notifikasi WA</h3>
      <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 12 }}>
        Pesan dikirim lewat gateway WA yang sama dengan WA Agent (kirimi.id). Isi nomor tujuan
        (mis. anggota grup), pisahkan dengan koma.
      </p>
      {error && <div className="alert alert-error">{error}</div>}
      {success && <div className="alert alert-success">{success}</div>}
      <label className="check-row">
        <input type="checkbox" checked={!!Number(cfg.notify_enabled)} onChange={(e) => set('notify_enabled', e.target.checked ? 1 : 0)} />
        Kirim notifikasi saat ada temuan baru
      </label>
      <label className="check-row">
        <input type="checkbox" checked={!!Number(cfg.notify_done_enabled)} onChange={(e) => set('notify_done_enabled', e.target.checked ? 1 : 0)} />
        Kirim notifikasi saat temuan selesai
      </label>
      <div className="field">
        <label>Nomor tujuan (628xxx, pisah koma)</label>
        <textarea value={cfg.target_phones || ''} onChange={(e) => set('target_phones', e.target.value)} placeholder="6281234567890, 6289876543210" />
      </div>
      <button className="btn btn-primary" onClick={save} disabled={busy}>{busy ? 'Menyimpan…' : 'Simpan Konfigurasi'}</button>

      <hr style={{ margin: '18px 0' }} />
      <h4 style={{ fontSize: 14, marginBottom: 8 }}>🧪 Test Kirim WA</h4>
      {testResult && (
        <div className={`alert ${testResult.ok ? 'alert-success' : 'alert-error'}`}>{testResult.message}</div>
      )}
      <div className="field">
        <label>Nomor HP tujuan (628xxx)</label>
        <input type="text" value={testPhone} onChange={(e) => setTestPhone(e.target.value)} placeholder="6281234567890" />
      </div>
      <div className="field">
        <label>Pesan (opsional)</label>
        <textarea value={testMsg} onChange={(e) => setTestMsg(e.target.value)} placeholder="Kosongkan untuk pesan default" />
      </div>
      <button className="btn btn-outline btn-sm" onClick={testSend} disabled={testBusy}>{testBusy ? 'Mengirim…' : 'Kirim Test'}</button>
    </div>
  );
}
