import { useState, useEffect, useCallback, useRef } from 'react';

const API = import.meta.env.VITE_API_BASE ?? '/absen/dat_reader';
const FIELDS = ['datang', 'out_ist', 'in_ist', 'pulang'];

export default function App() {
  const [theme, setTheme] = useState(() => localStorage.getItem('dr-theme') || 'light');
  const [branches, setBranches] = useState([]);
  const [branchId, setBranchId] = useState(null);
  const [period, setPeriod] = useState(null);
  const [machines, setMachines] = useState([]);
  const [machineSn, setMachineSn] = useState('');

  const [mode, setMode] = useState('period');     // period | range | date
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const [rows, setRows] = useState([]);
  const [recap, setRecap] = useState(null);
  const [dirty, setDirty] = useState(() => new Set());
  const [loaded, setLoaded] = useState(false);

  const [busy, setBusy] = useState('');           // '', upload, cloud, load, save, push
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const fileRef = useRef(null);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('dr-theme', theme);
  }, [theme]);

  useEffect(() => {
    fetch(`${API}/attendance_machines`, { credentials: 'include' })
      .then(r => r.json())
      .then(rows => { if (Array.isArray(rows)) setMachines(rows); })
      .catch(() => {});
  }, []);

  useEffect(() => {
    fetch(`${API}/branches`, { credentials: 'include' })
      .then(r => r.json())
      .then(rows => {
        if (!Array.isArray(rows)) { setError('Gagal memuat cabang. Pastikan sudah login di aplikasi absensi.'); return; }
        setBranches(rows); if (rows[0]) setBranchId(Number(rows[0].id));
      })
      .catch(() => setError('Gagal memuat cabang. Pastikan sudah login di aplikasi absensi.'));
  }, []);

  const loadPeriod = useCallback(() => {
    if (!branchId) return;
    fetch(`${API}/period?branch_id=${branchId}`, { credentials: 'include' })
      .then(r => r.json())
      .then(d => {
        if (d && !d.error) {
          setPeriod(d);
          if (!from) setFrom(d.from);
          if (!to) setTo(d.to);
        }
      })
      .catch(() => {});
  }, [branchId]); // eslint-disable-line
  useEffect(() => { loadPeriod(); }, [loadPeriod]);

  // Parameter rentang efektif yang dikirim ke server
  const rangeParams = () => {
    if (mode === 'period') return { mode: 'period' };
    if (mode === 'date')   return { mode: 'date', from, to: from };
    return { mode: 'range', from, to };
  };
  const rangeLabel = () => {
    if (mode === 'period') return period ? `${period.from} s/d ${period.to}` : 'periode berjalan';
    if (mode === 'date')   return from || '—';
    return `${from || '—'} s/d ${to || '—'}`;
  };

  const applyResp = (d, msg) => {
    setRows(d.rows || []);
    setRecap(d.recap || null);
    setDirty(new Set());
    setLoaded(true);
    if (msg) { setNotice(msg); setTimeout(() => setNotice(''), 6000); }
  };

  const doLoad = async () => {
    if (!branchId) return;
    setError(''); setBusy('load');
    try {
      const p = new URLSearchParams({ branch_id: branchId, ...rangeParams() });
      const r = await fetch(`${API}/data?${p.toString()}`, { credentials: 'include' });
      const d = await r.json();
      if (d.error) { setError(d.error); return; }
      applyResp(d, `Memuat ${d.rows.length} baris (${d.from} s/d ${d.to}).`);
    } catch (e) { setError('Gagal memuat data.'); }
    finally { setBusy(''); }
  };

  const doUpload = async (file) => {
    if (!file || !branchId) return;
    if (!machineSn) { setError('Pilih mesin absensi sumber file dulu.'); if (fileRef.current) fileRef.current.value = ''; return; }
    setError(''); setBusy('upload');
    try {
      const fd = new FormData();
      fd.append('file', file); fd.append('branch_id', branchId); fd.append('machine_sn', machineSn);
      const rp = rangeParams();
      Object.entries(rp).forEach(([k, v]) => fd.append(k, v));
      const r = await fetch(`${API}/sync_upload`, { method: 'POST', body: fd, credentials: 'include' });
      const d = await r.json();
      if (d.error) { setError(d.error); return; }
      applyResp(d, syncMsg(d));
    } catch (e) { setError('Gagal membaca file .dat.'); }
    finally { setBusy(''); if (fileRef.current) fileRef.current.value = ''; }
  };

  const doCloud = async () => {
    if (!branchId) return;
    setError(''); setBusy('cloud');
    try {
      const r = await fetch(`${API}/sync_cloud`, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ branch_id: branchId, ...rangeParams() }),
      });
      const d = await r.json();
      if (d.error) { setError(d.error + (d.failed?.length ? ` (mesin gagal: ${d.failed.join(', ')})` : '')); return; }
      applyResp(d, syncMsg(d));
    } catch (e) { setError('Gagal menarik data dari cloud.'); }
    finally { setBusy(''); }
  };

  const syncMsg = (d) => {
    const s = d.sync || {};
    let m = `Sync ${d.source}: ${s.synced || 0} baris mesin diproses`;
    if (s.missing) m += `, ${s.missing} tap tak dikenal`;
    if (d.failed?.length) m += `, mesin gagal: ${d.failed.join(', ')}`;
    return m + '.';
  };

  const onCell = (key, field, value) => {
    setRows(rs => rs.map(r => r.key === key ? { ...r, [field]: value } : r));
    setDirty(d => { const n = new Set(d); n.add(key); return n; });
  };

  const doSave = async () => {
    const edits = rows.filter(r => dirty.has(r.key)).map(r => ({
      user_id: r.user_id, flow_date: r.date,
      datang: r.datang, out_ist: r.out_ist, in_ist: r.in_ist, pulang: r.pulang,
    }));
    if (!edits.length) return;
    setError(''); setBusy('save');
    try {
      const r = await fetch(`${API}/save`, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ branch_id: branchId, ...rangeParams(), edits }),
      });
      const d = await r.json();
      if (d.error) { setError(d.error); return; }
      applyResp(d, `${d.saved} baris perubahan disimpan ke data kerja.`);
    } catch (e) { setError('Gagal menyimpan perubahan.'); }
    finally { setBusy(''); }
  };

  const doPush = async () => {
    setError(''); setBusy('push');
    try {
      const r = await fetch(`${API}/push`, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ branch_id: branchId, ...rangeParams() }),
      });
      const d = await r.json();
      if (d.error) { setError(d.error); return; }
      const p = d.push || {};
      applyResp(d, `Dorong ke presensi: ${p.inserted} ditambahkan, ${p.skipped_existing} sudah ada, ${p.skipped_locked} terkunci dilewati.`);
    } catch (e) { setError('Gagal mendorong ke presence.'); }
    finally { setBusy(''); }
  };

  const dirtyCount = dirty.size;
  const locked = period?.locked && mode === 'period';
  const canAct = !!branchId && !busy;

  return (
    <div className="app">
      <header className="topbar">
        <div className="brand">
          <span className="logo">🕔</span>
          <div>
            <div className="title">DAT Reader</div>
            <div className="subtitle">Baca .dat Mesin → Absen Harian (mirror + kerja)</div>
          </div>
        </div>
        <button className="btn-theme" onClick={() => setTheme(t => t === 'light' ? 'dark' : 'light')}>
          {theme === 'light' ? '🌙' : '☀️'}
        </button>
      </header>

      {/* Filter + aksi sync */}
      <div className="toolbar">
        <div className="tb-group">
          <span className="tb-label">Cabang</span>
          <select className="tb-select" value={branchId || ''} onChange={e => { setBranchId(Number(e.target.value)); setRows([]); setLoaded(false); }}>
            {branches.map(b => <option key={b.id} value={b.id}>{b.branch_name}</option>)}
          </select>
        </div>

        <div className="tb-sep" />

        <div className="tb-group">
          <span className="tb-label">Rentang</span>
          <div className="seg">
            {[['period', 'Periode Fee'], ['range', 'Rentang Tgl'], ['date', 'Per Tanggal']].map(([v, l]) => (
              <button key={v} className={`seg-btn ${mode === v ? 'active' : ''}`} onClick={() => setMode(v)}>{l}</button>
            ))}
          </div>
          {mode === 'period' && (
            <span className="range-info">{period ? `${period.from} → ${period.to}` : '…'}</span>
          )}
          {mode === 'range' && (
            <span className="range-inputs">
              <input type="date" value={from} onChange={e => setFrom(e.target.value)} />
              <span>→</span>
              <input type="date" value={to} onChange={e => setTo(e.target.value)} />
            </span>
          )}
          {mode === 'date' && (
            <input type="date" value={from} onChange={e => setFrom(e.target.value)} />
          )}
        </div>

        <div className="toolbar-actions">
          <button className="btn-ghost" disabled={!canAct} onClick={doLoad}>
            {busy === 'load' ? 'Memuat…' : '↻ Muat Data'}
          </button>
          <select
            className="tb-select"
            value={machineSn}
            onChange={e => setMachineSn(e.target.value)}
            disabled={!canAct}
            title="Wajib pilih mesin absensi sumber file sebelum upload -- cegah salah upload dump mesin sholat"
          >
            <option value="">Pilih mesin absensi…</option>
            {machines.map(m => <option key={m.sn} value={m.sn}>{m.name} ({m.sn})</option>)}
          </select>
          <button className="btn-ghost" disabled={!canAct || !machineSn} onClick={() => fileRef.current?.click()}>
            {busy === 'upload' ? 'Membaca…' : '📄 Sync Upload .dat'}
          </button>
          <button className="btn-ghost" disabled={!canAct} onClick={doCloud}>
            {busy === 'cloud' ? 'Menarik…' : '☁️ Sync Cloud'}
          </button>
          <input ref={fileRef} type="file" accept=".dat,.txt" hidden onChange={e => doUpload(e.target.files[0])} />
        </div>
      </div>

      {error && <div className="banner banner-error">{error}</div>}
      {notice && <div className="banner banner-info">{notice}</div>}
      {locked && (
        <div className="banner banner-warn">
          🔒 Periode <b>{String(period.month).padStart(2, '0')}/{period.year}</b> sudah dikunci.
          Baris di periode terkunci akan dilewati saat "Dorong ke Presensi".
        </div>
      )}

      {/* Rekap */}
      {recap && (
        <div className="recap">
          <Stat label="Mitra Kerja" value={recap.karyawan} />
          <Stat label="Hari-Absen" value={recap.hari_absen} />
          <Stat label="Total Tap" value={recap.total_tap} />
          <Stat label="Hadir Lengkap" value={recap.hadir_lengkap} cls="ok" />
          <Stat label="Tidak Lengkap" value={recap.tidak_lengkap} cls={recap.tidak_lengkap ? 'warn' : ''} />
          <Stat label="Diedit" value={recap.diedit} cls={recap.diedit ? 'edit' : ''} />
          <Stat label="Sudah di Presence" value={recap.sudah_di_presence} />
          <Stat label="Sudah Didorong" value={recap.sudah_didorong} />
        </div>
      )}

      {/* Tabel data kerja */}
      {loaded && (
        rows.length ? (
          <div className="table-wrap">
            <table className="grid">
              <thead>
                <tr>
                  <th>Tanggal</th><th>Hari</th><th>ID</th><th>Nama</th>
                  <th>Datang</th><th>Out Ist.</th><th>In Ist.</th><th>Pulang</th>
                  <th className="c-tap">Tap</th><th>Status</th>
                </tr>
              </thead>
              <tbody>
                {rows.map(r => (
                  <tr key={r.key} className={r.is_edited ? 'row-edit' : ''}>
                    <td>{r.date}</td>
                    <td>{r.weekday}</td>
                    <td className="mono">{r.employee_code}</td>
                    <td className="nm" title={r.source ? `sumber: ${r.source}` : ''}>{r.employee_name}</td>
                    {FIELDS.map(f => (
                      <td key={f} className={r[f] !== r.mirror[f] ? 'cell-diff' : ''}>
                        <input type="time" className="t-in" value={r[f] || ''}
                          title={r.mirror[f] ? `mesin: ${r.mirror[f]}` : 'tidak ada di mesin'}
                          onChange={e => onCell(r.key, f, e.target.value)} />
                      </td>
                    ))}
                    <td className="c-tap" title={r.all_taps}>{r.tap_count}</td>
                    <td className="st">
                      {r.is_edited ? <span className="badge badge-edit">diedit</span> : null}
                      {r.in_presence ? <span className="badge badge-pres">di presence</span> : null}
                      {!r.is_edited && !r.in_presence ? <span className="badge badge-mirror">mirror</span> : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="empty-note">Tidak ada data kerja pada rentang ini. Klik <b>Sync Upload</b> / <b>Sync Cloud</b> untuk mengisi dari mesin, atau ganti rentang.</div>
        )
      )}
      {!loaded && !error && (
        <div className="empty-note">Pilih rentang lalu klik <b>Muat Data</b> (lihat data tersimpan) atau <b>Sync</b> (baca dari mesin).</div>
      )}

      {/* Action bar */}
      {loaded && rows.length > 0 && (
        <footer className="actionbar">
          <div className="status">
            <b>{dirtyCount}</b> baris diubah · rentang: <b>{rangeLabel()}</b>
          </div>
          <div className="action-buttons">
            <button className="btn-secondary" disabled={!dirtyCount || busy === 'save'} onClick={doSave}>
              {busy === 'save' ? 'Menyimpan…' : `💾 Simpan Perubahan (${dirtyCount})`}
            </button>
            <button className="btn-primary" disabled={busy === 'push'} onClick={doPush}>
              {busy === 'push' ? 'Mendorong…' : '➡️ Dorong ke Presensi'}
            </button>
          </div>
        </footer>
      )}
    </div>
  );
}

function Stat({ label, value, cls = '' }) {
  return (
    <div className={`stat ${cls}`}>
      <div className="stat-val">{value}</div>
      <div className="stat-lbl">{label}</div>
    </div>
  );
}
