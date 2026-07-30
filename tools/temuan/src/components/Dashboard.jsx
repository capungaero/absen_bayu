import React, { useEffect, useState, useCallback } from 'react';
import { apiGet, apiPost, apiUpload } from '../api.js';

const STATUS_LABEL = { baru: 'Baru', dikerjakan: 'Dikerjakan', selesai: 'Selesai' };

function fmtTime(s) {
  if (!s) return '-';
  const d = new Date(s.replace(' ', 'T'));
  return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export default function Dashboard({ me, onSessionEnd }) {
  const [rows, setRows] = useState([]);
  const [summary, setSummary] = useState({ baru: 0, dikerjakan: 0, selesai: 0 });
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const [branchId, setBranchId] = useState('');
  const [locationId, setLocationId] = useState('');
  const [branches, setBranches] = useState([]);
  const [locations, setLocations] = useState([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [viewPhoto, setViewPhoto] = useState(null);
  const [doneTarget, setDoneTarget] = useState(null);

  const load = useCallback(async (p = 1, append = false) => {
    setBusy(true);
    setError('');
    try {
      const data = await apiGet('/list', { status, branch_id: branchId, location_id: locationId, page: p });
      setRows((prev) => (append ? [...prev, ...data.rows] : data.rows));
      setSummary(data.summary);
      setTotal(data.total);
      setPage(p);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }, [status, branchId, locationId, onSessionEnd]);

  useEffect(() => { load(1); }, [load]);

  useEffect(() => {
    apiGet('/branches').then((d) => setBranches(d.rows)).catch(() => {});
  }, []);

  useEffect(() => {
    apiGet('/locations', { branch_id: branchId }).then((d) => setLocations(d.rows)).catch(() => {});
  }, [branchId]);

  const canRespond = (row) => me.is_admin || me.role === 'supervisor' || Number(row.pj_user_id) === Number(me.id);

  const take = async (row) => {
    if (!window.confirm(`Kerjakan temuan di ${row.location_name}?`)) return;
    try {
      const data = await apiPost('/take', { id: row.id });
      setRows((prev) => prev.map((r) => (r.id === row.id ? data.row : r)));
      load(1);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  return (
    <div>
      {error && <div className="alert alert-error">{error}</div>}

      <div className="summary-row">
        {['baru', 'dikerjakan', 'selesai'].map((s) => (
          <div key={s} className={`summary-card s-${s}`} onClick={() => setStatus(status === s ? '' : s)} style={{ cursor: 'pointer', outline: status === s ? '2px solid var(--teal)' : 'none' }}>
            <div className="num">{summary[s]}</div>
            <div className="lbl">{STATUS_LABEL[s]}</div>
          </div>
        ))}
      </div>

      <div className="filter-row">
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Semua status</option>
          <option value="baru">Baru</option>
          <option value="dikerjakan">Dikerjakan</option>
          <option value="selesai">Selesai</option>
        </select>
        {branches.length > 1 && (
          <select value={branchId} onChange={(e) => { setBranchId(e.target.value); setLocationId(''); }}>
            <option value="">Semua cabang</option>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.branch_name}</option>)}
          </select>
        )}
        <select value={locationId} onChange={(e) => setLocationId(e.target.value)}>
          <option value="">Semua lokasi</option>
          {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
        </select>
        <button className="btn btn-outline btn-sm" onClick={() => load(1)} disabled={busy}>↻ Muat ulang</button>
      </div>

      {rows.length === 0 && !busy && <div className="empty">Belum ada temuan.</div>}

      <div className="cards">
        {rows.map((row) => (
          <div key={row.id} className="tcard">
            <div className="tcard-photos">
              {row.photo_url && <img src={row.photo_url} alt="Foto temuan" onClick={() => setViewPhoto({ url: row.photo_url, title: 'Foto temuan' })} />}
              {row.done_photo_url && <img src={row.done_photo_url} alt="Foto selesai" onClick={() => setViewPhoto({ url: row.done_photo_url, title: 'Foto selesai' })} />}
            </div>
            <div className="tcard-body">
              <span className={`badge badge-${row.status}`}>{STATUS_LABEL[row.status]}</span>
              <div className="tcard-loc" style={{ marginTop: 6 }}>📍 {row.location_name} · {row.branch_name}</div>
              <div className="tcard-desc">{row.description}</div>
              <div className="tcard-meta">
                Pelapor: <b>{row.reporter_name}</b> · {fmtTime(row.created_at)}<br />
                {row.pj_name && <>PJ: <b>{row.pj_name}</b><br /></>}
                {row.taken_by_name && <>Dikerjakan oleh <b>{row.taken_by_name}</b>{row.taken_as ? <span className={`badge badge-actor`}> {row.taken_as}</span> : null} · {fmtTime(row.taken_at)}<br /></>}
                {row.done_by_name && <>Selesai oleh <b>{row.done_by_name}</b>{row.done_as ? <span className={`badge badge-actor`}> {row.done_as}</span> : null} · {fmtTime(row.done_at)}</>}
              </div>
            </div>
            {row.status !== 'selesai' && canRespond(row) && (
              <div className="tcard-actions">
                {row.status === 'baru' && (
                  <button className="btn btn-primary btn-sm" onClick={() => take(row)}>🛠 Kerjakan</button>
                )}
                <button className="btn btn-outline btn-sm" onClick={() => setDoneTarget(row)}>✅ Selesai</button>
              </div>
            )}
          </div>
        ))}
      </div>

      {rows.length < total && (
        <div className="load-more">
          <button className="btn btn-outline" onClick={() => load(page + 1, true)} disabled={busy}>
            Muat lebih banyak ({rows.length}/{total})
          </button>
        </div>
      )}

      {viewPhoto && (
        <div className="modal-back" onClick={() => setViewPhoto(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <h3>{viewPhoto.title}</h3>
            <img className="photo-full" src={viewPhoto.url} alt={viewPhoto.title} />
            <div className="modal-actions">
              <button className="btn btn-outline btn-sm" onClick={() => setViewPhoto(null)}>Tutup</button>
            </div>
          </div>
        </div>
      )}

      {doneTarget && (
        <DoneModal
          row={doneTarget}
          onClose={() => setDoneTarget(null)}
          onSaved={(fresh) => {
            setDoneTarget(null);
            setRows((prev) => prev.map((r) => (r.id === fresh.id ? fresh : r)));
            load(1);
          }}
          onSessionEnd={onSessionEnd}
        />
      )}
    </div>
  );
}

function DoneModal({ row, onClose, onSaved, onSessionEnd }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const pick = (e) => {
    const f = e.target.files[0];
    setFile(f || null);
    setPreview(f ? URL.createObjectURL(f) : null);
  };

  const submit = async () => {
    if (!file) { setError('Foto hasil pengerjaan wajib dilampirkan'); return; }
    setBusy(true);
    setError('');
    try {
      const fd = new FormData();
      fd.append('id', row.id);
      fd.append('photo', file);
      const data = await apiUpload('/done', fd);
      onSaved(data.row);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
      setBusy(false);
    }
  };

  return (
    <div className="modal-back" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <h3>✅ Selesaikan Temuan</h3>
        <div className="tcard-meta" style={{ marginBottom: 12 }}>
          📍 {row.location_name} — {row.description}
        </div>
        {error && <div className="alert alert-error">{error}</div>}
        <div className="field">
          <label>Foto hasil pengerjaan</label>
          <input type="file" accept="image/*" capture="environment" onChange={pick} />
          {preview && <img className="preview-img" src={preview} alt="Preview" />}
        </div>
        <div className="modal-actions">
          <button className="btn btn-outline btn-sm" onClick={onClose} disabled={busy}>Batal</button>
          <button className="btn btn-primary btn-sm" onClick={submit} disabled={busy}>
            {busy ? 'Menyimpan…' : 'Tandai Selesai'}
          </button>
        </div>
      </div>
    </div>
  );
}
