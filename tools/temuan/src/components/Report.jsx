import React, { useEffect, useState, useCallback } from 'react';
import { apiGet } from '../api.js';
import { STATUS_LABEL, fmtTime } from './Dashboard.jsx';

export default function Report({ me, onSessionEnd }) {
  const firstOfMonth = new Date().toISOString().slice(0, 8) + '01';
  const today = new Date().toISOString().slice(0, 10);
  const [from, setFrom] = useState(firstOfMonth);
  const [to, setTo] = useState(today);
  const [branchId, setBranchId] = useState('');
  const [branches, setBranches] = useState([]);
  const [rows, setRows] = useState([]);
  const [summary, setSummary] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    apiGet('/branches').then((d) => setBranches(d.rows)).catch(() => {});
  }, []);

  const load = useCallback(async () => {
    setBusy(true);
    setError('');
    try {
      const data = await apiGet('/report', { from, to, branch_id: branchId });
      setRows(data.rows);
      setSummary(data.summary);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }, [from, to, branchId, onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const handling = (r) => {
    const parts = [];
    if (r.taken_by_name) parts.push(`Dikerjakan: ${r.taken_by_name}${r.taken_as ? ` (${r.taken_as})` : ''} ${fmtTime(r.taken_at)}`);
    if (r.done_by_name) parts.push(`Lapor selesai: ${r.done_by_name}${r.done_as ? ` (${r.done_as})` : ''} ${fmtTime(r.done_at)}`);
    if (r.acc_by_name) parts.push(`ACC: ${r.acc_by_name} ${fmtTime(r.acc_at)}`);
    if (r.status === 'ditolak') parts.push(`Ditolak: ${r.reject_by_name}${r.reject_as ? ` (${r.reject_as})` : ''} ${fmtTime(r.reject_at)} — ${r.reject_reason}`);
    return parts.length ? parts : ['—'];
  };

  return (
    <div>
      <h2 style={{ fontSize: 18, marginBottom: 14 }}>📊 Laporan Temuan</h2>
      {error && <div className="alert alert-error">{error}</div>}

      <div className="filter-row">
        <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="date-input" />
        <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="date-input" />
        {branches.length > 1 && (
          <select value={branchId} onChange={(e) => setBranchId(e.target.value)}>
            <option value="">Semua cabang</option>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.branch_name}</option>)}
          </select>
        )}
        <button className="btn btn-outline btn-sm" onClick={load} disabled={busy}>↻ Muat ulang</button>
      </div>

      {summary && (
        <div className="summary-row">
          {Object.entries(STATUS_LABEL).map(([k, v]) => (
            <div key={k} className={`summary-card s-${k}`}>
              <div className="num">{summary[k] ?? 0}</div>
              <div className="lbl">{k === 'ditolak' ? 'Ditolak/Batal' : v}</div>
            </div>
          ))}
        </div>
      )}

      <div className="admin-section">
        <div className="table-wrap">
          <table className="loc-table report-table">
            <thead>
              <tr>
                <th>Tanggal</th><th>Kode Area</th><th>Cabang</th><th>Temuan</th>
                <th>Pelapor</th><th>Status</th><th>Penanganan</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className={Number(r.is_deleted) ? 'inactive' : ''}>
                  <td style={{ whiteSpace: 'nowrap' }}>{fmtTime(r.created_at)}</td>
                  <td>{r.location_name}</td>
                  <td>{r.branch_name}</td>
                  <td>
                    {r.description}
                    <div style={{ display: 'flex', gap: 6, marginTop: 4 }}>
                      {r.photo_url && <a href={r.photo_url} target="_blank" rel="noreferrer">📎 foto temuan</a>}
                      {r.done_photo_url && <a href={r.done_photo_url} target="_blank" rel="noreferrer">📎 foto pengerjaan</a>}
                    </div>
                  </td>
                  <td>{r.reporter_name}</td>
                  <td>
                    <span className={`badge badge-${r.status}`}>{STATUS_LABEL[r.status] || r.status}</span>
                    {Number(r.is_deleted) ? <div style={{ fontSize: 11, color: 'var(--muted)' }}>dihapus admin</div> : null}
                  </td>
                  <td style={{ fontSize: 13 }}>
                    {handling(r).map((p, i) => <div key={i}>{p}</div>)}
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !busy && (
                <tr><td colSpan="7" style={{ color: 'var(--muted)' }}>Tidak ada temuan pada rentang ini.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
