import React, { useEffect, useState, useCallback } from 'react';
import { apiGet, API, getToken } from '../api.js';
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
  const [summaryRows, setSummaryRows] = useState([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    apiGet('/branches').then((d) => setBranches(d.rows)).catch(() => {});
  }, []);

  const load = useCallback(async () => {
    setBusy(true);
    setError('');
    try {
      const [data, sum] = await Promise.all([
        apiGet('/report', { from, to, branch_id: branchId }),
        apiGet('/report_summary', { from, to, branch_id: branchId }),
      ]);
      setRows(data.rows);
      setSummary(data.summary);
      setSummaryRows(sum.rows);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }, [from, to, branchId, onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const canExportExcel = me.is_admin || me.is_inspector;
  const qs = `from=${from}&to=${to}${branchId ? `&branch_id=${branchId}` : ''}&token=${encodeURIComponent(getToken())}`;
  const excelUrl = `${API}/report_excel?${qs}`;
  const detailExcelUrl = `${API}/report_detail_excel?${qs}`;

  const handling = (r) => {
    const parts = [];
    if (r.taken_by_name) parts.push(`Dikerjakan: ${r.taken_by_name}${r.taken_as ? ` (${r.taken_as})` : ''} ${fmtTime(r.taken_at)}`);
    if (r.done_by_name) parts.push(`Lapor selesai: ${r.done_by_name}${r.done_as ? ` (${r.done_as})` : ''} ${fmtTime(r.done_at)}`);
    if (r.acc_by_name) parts.push(`ACC: ${r.acc_by_name} ${fmtTime(r.acc_at)}`);
    if (r.status === 'ditolak') parts.push(`Ditolak: ${r.reject_by_name}${r.reject_as ? ` (${r.reject_as})` : ''} ${fmtTime(r.reject_at)} — ${r.reject_reason}`);
    if (r.extension_status && r.extension_status !== 'none') {
      const label = { pending: 'menunggu', approved: 'disetujui', rejected: 'ditolak' }[r.extension_status];
      parts.push(`Perpanjangan waktu: ${label} (oleh ${r.extension_requested_by_name})`);
    }
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
        {canExportExcel && (
          <a className="btn btn-primary btn-sm" href={excelUrl}>⬇ Unduh Excel Ringkasan</a>
        )}
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
        <h3>📋 Ringkasan per Kode Area</h3>
        <div className="table-wrap">
          <table className="loc-table">
            <thead>
              <tr>
                <th>Kode Area</th><th>Cabang</th><th>PJ Area</th><th>SPV Area</th>
                <th>Jumlah Temuan</th><th>Selesai Tepat Waktu</th><th>Tidak Selesai</th>
              </tr>
            </thead>
            <tbody>
              {summaryRows.map((s) => (
                <tr key={s.location_id}>
                  <td>{s.location_name}</td>
                  <td>{s.branch_name}</td>
                  <td>{s.pj_name}</td>
                  <td>{s.spv_name}</td>
                  <td>{s.total}</td>
                  <td>{s.selesai_tepat_waktu}</td>
                  <td>{s.tidak_selesai}</td>
                </tr>
              ))}
              {summaryRows.length === 0 && !busy && (
                <tr><td colSpan="7" style={{ color: 'var(--muted)' }}>Tidak ada data pada rentang ini.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="admin-section">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8, marginBottom: 8 }}>
          <h3 style={{ margin: 0 }}>📄 Detail Temuan</h3>
          {canExportExcel && (
            <a className="btn btn-outline btn-sm" href={detailExcelUrl}>⬇ Unduh Excel Detail</a>
          )}
        </div>
        <div className="table-wrap">
          <table className="loc-table report-table">
            <thead>
              <tr>
                <th>Tanggal</th><th>Jenis</th><th>Kode Area / Mitra</th><th>Cabang</th><th>Temuan</th>
                <th>Inspector</th><th>Status</th><th>Terlambat</th><th>Penanganan</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className={Number(r.is_deleted) ? 'inactive' : ''}>
                  <td style={{ whiteSpace: 'nowrap' }}>{fmtTime(r.created_at)}</td>
                  <td>{r.type_name || '-'}</td>
                  <td>{r.type_target_mode === 'individu' ? (r.subject_names || '-') : r.location_name}</td>
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
                  <td>
                    {r.status === 'ditolak' ? (
                      <span style={{ color: 'var(--muted)' }}>—</span>
                    ) : (
                      <span className={`badge ${r.is_late ? 'badge-telat' : 'badge-selesai'}`}>{r.is_late ? 'Telat' : 'Tepat waktu'}</span>
                    )}
                  </td>
                  <td style={{ fontSize: 13 }}>
                    {handling(r).map((p, i) => <div key={i}>{p}</div>)}
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !busy && (
                <tr><td colSpan="9" style={{ color: 'var(--muted)' }}>Tidak ada temuan pada rentang ini.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
