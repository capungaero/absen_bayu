import React, { useEffect, useState, useCallback } from 'react';
import { apiGet, apiPost, apiUpload } from '../api.js';

export const STATUS_LABEL = {
  baru: 'Baru',
  dikerjakan: 'Dikerjakan',
  menunggu_acc: 'Menunggu ACC',
  selesai: 'Selesai',
  ditolak: 'Ditolak',
};

export function fmtTime(s) {
  if (!s) return '-';
  const d = new Date(s.replace(' ', 'T'));
  return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

const EXT_LABEL = { pending: 'Menunggu Keputusan', approved: 'Disetujui', rejected: 'Ditolak' };

function dueCountdown(row, nowMs) {
  if (!row.effective_due_at || row.status === 'selesai' || row.status === 'ditolak') return null;
  const due = new Date(row.effective_due_at.replace(' ', 'T')).getTime();
  const diffMs = due - nowMs;
  if (diffMs <= 0) {
    const h = Math.floor(-diffMs / 3600000);
    return h < 24 ? `⏰ TELAT ${h} jam` : `⏰ TELAT ${Math.floor(h / 24)} hari`;
  }
  const h = Math.floor(diffMs / 3600000);
  return h < 24 ? `⏳ Sisa ${h} jam` : `⏳ Sisa ${Math.floor(h / 24)} hari ${h % 24} jam`;
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
  const [rejectTarget, setRejectTarget] = useState(null);
  const [extendTarget, setExtendTarget] = useState(null);
  const [extendDecideTarget, setExtendDecideTarget] = useState(null);
  const [nowMs, setNowMs] = useState(() => Date.now());

  useEffect(() => {
    const t = setInterval(() => setNowMs(Date.now()), 30000);
    return () => clearInterval(t);
  }, []);

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

  const canRespond = (row) => me.is_admin
    || me.is_inspector
    || me.is_spv
    || Number(row.pj_user_id) === Number(me.id)
    || Number(row.spv_user_id) === Number(me.id);

  const doAction = async (path, body, confirmMsg) => {
    if (confirmMsg && !window.confirm(confirmMsg)) return;
    try {
      const data = await apiPost(path, body);
      if (data.row) setRows((prev) => prev.map((r) => (r.id === data.row.id ? data.row : r)));
      load(1);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      alert(err.message);
    }
  };

  const canAcc = (row) => me.is_admin || me.is_inspector;
  const canRequestExtension = (row) => (me.is_admin || me.is_spv)
    && ['baru', 'dikerjakan'].includes(row.status) && row.extension_status !== 'pending';
  const canDecideExtension = (row) => (me.is_admin || me.is_inspector) && row.extension_status === 'pending';

  return (
    <div>
      {error && <div className="alert alert-error">{error}</div>}

      <div className="summary-row">
        {['baru', 'dikerjakan', 'menunggu_acc', 'selesai', 'ditolak'].map((s) => (
          <div key={s} className={`summary-card s-${s}`} onClick={() => setStatus(status === s ? '' : s)} style={{ cursor: 'pointer', outline: status === s ? '2px solid var(--teal)' : 'none' }}>
            <div className="num">{summary[s]}</div>
            <div className="lbl">{STATUS_LABEL[s]}</div>
          </div>
        ))}
      </div>

      <div className="filter-row">
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Semua status</option>
          {Object.entries(STATUS_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
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
              {row.status === 'selesai' && row.is_late && <span className="badge badge-telat"> Telat</span>}
              {dueCountdown(row, nowMs) && (
                <span className={`badge ${row.is_late ? 'badge-telat' : 'badge-countdown'}`}> {dueCountdown(row, nowMs)}</span>
              )}
              <div className="tcard-loc" style={{ marginTop: 6 }}>📍 {row.location_name} · {row.branch_name}</div>
              <div className="tcard-desc">{row.description}</div>
              <div className="tcard-meta">
                Pelapor: <b>{row.reporter_name}</b> · {fmtTime(row.created_at)}<br />
                {(row.pj_name || row.spv_name) && (
                  <>{row.pj_name && <>PJ: <b>{row.pj_name}</b></>}{row.pj_name && row.spv_name && ' · '}{row.spv_name && <>SPV: <b>{row.spv_name}</b></>}<br /></>
                )}
                {row.status !== 'selesai' && row.status !== 'ditolak' && (
                  <>Deadline: <b>{fmtTime(row.effective_due_at)}</b>{row.due_extended_at ? ' (diperpanjang)' : ''}<br /></>
                )}
                {row.taken_by_name && <>Dikerjakan oleh <b>{row.taken_by_name}</b>{row.taken_as ? <span className="badge badge-actor"> {row.taken_as}</span> : null} · {fmtTime(row.taken_at)}<br /></>}
                {row.done_by_name && <>Dilaporkan selesai oleh <b>{row.done_by_name}</b>{row.done_as ? <span className="badge badge-actor"> {row.done_as}</span> : null} · {fmtTime(row.done_at)}<br /></>}
                {row.acc_by_name && <>ACC oleh <b>{row.acc_by_name}</b> · {fmtTime(row.acc_at)}<br /></>}
                {row.status === 'ditolak' && (
                  <>Ditolak oleh <b>{row.reject_by_name}</b>{row.reject_as ? <span className="badge badge-actor"> {row.reject_as}</span> : null} · {fmtTime(row.reject_at)}<br />
                  Alasan: <i>{row.reject_reason}</i></>
                )}
              </div>
              {row.extension_status !== 'none' && (
                <div className="ext-info">
                  <div>⏳ Tambahan waktu: <b className={`ext-${row.extension_status}`}>{EXT_LABEL[row.extension_status]}</b></div>
                  <div>Diajukan oleh {row.extension_requested_by_name}{row.extension_requested_as ? ` (${row.extension_requested_as})` : ''} · {fmtTime(row.extension_requested_at)}</div>
                  <div>Alasan: <i>{row.extension_reason}</i></div>
                  {row.extension_status !== 'pending' && (
                    <div>
                      Diputuskan oleh {row.extension_decided_by_name} · {fmtTime(row.extension_decided_at)}
                      {row.extension_status === 'approved' && <> — deadline baru {fmtTime(row.due_extended_at)}</>}
                      {row.extension_decision_note && <> — Catatan: <i>{row.extension_decision_note}</i></>}
                    </div>
                  )}
                </div>
              )}
            </div>
            {(() => {
              const btns = [];
              if (row.status === 'baru' && canRespond(row)) {
                btns.push(<button key="take" className="btn btn-primary btn-sm" onClick={() => doAction('/take', { id: row.id }, `Kerjakan temuan di ${row.location_name}?`)}>🛠 Kerjakan</button>);
                btns.push(<button key="rej" className="btn btn-danger" onClick={() => setRejectTarget(row)}>✖ Tolak</button>);
              }
              if (row.status === 'dikerjakan' && canRespond(row)) {
                btns.push(<button key="done" className="btn btn-primary btn-sm" onClick={() => setDoneTarget(row)}>📷 Lapor Selesai</button>);
              }
              if (row.status === 'menunggu_acc' && canAcc(row)) {
                btns.push(<button key="acc" className="btn btn-primary btn-sm" onClick={() => doAction('/acc', { id: row.id }, 'ACC — pengerjaan sudah sesuai dan temuan ditutup?')}>🆗 ACC Selesai</button>);
              }
              if (row.status === 'ditolak' && me.is_admin) {
                btns.push(<button key="del" className="btn btn-danger" onClick={() => doAction('/delete', { id: row.id }, 'Hapus temuan ditolak ini dari dashboard? (tetap tercatat di Laporan)')}>🗑 Hapus</button>);
              }
              if (canRequestExtension(row)) {
                btns.push(<button key="extreq" className="btn btn-outline btn-sm" onClick={() => setExtendTarget(row)}>⏳ Ajukan Tambahan Waktu</button>);
              }
              if (canDecideExtension(row)) {
                btns.push(<button key="extdec" className="btn btn-primary btn-sm" onClick={() => setExtendDecideTarget(row)}>⚖ Putuskan Perpanjangan</button>);
              }
              return btns.length ? <div className="tcard-actions">{btns}</div> : null;
            })()}
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

      {rejectTarget && (
        <RejectModal
          row={rejectTarget}
          onClose={() => setRejectTarget(null)}
          onSaved={(fresh) => {
            setRejectTarget(null);
            setRows((prev) => prev.map((r) => (r.id === fresh.id ? fresh : r)));
            load(1);
          }}
          onSessionEnd={onSessionEnd}
        />
      )}

      {extendTarget && (
        <ExtendRequestModal
          row={extendTarget}
          onClose={() => setExtendTarget(null)}
          onSaved={(fresh) => {
            setExtendTarget(null);
            setRows((prev) => prev.map((r) => (r.id === fresh.id ? fresh : r)));
            load(1);
          }}
          onSessionEnd={onSessionEnd}
        />
      )}

      {extendDecideTarget && (
        <ExtendDecideModal
          row={extendDecideTarget}
          onClose={() => setExtendDecideTarget(null)}
          onSaved={(fresh) => {
            setExtendDecideTarget(null);
            setRows((prev) => prev.map((r) => (r.id === fresh.id ? fresh : r)));
            load(1);
          }}
          onSessionEnd={onSessionEnd}
        />
      )}
    </div>
  );
}

function ExtendRequestModal({ row, onClose, onSaved, onSessionEnd }) {
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (reason.trim().length < 5) { setError('Alasan pengajuan minimal 5 karakter'); return; }
    setBusy(true);
    setError('');
    try {
      const data = await apiPost('/extend_request', { id: row.id, reason: reason.trim() });
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
        <h3>⏳ Ajukan Tambahan Waktu</h3>
        <div className="tcard-meta" style={{ marginBottom: 12 }}>
          📍 {row.location_name} — {row.description}<br />
          Deadline saat ini: <b>{fmtTime(row.effective_due_at)}</b>
        </div>
        {error && <div className="alert alert-error">{error}</div>}
        <div className="field">
          <label>Alasan pengajuan</label>
          <textarea value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Contoh: stok masih dalam proses restock, butuh waktu tambahan" />
        </div>
        <p style={{ fontSize: 12, color: 'var(--muted)' }}>Inspector atau admin akan menentukan deadline baru saat menyetujui.</p>
        <div className="modal-actions">
          <button className="btn btn-outline btn-sm" onClick={onClose} disabled={busy}>Batal</button>
          <button className="btn btn-primary btn-sm" onClick={submit} disabled={busy}>{busy ? 'Mengirim…' : 'Ajukan'}</button>
        </div>
      </div>
    </div>
  );
}

function ExtendDecideModal({ row, onClose, onSaved, onSessionEnd }) {
  const [approve, setApprove] = useState(true);
  const [newDue, setNewDue] = useState('');
  const [note, setNote] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (approve && !newDue) { setError('Tanggal deadline baru wajib diisi'); return; }
    setBusy(true);
    setError('');
    try {
      const data = await apiPost('/extend_decide', {
        id: row.id, approve, new_due_at: approve ? newDue : undefined, note: note.trim() || undefined,
      });
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
        <h3>⚖ Putuskan Pengajuan Tambahan Waktu</h3>
        <div className="tcard-meta" style={{ marginBottom: 12 }}>
          📍 {row.location_name} — {row.description}<br />
          Diajukan oleh <b>{row.extension_requested_by_name}</b>: <i>{row.extension_reason}</i><br />
          Deadline saat ini: <b>{fmtTime(row.effective_due_at)}</b>
        </div>
        {error && <div className="alert alert-error">{error}</div>}
        <div className="field">
          <label>Keputusan</label>
          <select value={approve ? '1' : '0'} onChange={(e) => setApprove(e.target.value === '1')}>
            <option value="1">Setujui</option>
            <option value="0">Tolak</option>
          </select>
        </div>
        {approve && (
          <div className="field">
            <label>Deadline baru</label>
            <input type="date" value={newDue} onChange={(e) => setNewDue(e.target.value)} className="date-input" />
          </div>
        )}
        <div className="field">
          <label>Catatan (opsional)</label>
          <textarea value={note} onChange={(e) => setNote(e.target.value)} placeholder="Catatan tambahan" />
        </div>
        <div className="modal-actions">
          <button className="btn btn-outline btn-sm" onClick={onClose} disabled={busy}>Batal</button>
          <button className={approve ? 'btn btn-primary btn-sm' : 'btn btn-danger'} onClick={submit} disabled={busy}>
            {busy ? 'Menyimpan…' : approve ? 'Setujui' : 'Tolak'}
          </button>
        </div>
      </div>
    </div>
  );
}

function RejectModal({ row, onClose, onSaved, onSessionEnd }) {
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (reason.trim().length < 5) { setError('Alasan penolakan minimal 5 karakter'); return; }
    setBusy(true);
    setError('');
    try {
      const data = await apiPost('/reject', { id: row.id, reason: reason.trim() });
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
        <h3>✖ Tolak Temuan</h3>
        <div className="tcard-meta" style={{ marginBottom: 12 }}>
          📍 {row.location_name} — {row.description}
        </div>
        {error && <div className="alert alert-error">{error}</div>}
        <div className="field">
          <label>Alasan penolakan</label>
          <textarea value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Contoh: salah data, temuan sudah tidak relevan, dll" />
        </div>
        <div className="modal-actions">
          <button className="btn btn-outline btn-sm" onClick={onClose} disabled={busy}>Batal</button>
          <button className="btn btn-danger" onClick={submit} disabled={busy}>{busy ? 'Menyimpan…' : 'Tolak Temuan'}</button>
        </div>
      </div>
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
        <h3>📷 Lapor Pengerjaan Selesai</h3>
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
            {busy ? 'Menyimpan…' : 'Lapor Selesai'}
          </button>
        </div>
      </div>
    </div>
  );
}
