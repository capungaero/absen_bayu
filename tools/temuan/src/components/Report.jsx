import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { apiGet, API, getToken } from '../api.js';
import { STATUS_LABEL, fmtTime } from './Dashboard.jsx';
import PieChart from './PieChart.jsx';

const CHART_DIMENSIONS = [
  { key: 'byDivision', label: 'Divisi' },
  { key: 'byArea', label: 'Area (Kode Area)' },
  { key: 'byJenis', label: 'Jenis Temuan' },
];

const SORT_COLUMNS = [
  { key: 'location_name', label: 'Kode Area' },
  { key: 'pj_name', label: 'PJ Area' },
  { key: 'spv_name', label: 'Pengawas Area' },
  { key: 'total', label: 'Jumlah Temuan' },
];

const DETAIL_SORT_COLUMNS = [
  { key: 'created_at', label: 'Tanggal', className: 'nowrap' },
  { key: 'type_name', label: 'Jenis' },
  { key: 'area_mitra', label: 'Kode Area / Mitra' },
  { key: 'branch_name', label: 'Cabang', className: 'nowrap' },
  { key: 'reporter_name', label: 'Inspector' },
  { key: 'status', label: 'Status', className: 'nowrap' },
  { key: 'is_late', label: 'Terlambat', className: 'nowrap' },
];

