import React, { useEffect, useState } from 'react';
import { apiGet, apiUpload } from '../api.js';

export default function ReportForm({ me, onDone, onSessionEnd }) {
  const [branches, setBranches] = useState([]);
  const [branchId, setBranchId] = useState('');
  const [locations, setLocations] = useState([]);
  const [locationId, setLocationId] = useState('');
  const [description, setDescription] = useState('');
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    apiGet('/branches').then((d) => {
      setBranches(d.rows);
      if (d.rows.length === 1) setBranchId(String(d.rows[0].id));
      else if (me.branch_id) setBranchId(String(me.branch_id));
    }).catch((err) => { if (err.auth) onSessionEnd(); });
  }, [me, onSessionEnd]);

  useEffect(() => {
    if (!branchId) { setLocations([]); return; }
    apiGet('/locations', { branch_id: branchId }).then((d) => setLocations(d.rows)).catch(() => {});
    setLocationId('');
  }, [branchId]);

  const pick = (e) => {
    const f = e.target.files[0];
    setFile(f || null);
    setPreview(f ? URL.createObjectURL(f) : null);
  };

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    if (!locationId) { setError('Pilih lokasi terlebih dahulu'); return; }
    if (description.trim().length < 5) { setError('Keterangan minimal 5 karakter'); return; }
    if (!file) { setError('Foto temuan wajib dilampirkan'); return; }
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append('location_id', locationId);
      fd.append('description', description.trim());
      fd.append('photo', file);
      await apiUpload('/create', fd);
      setSuccess('Temuan berhasil dilaporkan. Notifikasi terkirim.');
      setDescription('');
      setFile(null);
      setPreview(null);
      setLocationId('');
      setTimeout(onDone, 1200);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} style={{ maxWidth: 480, margin: '0 auto' }}>
      <h2 style={{ fontSize: 18, marginBottom: 14 }}>🚩 Lapor Temuan</h2>
      {error && <div className="alert alert-error">{error}</div>}
      {success && <div className="alert alert-success">{success}</div>}

      {branches.length > 1 && (
        <div className="field">
          <label>Cabang</label>
          <select value={branchId} onChange={(e) => setBranchId(e.target.value)} required>
            <option value="">— pilih cabang —</option>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.branch_name}</option>)}
          </select>
        </div>
      )}

      <div className="field">
        <label>Lokasi</label>
        <select value={locationId} onChange={(e) => setLocationId(e.target.value)} required>
          <option value="">— pilih lokasi —</option>
          {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
        </select>
        {branchId && locations.length === 0 && (
          <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>
            Belum ada lokasi terdaftar untuk cabang ini. Minta admin menambah lewat menu Kelola.
          </div>
        )}
      </div>

      <div className="field">
        <label>Keterangan</label>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder="Contoh: barang di rak berdebu, perlu dibersihkan"
          required
        />
      </div>

      <div className="field">
        <label>Foto temuan</label>
        <input type="file" accept="image/*" capture="environment" onChange={pick} required />
        {preview && <img className="preview-img" src={preview} alt="Preview" />}
      </div>

      <button className="btn btn-primary btn-block" disabled={busy}>
        {busy ? 'Mengirim…' : 'Kirim Laporan'}
      </button>
    </form>
  );
}
