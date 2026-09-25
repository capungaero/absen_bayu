import React, { useCallback, useEffect, useState } from 'react';
import { apiGet } from '../api.js';
import { STATUS_LABEL, fmtTime } from './Dashboard.jsx';

const STATUS_META = {
  baik: { label: '✅ BAIK', desc: 'Semua tertangani tepat waktu', cls: 's-selesai' },
  cukup: { label: '🟡 CUKUP', desc: 'Sebagian kecil terlambat/belum dieksekusi', cls: 's-dikerjakan' },
  perlu_perhatian: { label: '🔴 PERLU PERHATIAN', desc: 'Banyak yang terlambat/belum dieksekusi', cls: 's-baru' },
  tidak_ada: { label: '⚪ TIDAK ADA TEMUAN', desc: 'Tidak ada temuan pada tanggal ini', cls: 's-ditolak' },
};

const SOURCE_LABEL = {
  live: '🔴 Live — terus diperbarui sampai snapshot jam 22:00',
  snapshot: '📦 Tersimpan — snapshot resmi jam 22:00',
  live_fallback: '⚠️ Dihitung langsung (belum pernah di-snapshot pada tanggal ini)',
};

function fmtDate(d) {
  const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  const dt = new Date(d + 'T12:00:00');
  return `${days[dt.getDay()]}, ${dt.getDate()} ${months[dt.getMonth()]} ${dt.getFullYear()}`;
}

