import { useState, useEffect, useCallback, useRef } from 'react';

const API = import.meta.env.VITE_API_BASE ?? '/absen/dat_reader';

// Kolom jam efektif kerja. Nilai mesin ada di r.machine[<field>] untuk pembanding.
const FIELDS_ATTENDANCE = [
  ['entry_time', 'Datang'],
  ['rest_in',    'Out Ist.'],
  ['rest_out',   'In Ist.'],
  ['out_time',   'Pulang'],
];

// Kolom jam sholat: 6 slot x masuk/keluar.
const PRAYER_LABELS = { subuh: 'Subuh', dzuhur: 'Dzuhur', ashar: 'Ashar', maghrib: 'Maghrib', isha: 'Isha', friday: 'Jumat' };
const PRAYERS = Object.keys(PRAYER_LABELS);
const FIELDS_PRAY = PRAYERS.flatMap(p => [
  [`${p}_in`,  `${PRAYER_LABELS[p]} Masuk`],
  [`${p}_out`, `${PRAYER_LABELS[p]} Keluar`],
]);

export default function App() {
  const [theme, setTheme] = useState(() => localStorage.getItem('dr-theme') || 'light');
  const [branches, setBranches] = useState([]);
  const [branchId, setBranchId] = useState(null);

  // Jenis Mesin: 'attendance' (jalur kerja) | 'pray' (jalur sholat) -- backend
  // benar-benar terpisah (attendance_day vs pray_day), cuma disatukan di UI.
  const [kind, setKind] = useState('attendance');
  const [attMachines, setAttMachines] = useState([]);
  const [prayMachines, setPrayMachines] = useState([]);
  const [uploadSn, setUploadSn] = useState('');
  const [period, setPeriod] = useState(null);

  const [mode, setMode] = useState('period');     // period | range | date
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const [rows, setRows] = useState([]);
  const [recap, setRecap] = useState(null);
  const [dirty, setDirty] = useState(() => new Set());
  const [loaded, setLoaded] = useState(false);

  const [openKey, setOpenKey] = useState(null);   // baris yang rincian tap-nya dibuka
  const [taps, setTaps] = useState([]);
  const [tapBusy, setTapBusy] = useState(false);
  const [addTime, setAddTime] = useState('');
  const [addNote, setAddNote] = useState('');

  const [busy, setBusy] = useState('');           // '', upload, cloud, load, save, push, reclass
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const fileRef = useRef(null);

  const isPray = kind === 'pray';
  const FIELDS = isPray ? FIELDS_PRAY : FIELDS_ATTENDANCE;
  const machines = isPray ? prayMachines : attMachines;
  const ep = (name) => isPray ? `${name}_pray` : name; // nama endpoint per jenis mesin

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('dr-theme', theme);
  }, [theme]);

  useEffect(() => {
    fetch(`${API}/branches`, { credentials: 'include' })
      .then(r => r.json())
      .then(rows => {
        if (!Array.isArray(rows)) { setError('Gagal memuat cabang. Pastikan sudah login di aplikasi absensi.'); return; }
        setBranches(rows); if (rows[0]) setBranchId(Number(rows[0].id));
      })
      .catch(() => setError('Gagal memuat cabang. Pastikan sudah login di aplikasi absensi.'));
  }, []);

  // Sumber upload wajib dipilih eksplisit per jenis mesin: dump mesin sholat
  // yang keliru diunggah sebagai jam kerja (atau sebaliknya) pernah mencemari
  // data (lihat investigasi 17 Agu 2026).
  useEffect(() => {
    fetch(`${API}/attendance_machines`, { credentials: 'include' })
      .then(r => r.json())
      .then(rows => { if (Array.isArray(rows)) setAttMachines(rows); })
      .catch(() => {});
    fetch(`${API}/sholat_machines`, { credentials: 'include' })
      .then(r => r.json())
      .then(rows => { if (Array.isArray(rows)) setPrayMachines(rows); })
      .catch(() => {});
  }, []);

  useEffect(() => {
    setUploadSn(machines[0]?.sn || '');
  }, [kind, attMachines, prayMachines]); // eslint-disable-line

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
    setOpenKey(null);
    if (msg) { setNotice(msg); setTimeout(() => setNotice(''), 9000); }
  };

  const post = async (path, body) => {
    const r = await fetch(`${API}/${path}`, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return r.json();
  };

  const switchKind = (k) => {
    if (k === kind) return;
    setKind(k); setRows([]); setLoaded(false); setDirty(new Set()); setOpenKey(null); setError('');
  };

  const doLoad = async () => {
    if (!branchId) return;
    setError(''); setBusy('load');
    try {
      const p = new URLSearchParams({ branch_id: branchId, ...rangeParams() });
      const r = await fetch(`${API}/${ep('data')}?${p.toString()}`, { credentials: 'include' });
      const d = await r.json();
      if (d.error) { setError(d.error); return; }
      applyResp(d, `Memuat ${d.rows.length} baris (${d.from} s/d ${d.to}).`);
    } catch (e) { setError('Gagal memuat data.'); }
    finally { setBusy(''); }
  };

  const doUpload = async (file) => {
    if (!file || !branchId) return;
    setError(''); setBusy('upload');
    try {
      const fd = new FormData();
      fd.append('file', file); fd.append('branch_id', branchId); fd.append('machine_sn', uploadSn);
      Object.entries(rangeParams()).forEach(([k, v]) => fd.append(k, v));
      const r = await fetch(`${API}/${ep('sync_upload')}`, { method: 'POST', body: fd, credentials: 'include' });
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
      const d = await post(ep('sync_cloud'), { branch_id: branchId, ...rangeParams() });
      if (d.error) { setError(d.error + (d.failed?.length ? ` (mesin gagal: ${d.failed.join(', ')})` : '')); return; }
      applyResp(d, syncMsg(d));
    } catch (e) { setError('Gagal menarik data dari cloud.'); }
    finally { setBusy(''); }
  };

  const doReclass = async () => {
    if (!branchId) return;
    setError(''); setBusy('reclass');
    try {
      const d = await post(ep('reclassify'), { branch_id: branchId, ...rangeParams() });
      if (d.error) { setError(d.error); return; }
      applyResp(d, classifyMsg(d.classify));
    } catch (e) { setError('Gagal mengklasifikasi ulang.'); }
    finally { setBusy(''); }
  };

  const classifyMsg = (c) => {
    if (!c) return 'Selesai.';
    let m = `Klasifikasi ulang: ${c.days} hari (window ${c.window}`;
    if (c.positional) m += `, tanpa jadwal ${c.positional}`;
    if (c.empty) m += `, tanpa tap ${c.empty}`;
    m += ')';
    if (c.skipped_edited) m += `, ${c.skipped_edited} koreksi admin dipertahankan`;
    return m + '.';
  };

  const syncMsg = (d) => {
    const parts = [];
    if (d.new_taps !== undefined) parts.push(`${d.new_taps} tap baru masuk arsip`);
    if (d.ingest) Object.entries(d.ingest).forEach(([sn, msg]) => parts.push(`${sn} → ${msg}`));
    if (d.classify) parts.push(classifyMsg(d.classify));
    if (d.failed?.length) parts.push(`mesin gagal: ${d.failed.join(', ')}`);
    return parts.join(' · ');
  };

  const onCell = (key, field, value) => {
    setRows(rs => rs.map(r => r.key === key ? { ...r, [field]: value } : r));
    setDirty(d => { const n = new Set(d); n.add(key); return n; });
  };

  const doSave = async () => {
    const edits = rows.filter(r => dirty.has(r.key)).map(r => {
      const e = { user_id: r.user_id, flow_date: r.date };
      FIELDS.forEach(([f]) => { e[f] = r[f]; });
      return e;
    });
    if (!edits.length) return;
    setError(''); setBusy('save');
    try {
      const d = await post(ep('save'), { branch_id: branchId, ...rangeParams(), edits });
      if (d.error) { setError(d.error); return; }
      let m = `${d.saved} baris perubahan disimpan.`;
      if (d.skipped_locked) m += ` ${d.skipped_locked} baris dilewati karena periode terkunci.`;
      applyResp(d, m);
    } catch (e) { setError('Gagal menyimpan perubahan.'); }
    finally { setBusy(''); }
  };

  // Menurunkan presence. Selalu tampilkan pratinjau dry-run dulu — force
  // menembus proteksi trigger dan bisa menimpa baris presensi yang ditandai
  // manual, jadi operator harus melihat angkanya sebelum memutuskan.
  const doDerive = async (force) => {
    setError(''); setBusy('derive');
    try {
      const pre = await post(ep('derive'), { branch_id: branchId, ...rangeParams(), force, dry_run: true });
      if (pre.error) { setError(pre.error); return; }
      const d0 = pre.derive || {};
      const lines = [
        force ? 'REGENERATE PERIODE (menimpa baris presensi bertanda manual)' : 'Buat/Perbarui Presensi',
        `${d0.inserted || 0} baris baru, ${d0.updated || 0} diperbarui`,
        `${d0.skipped_locked || 0} dilewati karena periode gaji terkunci`,
        `${d0.skipped_leave || 0} dilewati karena izin/cuti/sakit`,
      ];
      if (isPray && d0.skipped_no_presence) {
        lines.push(`${d0.skipped_no_presence} dilewati karena belum ada presensi kerja hari itu`);
      }
      if (force && d0.refilled_cleared) {
        lines.push(`${d0.refilled_cleared} baris yang jamnya sengaja dikosongkan akan terisi lagi`);
      }
      lines.push('', 'Lanjutkan?');
      if (!window.confirm(lines.join(String.fromCharCode(10)))) return;

      const d = await post(ep('derive'), { branch_id: branchId, ...rangeParams(), force });
      if (d.error) { setError(d.error); return; }
      const r = d.derive || {};
      let m = `Presensi: ${r.inserted || 0} baru, ${r.updated || 0} diperbarui`;
      if (r.blocked) m += `, ${r.blocked} ditolak karena baris presensi bertanda manual`;
      m += `, ${r.skipped_locked} terkunci, ${r.skipped_leave} izin/cuti/sakit`;
      m += isPray ? `, ${r.skipped_no_presence || 0} belum ada presensi kerja.` : `, ${r.skipped_empty} tanpa jam.`;
      if (r.conflicts?.length) {
        m += ` ${r.conflicts.length} hari berbeda antara mesin dan presensi manual — pakai Regenerate kalau versi mesin yang benar.`;
      }
      applyResp(d, m);
    } catch (e) { setError('Gagal menurunkan ke presensi.'); }
    finally { setBusy(''); }
  };

  // ── rincian tap ────────────────────────────────────────────────────────────
  // Sama endpoint utk kedua jenis mesin (taps() sudah menampilkan SEMUA
  // machine_type). Utk Jenis Mesin Sholat, panel ini read-only (lihat scope
  // koreksi: per-hari per-slot, bukan per-tap) -- tombol tambah/batalkan tap
  // disembunyikan lewat prop readOnly di FragmentRow.
  const openTaps = async (row) => {
    if (openKey === row.key) { setOpenKey(null); return; }
    setOpenKey(row.key); setTaps([]); setTapBusy(true);
    setAddTime(''); setAddNote('');
    try {
      const r = await fetch(`${API}/taps?user_id=${row.user_id}&date=${row.date}`, { credentials: 'include' });
      const d = await r.json();
      if (d.error) { setError(d.error); return; }
      setTaps(d.taps || []);
    } catch (e) { setError('Gagal memuat rincian tap.'); }
    finally { setTapBusy(false); }
  };

  const refreshAfterTap = async (row, msg) => {
    setNotice(msg); setTimeout(() => setNotice(''), 9000);
    const r = await fetch(`${API}/taps?user_id=${row.user_id}&date=${row.date}`, { credentials: 'include' });
    const d = await r.json();
    setTaps(d.taps || []);
    const p = new URLSearchParams({ branch_id: branchId, ...rangeParams() });
    const rr = await fetch(`${API}/${ep('data')}?${p.toString()}`, { credentials: 'include' });
    const dd = await rr.json();
    if (!dd.error) { setRows(dd.rows || []); setRecap(dd.recap || null); setDirty(new Set()); }
  };

  const doAddTap = async (row) => {
    if (!addTime) return;
    setError(''); setTapBusy(true);
    try {
      const d = await post('tap_add', { user_id: row.user_id, date: row.date, time: addTime, note: addNote });
      if (d.error) { setError(d.error); return; }
      setAddTime(''); setAddNote('');
      await refreshAfterTap(row, d.message);
    } catch (e) { setError('Gagal menambah tap.'); }
    finally { setTapBusy(false); }
  };

  const doVoid = async (row, tap, unvoid) => {
    const reason = unvoid ? '' : (window.prompt('Alasan membatalkan tap ini?') ?? null);
    if (!unvoid && reason === null) return;
    setError(''); setTapBusy(true);
    try {
      const d = await post(unvoid ? 'tap_unvoid' : 'tap_void', { tap_id: tap.id, reason });
      if (d.error) { setError(d.error); return; }
      await refreshAfterTap(row, d.message);
    } catch (e) { setError('Gagal mengubah status tap.'); }
    finally { setTapBusy(false); }
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
            <div className="title">Absensi Mentah</div>
            <div className="subtitle">Tap mesin → klasifikasi {isPray ? 'window sholat' : 'shift'} → presensi</div>
          </div>
        </div>
        <button className="btn-theme" onClick={() => setTheme(t => t === 'light' ? 'dark' : 'light')}>
          {theme === 'light' ? '🌙' : '☀️'}
        </button>
      </header>

      <div className="toolbar">
        <div className="tb-group">
          <span className="tb-label">Cabang</span>
          <select className="tb-select" value={branchId || ''} onChange={e => { setBranchId(Number(e.target.value)); setRows([]); setLoaded(false); }}>
            {branches.map(b => <option key={b.id} value={b.id}>{b.branch_name}</option>)}
          </select>
        </div>

        <div className="tb-sep" />

        <div className="tb-group">
          <span className="tb-label">Jenis Mesin</span>
          <div className="seg">
            {[['attendance', 'Absensi'], ['pray', 'Sholat']].map(([v, l]) => (
              <button key={v} className={`seg-btn ${kind === v ? 'active' : ''}`} onClick={() => switchKind(v)}>{l}</button>
            ))}
          </div>
        </div>

        <div className="tb-sep" />

        <div className="tb-group">
          <span className="tb-label">Rentang</span>
          <div className="seg">
            {[['period', 'Periode Fee'], ['range', 'Rentang Tgl'], ['date', 'Per Tanggal']].map(([v, l]) => (
              <button key={v} className={`seg-btn ${mode === v ? 'active' : ''}`} onClick={() => setMode(v)}>{l}</button>
            ))}
          </div>
          {mode === 'period' && <span className="range-info">{period ? `${period.from} → ${period.to}` : '…'}</span>}
          {mode === 'range' && (
            <span className="range-inputs">
              <input type="date" value={from} onChange={e => setFrom(e.target.value)} />
              <span>→</span>
              <input type="date" value={to} onChange={e => setTo(e.target.value)} />
            </span>
          )}
          {mode === 'date' && <input type="date" value={from} onChange={e => setFrom(e.target.value)} />}
        </div>

        <div className="toolbar-actions">
          <button className="btn-ghost" disabled={!canAct} onClick={doLoad}>
            {busy === 'load' ? 'Memuat…' : '↻ Muat Data'}
          </button>
          <select className="tb-select" value={uploadSn} onChange={e => setUploadSn(e.target.value)}
                  title={`Mesin ${isPray ? 'sholat' : 'absensi'} sumber file .dat yang akan diunggah`}>
            {machines.length === 0 && <option value="">{`(tidak ada mesin ${isPray ? 'sholat' : 'absensi'} aktif)`}</option>}
            {machines.map(m => <option key={m.sn} value={m.sn}>{m.name || m.sn}</option>)}
          </select>
          <button className="btn-ghost" disabled={!canAct || !uploadSn} onClick={() => fileRef.current?.click()}>
            {busy === 'upload' ? 'Membaca…' : '📄 Upload .dat'}
          </button>
          <button className="btn-ghost" disabled={!canAct} onClick={doCloud}>
            {busy === 'cloud' ? 'Menarik…' : '☁️ Tarik dari Cloud'}
          </button>
          <button className="btn-ghost" disabled={!canAct} onClick={doReclass}>
            {busy === 'reclass' ? 'Menghitung…' : '🔄 Klasifikasi Ulang'}
          </button>
          <input ref={fileRef} type="file" accept=".dat,.txt" hidden onChange={e => doUpload(e.target.files[0])} />
        </div>
      </div>

      {error && <div className="banner banner-error">{error}</div>}
      {notice && <div className="banner banner-info">{notice}</div>}
      {locked && (
        <div className="banner banner-warn">
          🔒 Periode <b>{String(period.month).padStart(2, '0')}/{period.year}</b> sudah dikunci.
          Tap dan koreksi pada periode ini ditolak, dan baris terkunci dilewati saat didorong ke presensi.
        </div>
      )}

      {recap && !isPray && (
        <div className="recap">
          <Stat label="Mitra Kerja" value={recap.karyawan} />
          <Stat label="Hari-Absen" value={recap.hari_absen} />
          <Stat label="Total Tap" value={recap.total_tap} />
          <Stat label="Hadir Lengkap" value={recap.hadir_lengkap} cls="ok" />
          <Stat label="Tidak Lengkap" value={recap.tidak_lengkap} cls={recap.tidak_lengkap ? 'warn' : ''} />
          <Stat label="Dikoreksi" value={recap.diedit} cls={recap.diedit ? 'edit' : ''} />
          <Stat label="Tanpa Jadwal" value={recap.tanpa_jadwal} cls={recap.tanpa_jadwal ? 'warn' : ''} />
          <Stat label="Sudah di Presensi" value={recap.sudah_di_presence} />
        </div>
      )}
      {recap && isPray && (
        <div className="recap">
          <Stat label="Mitra Kerja" value={recap.karyawan} />
          <Stat label="Hari-Karyawan" value={recap.hari_absen} />
          <Stat label="Total Tap" value={recap.total_tap} />
          <Stat label="Ada Sholat" value={recap.ada_sholat} cls="ok" />
          <Stat label="Tanpa Sholat" value={recap.tanpa_sholat} cls={recap.tanpa_sholat ? 'warn' : ''} />
          <Stat label="Dikoreksi" value={recap.diedit} cls={recap.diedit ? 'edit' : ''} />
          <Stat label="Perlu Hitung Ulang" value={recap.perlu_klasifikasi_ulang} cls={recap.perlu_klasifikasi_ulang ? 'warn' : ''} />
        </div>
      )}

      {loaded && (
        rows.length ? (
          <div className="table-wrap">
            <table className="grid">
              <thead>
                <tr>
                  <th>Tanggal</th><th>Hari</th><th>ID</th><th>Nama</th>{!isPray && <th>Shift</th>}
                  {FIELDS.map(([f, l]) => <th key={f}>{l}</th>)}
                  <th className="c-tap">Tap</th>{!isPray && <th>Telat</th>}<th>Status</th>
                </tr>
              </thead>
              <tbody>
                {rows.map(r => (
                  <FragmentRow
                    key={r.key} row={r} fields={FIELDS} isPray={isPray}
                    open={openKey === r.key} taps={taps} tapBusy={tapBusy}
                    addTime={addTime} setAddTime={setAddTime} addNote={addNote} setAddNote={setAddNote}
                    onToggle={() => openTaps(r)} onCell={onCell}
                    onAddTap={() => doAddTap(r)} onVoid={(tap, un) => doVoid(r, tap, un)}
                  />
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="empty-note">Tidak ada data pada rentang ini. Klik <b>Upload .dat</b> / <b>Tarik dari Cloud</b> untuk mengisi dari mesin, atau ganti rentang.</div>
        )
      )}
      {!loaded && !error && (
        <div className="empty-note">Pilih rentang lalu klik <b>Muat Data</b> (lihat yang tersimpan) atau <b>Tarik dari Cloud</b> (ambil dari mesin).</div>
      )}

      {loaded && rows.length > 0 && (
        <footer className="actionbar">
          <div className="status">
            <b>{dirtyCount}</b> baris diubah · rentang: <b>{rangeLabel()}</b>
          </div>
          <div className="action-buttons">
            <button className="btn-secondary" disabled={!dirtyCount || busy === 'save'} onClick={doSave}>
              {busy === 'save' ? 'Menyimpan…' : `💾 Simpan Koreksi (${dirtyCount})`}
            </button>
            <button className="btn-secondary" disabled={busy === 'derive'} onClick={() => doDerive(true)}
                    title="Menimpa juga baris presensi yang ditandai manual">
              ♻️ Regenerate Periode
            </button>
            <button className="btn-primary" disabled={busy === 'derive'} onClick={() => doDerive(false)}>
              {busy === 'derive' ? 'Memproses…' : '➡️ Buat/Perbarui Presensi'}
            </button>
          </div>
        </footer>
      )}
    </div>
  );
}

function FragmentRow({ row: r, fields, isPray, open, taps, tapBusy, addTime, setAddTime, addNote, setAddNote, onToggle, onCell, onAddTap, onVoid }) {
  return (
    <>
      <tr className={r.is_edited ? 'row-edit' : ''}>
        <td>{r.date}</td>
        <td>{r.weekday}</td>
        <td className="mono">{r.employee_code}</td>
        <td className="nm">{r.employee_name}</td>
        {!isPray && <td className="mono">{r.shift_code || '—'}</td>}
        {fields.map(([f]) => (
          <td key={f} className={r[f] !== r.machine[f] ? 'cell-diff' : ''}>
            <input type="time" className="t-in" value={r[f] || ''}
              title={r.machine[f] ? `mesin: ${r.machine[f]}` : 'tidak ada tap mesin di slot ini'}
              onChange={e => onCell(r.key, f, e.target.value)} />
          </td>
        ))}
        <td className="c-tap">
          <button className="btn-link" title={r.all_taps || 'tidak ada tap'} onClick={onToggle}>
            {open ? '▾' : '▸'} {r.tap_count}
          </button>
        </td>
        {!isPray && <td className="mono">{r.entry_late ? `${r.entry_late}m` : '—'}</td>}
        <td className="st">
          {r.is_edited && <span className="badge badge-edit">dikoreksi</span>}
          {!isPray && r.classify_method === 'positional' && <span className="badge badge-warn" title="Jadwal shift belum ada; jam ditebak dari urutan tap">tanpa jadwal</span>}
          {r.needs_reclass ? <span className="badge badge-mirror">perlu hitung ulang</span> : null}
          {!isPray && r.in_presence && <span className="badge badge-pres">di presensi</span>}
        </td>
      </tr>

      {open && (
        <tr className="tap-row">
          <td colSpan={12}>
            <div className="tap-panel">
              <div className="tap-title">
                Tap mentah — <b>{r.employee_name}</b>, {r.date}
                <span className="tap-hint">
                  {isPray
                    ? 'Rincian tap khusus dilihat di sini — koreksi sholat dilakukan per-slot di tabel di atas, bukan per-tap.'
                    : 'Tap mesin tidak pernah diubah. Kesalahan dibatalkan (dicoret), kekurangan ditambah sebagai tap manual.'}
                </span>
              </div>

              {tapBusy && <div className="tap-empty">Memuat…</div>}
              {!tapBusy && !taps.length && <div className="tap-empty">Tidak ada tap pada tanggal ini.</div>}

              {!tapBusy && taps.length > 0 && (
                <table className="tap-table">
                  <thead>
                    <tr><th>Jam</th><th>Sumber</th><th>Mesin</th><th>Catatan</th>{!isPray && <th></th>}</tr>
                  </thead>
                  <tbody>
                    {taps.map(t => (
                      <tr key={t.id} className={t.voided ? 'tap-voided' : ''}>
                        <td className="mono">{t.time}</td>
                        <td>
                          {t.source === 'manual'
                            ? <span className="badge badge-edit">manual</span>
                            : <span className={`badge ${t.machine_type === 'pray' ? 'badge-warn' : 'badge-mirror'}`}>
                                {t.machine_type === 'pray' ? 'sholat' : 'mesin'}
                              </span>}
                        </td>
                        <td className="mono">{t.machine}</td>
                        <td>{t.voided ? <i>dibatalkan: {t.void_reason || '—'}</i> : (t.note || '')}</td>
                        {!isPray && (
                          <td>
                            <button className="btn-link" onClick={() => onVoid(t, !!t.voided)}>
                              {t.voided ? 'batalkan pembatalan' : 'batalkan tap'}
                            </button>
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}

              {!isPray && (
                <div className="tap-add">
                  <span className="tb-label">Tambah tap</span>
                  <input type="time" step="1" value={addTime} onChange={e => setAddTime(e.target.value)} />
                  <input type="text" placeholder="alasan (mis. lupa finger scan)" value={addNote}
                    onChange={e => setAddNote(e.target.value)} />
                  <button className="btn-secondary" disabled={!addTime || tapBusy} onClick={onAddTap}>+ Tambah</button>
                </div>
              )}
            </div>
          </td>
        </tr>
      )}
    </>
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
