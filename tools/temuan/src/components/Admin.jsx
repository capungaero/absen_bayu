import React, { useEffect, useState, useCallback } from 'react';
import { apiGet, apiPost } from '../api.js';

export default function Admin({ me, onSessionEnd }) {
  return (
    <div>
      <RosterAdmin
        icon="🕵️" title="Inspector" label="inspector"
        desc="Hanya karyawan yang terdaftar sebagai inspector (dan admin) yang bisa memposting temuan."
        listPath="/inspectors" addPath="/inspector_add" delPath="/inspector_delete"
        onSessionEnd={onSessionEnd}
      />
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
          <option value="">— pilih karyawan —</option>
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

function LocationAdmin({ me, onSessionEnd }) {
  const [branches, setBranches] = useState([]);
  const [locations, setLocations] = useState([]);
  const [editing, setEditing] = useState(null); // {id?, branch_id, name, pj_user_id, is_active}
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
      <h3>📍 Kode Area, PJ &amp; SPV Area</h3>
      <p style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 12 }}>
        Kode area = lokasi temuan (mis. Rak 1, WC, Gudang). Tiap area punya 1 PJ dan 1 SPV —
        keduanya merespon temuan area itu, dan hanya melihat progres areanya sendiri.
      </p>
      {error && <div className="alert alert-error">{error}</div>}
      <button className="btn btn-primary btn-sm" style={{ marginBottom: 12 }}
        onClick={() => setEditing({ branch_id: me.branch_id || (branches[0]?.id ?? ''), name: '', pj_user_id: '', spv_user_id: '', is_active: 1 })}>
        + Tambah Kode Area
      </button>
      <div className="table-wrap">
        <table className="loc-table">
          <thead>
            <tr><th>Kode Area</th><th>Cabang</th><th>PJ Area</th><th>SPV Area</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            {locations.map((l) => (
              <tr key={l.id} className={Number(l.is_active) ? '' : 'inactive'}>
                <td>{l.name}</td>
                <td>{l.branch_name}</td>
                <td>{l.pj_name || <i style={{ color: 'var(--muted)' }}>belum ada</i>}</td>
                <td>{l.spv_name || <i style={{ color: 'var(--muted)' }}>belum ada</i>}</td>
                <td>{Number(l.is_active) ? 'Aktif' : 'Nonaktif'}</td>
                <td style={{ whiteSpace: 'nowrap' }}>
                  <button className="btn btn-outline btn-sm" onClick={() => setEditing({ ...l, pj_user_id: l.pj_user_id || '', spv_user_id: l.spv_user_id || '' })}>Edit</button>{' '}
                  <button className="btn btn-danger" onClick={() => remove(l)}>Hapus</button>
                </td>
              </tr>
            ))}
            {locations.length === 0 && (
              <tr><td colSpan="6" style={{ color: 'var(--muted)' }}>Belum ada lokasi.</td></tr>
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
  const [form, setForm] = useState(initial);
  const [employees, setEmployees] = useState([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!form.branch_id) { setEmployees([]); return; }
    apiGet('/employees', { branch_id: form.branch_id }).then((d) => setEmployees(d.rows)).catch(() => {});
  }, [form.branch_id]);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const save = async () => {
    if (!form.name.trim()) { setError('Nama lokasi wajib diisi'); return; }
    setBusy(true);
    setError('');
    try {
      await apiPost('/location_save', {
        id: form.id || null,
        branch_id: form.branch_id,
        name: form.name.trim(),
        pj_user_id: form.pj_user_id || null,
        spv_user_id: form.spv_user_id || null,
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
          <label>PJ Area (penanggung jawab)</label>
          <select value={form.pj_user_id} onChange={(e) => set('pj_user_id', e.target.value)}>
            <option value="">— belum ditentukan —</option>
            {employees.map((u) => <option key={u.id} value={u.id}>{u.name}{u.position_name ? ` (${u.position_name})` : ''}</option>)}
          </select>
        </div>
        <div className="field">
          <label>SPV Area (supervisor)</label>
          <select value={form.spv_user_id} onChange={(e) => set('spv_user_id', e.target.value)}>
            <option value="">— belum ditentukan —</option>
            {employees.map((u) => <option key={u.id} value={u.id}>{u.name}{u.position_name ? ` (${u.position_name})` : ''}</option>)}
          </select>
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