export default function DailyReport({ onSessionEnd }) {
  const today = new Date().toISOString().slice(0, 10);
  const [date, setDate] = useState(today);
  const [report, setReport] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async (d) => {
    setBusy(true);
    setError('');
    try {
      const data = await apiGet('/daily_report', { date: d });
      setReport(data.report);
    } catch (err) {
      if (err.auth) return onSessionEnd();
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }, [onSessionEnd]);

  useEffect(() => { load(date); }, [date, load]);

  const status = report ? (STATUS_META[report.overall_status] || { label: report.overall_status, desc: '', cls: '' }) : null;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8, marginBottom: 14 }}>
        <h2 style={{ fontSize: 18 }}>📋 Daily Report Temuan &amp; Inspeksi</h2>
        <div className="filter-row" style={{ marginBottom: 0 }}>
          <input type="date" value={date} max={today} onChange={(e) => setDate(e.target.value)} className="date-input" />
          <button className="btn btn-outline btn-sm" onClick={() => load(date)} disabled={busy}>↻ Muat ulang</button>
        </div>
      </div>

      {error && <div className="alert alert-error">{error}</div>}
      {!report && !error && <div className="empty">Memuat…</div>}

      {report && (
        <>
          <div className="admin-section">
            <h3 style={{ marginBottom: 4 }}>{fmtDate(report.date)}</h3>
            <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 14 }}>{SOURCE_LABEL[report.source] || ''}</div>

            <div style={{ marginBottom: 14 }}>
              <div style={{ fontSize: 12, color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '0.04em', fontWeight: 600 }}>
                Total Temuan Baru Hari Ini
              </div>
              <div style={{ fontSize: 40, fontWeight: 700, lineHeight: 1.2 }}>{report.total}</div>
            </div>

            <div className="summary-row">
              <div className="summary-card s-selesai">
                <div className="num">{report.executed_count}</div>
                <div className="lbl">Sudah Dieksekusi</div>
              </div>
              <div className="summary-card s-selesai">
                <div className="num">{report.on_time_count}</div>
                <div className="lbl">Selesai Tepat Waktu</div>
              </div>
              <div className="summary-card s-baru">
                <div className="num">{report.late_count}</div>
                <div className="lbl">Terlambat Dieksekusi</div>
              </div>
              <div className="summary-card s-dikerjakan">
                <div className="num">{report.not_executed_count}</div>
                <div className="lbl">Belum/Tidak Dieksekusi</div>
              </div>
            </div>

            <div className={`badge ${status.cls}`} style={{ fontSize: 13, padding: '6px 12px' }}>
              {status.label}
            </div>
            {status.desc && <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>{status.desc}</div>}
          </div>

          <div className="admin-section">
            <h3>🔄 Aktivitas Hari Ini <span style={{ fontWeight: 400, fontSize: 12, color: 'var(--muted)' }}>(termasuk temuan hari sebelumnya yang baru bergerak hari ini)</span></h3>
            <div className="summary-row">
              <div className="summary-card s-dikerjakan">
                <div className="num">{report.activity?.started_count ?? 0}</div>
                <div className="lbl">Mulai Dikerjakan</div>
              </div>
              <div className="summary-card s-dikerjakan">
                <div className="num">{report.activity?.reported_done_count ?? 0}</div>
                <div className="lbl">Lapor Selesai</div>
              </div>
              <div className="summary-card s-selesai">
                <div className="num">{report.activity?.closed_count ?? 0}</div>
                <div className="lbl">Ditutup/ACC ({report.activity?.closed_on_time_count ?? 0} tepat waktu, {report.activity?.closed_late_count ?? 0} telat)</div>
              </div>
              <div className="summary-card s-baru">
                <div className="num">{report.activity?.carried_over_count ?? 0}</div>
                <div className="lbl">Dari Temuan Hari Sebelumnya</div>
              </div>
            </div>
          </div>

          <div className="admin-section">
            <h3>📊 Rekap Temuan Terbuka <span style={{ fontWeight: 400, fontSize: 12, color: 'var(--muted)' }}>(semua tanggal, live)</span></h3>
            <div className="summary-row">
              <div className="summary-card s-dikerjakan">
                <div className="num">{report.backlog?.total_open ?? 0}</div>
                <div className="lbl">Total Masih Terbuka</div>
              </div>
              <div className="summary-card s-dikerjakan">
                <div className="num">{report.backlog?.not_started ?? 0}</div>
                <div className="lbl">Belum Diambil</div>
              </div>
              <div className="summary-card s-dikerjakan">
                <div className="num">{report.backlog?.in_progress ?? 0}</div>
                <div className="lbl">Sedang Berjalan</div>
              </div>
              <div className="summary-card s-baru">
                <div className="num">{report.backlog?.overdue ?? 0}</div>
                <div className="lbl">Sudah Lewat Deadline</div>
              </div>
            </div>
            {!!report.backlog?.oldest_open_days && (
              <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 8 }}>
                Temuan terbuka paling lama: {report.backlog.oldest_open_days} hari
              </div>
            )}
          </div>

          <div className="admin-section">
            <h3>📂 Jumlah Temuan per Kategori/Jenis</h3>
            <div className="table-wrap">
              <table className="loc-table">
                <thead>
                  <tr><th>Kategori / Jenis Temuan</th><th>Jumlah</th></tr>
                </thead>
                <tbody>
                  {report.categories.map((c) => (
                    <tr key={c.name}>
                      <td>{c.name}</td>
                      <td>{c.total}</td>
                    </tr>
                  ))}
                  {report.categories.length === 0 && (
                    <tr><td colSpan="2" style={{ color: 'var(--muted)' }}>Tidak ada temuan pada tanggal ini.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          <div className="admin-section">
            <h3>📄 Detail Temuan</h3>
            <div className="table-wrap">
              <table className="loc-table report-table">
                <thead>
                  <tr>
                    <th>Waktu</th><th>Jenis</th><th>Kode Area / Mitra</th><th>Cabang</th>
                    <th>Temuan</th><th>Pelapor</th><th>Status</th><th>Penanganan</th>
                  </tr>
                </thead>
                <tbody>
                  {(report.details || []).map((r) => (
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
                      <td style={{ fontSize: 13 }}>
                        {r.taken_by_name && <div>Dikerjakan: {r.taken_by_name} {fmtTime(r.taken_at)}</div>}
                        {r.done_by_name && <div>Lapor selesai: {r.done_by_name} {fmtTime(r.done_at)}</div>}
                        {r.acc_by_name && <div>ACC: {r.acc_by_name} {fmtTime(r.acc_at)}</div>}
                        {r.status === 'ditolak' && <div>Ditolak: {r.reject_by_name} — {r.reject_reason}</div>}
                        {!r.taken_by_name && !r.done_by_name && !r.acc_by_name && r.status !== 'ditolak' && '—'}
                      </td>
                    </tr>
                  ))}
                  {(report.details || []).length === 0 && (
                    <tr><td colSpan="8" style={{ color: 'var(--muted)' }}>Tidak ada temuan pada tanggal ini.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