const normalize = (s) => String(s || '').toLowerCase();

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
  const [chart, setChart] = useState(null);
  const [chartDim, setChartDim] = useState('byDivision');
  const [sortKey, setSortKey] = useState('total');
  const [sortDir, setSortDir] = useState('desc');
  const [detailSortKey, setDetailSortKey] = useState('created_at');
  const [detailSortDir, setDetailSortDir] = useState('desc');
  const [search, setSearch] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    apiGet('/branches').then((d) => setBranches(d.rows)).catch(() => {});
  }, []);

  const load = useCallback(async () => {
    setBusy(true);
    setError('');
    try {
      const [data, sum, ch] = await Promise.all([
        apiGet('/report', { from, to, branch_id: branchId }),
        apiGet('/report_summary', { from, to, branch_id: branchId }),
        apiGet('/report_chart', { from, to, branch_id: branchId }),
      ]);
      setRows(data.rows);
      setSummary(data.summary);
      setSummaryRows(sum.rows);
      setChart(ch.chart);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }, [from, to, branchId, onSessionEnd]);

  useEffect(() => { load(); }, [load]);

  const q = normalize(search).trim();

  const sortedSummaryRows = useMemo(() => {
    let list = summaryRows;
    if (q) {
      list = list.filter((s) =>
        normalize(s.location_name).includes(q) ||
        normalize(s.pj_name).includes(q) ||
        normalize(s.spv_name).includes(q) ||
        normalize(s.branch_name).includes(q)
      );
    }
    list = [...list];
    list.sort((a, b) => {
      const av = a[sortKey], bv = b[sortKey];
      let cmp;
      if (sortKey === 'total') {
        cmp = (Number(av) || 0) - (Number(bv) || 0);
      } else {
        cmp = String(av || '').localeCompare(String(bv || ''), 'id', { sensitivity: 'base' });
      }
      return sortDir === 'asc' ? cmp : -cmp;
    });
    return list;
  }, [summaryRows, sortKey, sortDir, q]);

  const toggleSort = (key) => {
    if (key === sortKey) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortKey(key);
      setSortDir(key === 'total' ? 'desc' : 'asc');
    }
  };

  const toggleDetailSort = (key) => {
    if (key === detailSortKey) {
      setDetailSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setDetailSortKey(key);
      setDetailSortDir(key === 'created_at' ? 'desc' : 'asc');
    }
  };

  const areaOrMitra = (r) => (r.type_target_mode === 'individu' ? (r.subject_names || '-') : (r.location_name || '-'));

  const filteredDetailRows = useMemo(() => {
    let list = rows;
    if (q) {
      list = list.filter((r) =>
        normalize(r.type_name).includes(q) ||
        normalize(areaOrMitra(r)).includes(q) ||
        normalize(r.branch_name).includes(q) ||
        normalize(r.description).includes(q) ||
        normalize(r.reporter_name).includes(q) ||
        normalize(STATUS_LABEL[r.status] || r.status).includes(q)
      );
    }
    list = [...list];
    list.sort((a, b) => {
      let av, bv;
      if (detailSortKey === 'area_mitra') { av = areaOrMitra(a); bv = areaOrMitra(b); }
      else if (detailSortKey === 'status') { av = STATUS_LABEL[a.status] || a.status; bv = STATUS_LABEL[b.status] || b.status; }
      else { av = a[detailSortKey]; bv = b[detailSortKey]; }
      let cmp;
      if (detailSortKey === 'created_at') {
        cmp = new Date(av || 0) - new Date(bv || 0);
      } else if (detailSortKey === 'is_late') {
        cmp = (av ? 1 : 0) - (bv ? 1 : 0);
      } else {
        cmp = String(av || '').localeCompare(String(bv || ''), 'id', { sensitivity: 'base' });
      }
      return detailSortDir === 'asc' ? cmp : -cmp;
    });
    return list;
  }, [rows, detailSortKey, detailSortDir, q]);

  const canExportExcel = me.is_admin || me.is_inspector;
  const qs = `from=${from}&to=${to}${branchId ? `&branch_id=${branchId}` : ''}&token=${encodeURIComponent(getToken())}`;
  const excelUrl = `${API}/report_excel?${qs}`;
  const detailExcelUrl = `${API}/report_detail_excel?${qs}`;

  const handling = (r) => {
    const parts = [];
    if (r.taken_by_name) parts.push(`Dikerjakan: ${r.taken_by_name}${r.taken_as ? ` (${r.taken_as})` : ''} ${fmtTime(r.taken_at)}`);
    if (r.done_by_name) parts.push(`Lapor selesai: ${r.done_by_name}${r.done_as ? ` (${r.done_as})` : ''} ${fmtTime(r.done_at)}`);
    if (r.acc_by_name) parts.push(`ACC: ${r.acc_by_name} ${fmtTime(r.acc_at)}`);
    if (r.status === 'menunggu_acc_tolak') parts.push(`Pengajuan tolak (menunggu ACC): ${r.reject_by_name}${r.reject_as ? ` (${r.reject_as})` : ''} ${fmtTime(r.reject_at)} — ${r.reject_reason}`);
    if (r.status === 'ditolak') parts.push(`Ditolak: ${r.reject_by_name}${r.reject_as ? ` (${r.reject_as})` : ''} ${fmtTime(r.reject_at)} — ${r.reject_reason}${r.reject_decided_by_name ? ` (ACC: ${r.reject_decided_by_name})` : ''}`);
    if (r.reject_decision === 'denied' && r.status !== 'ditolak') parts.push(`Pengajuan tolak TIDAK disetujui oleh ${r.reject_decided_by_name} ${fmtTime(r.reject_decided_at)}${r.reject_decision_note ? ` — ${r.reject_decision_note}` : ''}`);
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

      <div className="filter-row">
        <input
          type="search"
          placeholder="🔎 Cari kode area, mitra, PJ, Pengawas, jenis, keterangan..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="date-input"
          style={{ flex: 1, minWidth: 240 }}
        />
        {search && (
          <button className="btn btn-outline btn-sm" onClick={() => setSearch('')}>✕ Bersihkan</button>
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
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8, marginBottom: 16 }}>
          <h3 style={{ margin: 0 }}>🥧 Distribusi Temuan</h3>
          <div className="segmented">
            {CHART_DIMENSIONS.map((d) => (
              <button
                key={d.key}
                className={chartDim === d.key ? 'active' : ''}
                onClick={() => setChartDim(d.key)}
              >
                {d.label}
              </button>
            ))}
          </div>
        </div>
        <PieChart data={chart ? chart[chartDim] : []} />
      </div>

      <div className="admin-section">
        <h3>📋 Ringkasan per Kode Area</h3>
        <div className="table-wrap">
          <table className="loc-table">
            <thead>
              <tr>
                {SORT_COLUMNS.map((c) => (
                  <th
                    key={c.key}
                    className={`sortable${c.key === 'total' ? ' num' : ''}`}
                    onClick={() => toggleSort(c.key)}
                  >
                    {c.label}{sortKey === c.key && <span className="arrow">{sortDir === 'asc' ? '▲' : '▼'}</span>}
                  </th>
                ))}
                <th>Cabang</th><th className="num">Selesai Tepat Waktu</th><th className="num">Tidak Selesai</th>
              </tr>
            </thead>
            <tbody>
              {sortedSummaryRows.map((s) => (
                <tr key={s.location_id}>
                  <td className="cell-primary">{s.location_name}</td>
                  <td>{s.pj_name}</td>
                  <td>{s.spv_name}</td>
                  <td className="num">{s.total}</td>
                  <td className="cell-muted nowrap">{s.branch_name}</td>
                  <td className="num" style={{ color: 'var(--green)', fontWeight: 600 }}>{s.selesai_tepat_waktu}</td>
                  <td className="num" style={{ color: s.tidak_selesai > 0 ? 'var(--red)' : 'var(--muted)', fontWeight: 600 }}>{s.tidak_selesai}</td>
                </tr>
              ))}
              {sortedSummaryRows.length === 0 && !busy && (
                <tr><td colSpan="7" style={{ color: 'var(--muted)' }}>{q ? 'Tidak ada yang cocok dengan pencarian.' : 'Tidak ada data pada rentang ini.'}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="admin-section">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
          <h3 style={{ margin: 0 }}>📄 Detail Temuan</h3>
          {canExportExcel && (
            <a className="btn btn-outline btn-sm" href={detailExcelUrl}>⬇ Unduh Excel Detail</a>
          )}
        </div>
        <div className="table-wrap">
          <table className="loc-table report-table">
            <thead>
              <tr>
                {DETAIL_SORT_COLUMNS.map((c) => (
                  <th
                    key={c.key}
                    className={`sortable${c.className ? ' ' + c.className : ''}`}
                    onClick={() => toggleDetailSort(c.key)}
                  >
                    {c.label}{detailSortKey === c.key && <span className="arrow">{detailSortDir === 'asc' ? '▲' : '▼'}</span>}
                  </th>
                ))}
                <th>Temuan</th><th>Penanganan</th>
              </tr>
            </thead>
            <tbody>
              {filteredDetailRows.map((r) => (
                <tr key={r.id} className={Number(r.is_deleted) ? 'inactive' : ''}>
                  <td style={{ whiteSpace: 'nowrap' }}>{fmtTime(r.created_at)}</td>
                  <td>{r.type_name || '-'}</td>
                  <td>{areaOrMitra(r)}</td>
                  <td>{r.branch_name}</td>
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
                  <td>
                    {r.description}
                    <div style={{ display: 'flex', gap: 6, marginTop: 4 }}>
                      {r.photo_url && <a href={r.photo_url} target="_blank" rel="noreferrer">📎 foto temuan</a>}
                      {r.done_photo_url && <a href={r.done_photo_url} target="_blank" rel="noreferrer">📎 foto pengerjaan</a>}
                    </div>
                  </td>
                  <td style={{ fontSize: 13 }}>
                    {handling(r).map((p, i) => <div key={i}>{p}</div>)}
                  </td>
                </tr>
              ))}
              {filteredDetailRows.length === 0 && !busy && (
                <tr><td colSpan="9" style={{ color: 'var(--muted)' }}>{q ? 'Tidak ada yang cocok dengan pencarian.' : 'Tidak ada temuan pada rentang ini.'}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
