import { useState, useEffect, useCallback, useRef } from 'react';
import Connector from './components/Connector.jsx';

const API = import.meta.env.VITE_API_BASE ?? '/absen/payroll_import';
const MONTHS = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const now = new Date();

const norm = (s) => (s || '').toString().toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
const tokens = (s) => new Set(norm(s).split(' ').filter(Boolean));
function score(a, b) {
  const ta = tokens(a), tb = tokens(b);
  if (!ta.size || !tb.size) return 0;
  let hit = 0; for (const t of ta) if (tb.has(t)) hit++;
  return hit / Math.max(ta.size, tb.size);
}

export default function App() {
  const [theme, setTheme] = useState(() => localStorage.getItem('pi-theme') || 'light');
  const [branches, setBranches] = useState([]);
  const [branchId, setBranchId] = useState(null);
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [year, setYear] = useState(now.getFullYear());
  const [targets, setTargets] = useState([]);
  const [locked, setLocked] = useState(false);

  const [columns, setColumns] = useState([]);
  const [rowCount, setRowCount] = useState(0);
  const [token, setToken] = useState(null);
  const [mapping, setMapping] = useState({});
  const [fileName, setFileName] = useState('');

  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);
  const fileRef = useRef(null);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('pi-theme', theme);
  }, [theme]);

  // Load branches once
  useEffect(() => {
    fetch(`${API}/branches`, { credentials: 'include' })
      .then(r => r.json())
      .then(rows => {
        if (!Array.isArray(rows)) { setError('Gagal memuat daftar cabang. Pastikan Anda sudah login di aplikasi absensi.'); return; }
        setBranches(rows); if (rows[0]) setBranchId(Number(rows[0].id));
      })
      .catch(() => setError('Gagal memuat daftar cabang. Pastikan Anda sudah login di aplikasi absensi.'));
  }, []);

  // Load targets when branch/period changes
  const loadTargets = useCallback(() => {
    if (!branchId) return;
    fetch(`${API}/targets?branch_id=${branchId}&month=${month}&year=${year}`, { credentials: 'include' })
      .then(r => r.json())
      .then(d => {
        if (!d || d.error) { setError((d && d.error) || 'Gagal memuat kolom database.'); return; }
        setTargets(Array.isArray(d.targets) ? d.targets : []);
        setLocked(!!d.locked);
      })
      .catch(() => setError('Gagal memuat kolom database.'));
  }, [branchId, month, year]);
  useEffect(() => { loadTargets(); }, [loadTargets]);

  // Auto-suggest mapping after columns + targets ready
  const autoMap = useCallback((cols, tgs, guessKey) => {
    const map = {};
    const usedCols = new Set();
    // employee key first
    if (guessKey != null) { map['employee'] = guessKey; usedCols.add(guessKey); }
    for (const t of tgs) {
      if (t.key === 'employee') continue;
      let best = null, bestScore = 0.45;
      for (const c of cols) {
        if (usedCols.has(c.index)) continue;
        const sc = score(t.label, c.name);
        if (sc > bestScore) { bestScore = sc; best = c.index; }
      }
      if (best != null) { map[t.key] = best; usedCols.add(best); }
    }
    // if key still unmapped, try by header text
    if (map['employee'] == null) {
      for (const c of cols) {
        const n = norm(c.name);
        if (/\b(id|no|nik|kode|finger)\b/.test(n) || n.includes('karyawan')) { map['employee'] = c.index; break; }
      }
    }
    return map;
  }, []);

  const onFile = async (file) => {
    if (!file) return;
    setError(''); setResult(null); setBusy(true); setFileName(file.name);
    try {
      const fd = new FormData();
      fd.append('file', file);
      const r = await fetch(`${API}/parse`, { method: 'POST', body: fd, credentials: 'include' });
      const d = await r.json();
      if (d.error) { setError(d.error); setColumns([]); setToken(null); return; }
      setColumns(d.columns); setRowCount(d.row_count); setToken(d.token);
      setMapping(autoMap(d.columns, targets, d.guess_key));
    } catch (e) {
      setError('Gagal mengunggah / membaca file.');
    } finally { setBusy(false); }
  };

  const connect = (targetKey, colIndex) => {
    setMapping(m => {
      const next = { ...m };
      // remove this column from any other target (one column → one target)
      for (const k of Object.keys(next)) if (next[k] === colIndex) delete next[k];
      next[targetKey] = colIndex;
      return next;
    });
  };
  const disconnect = (targetKey) => setMapping(m => { const n = { ...m }; delete n[targetKey]; return n; });

  const mappedCount = Object.values(mapping).filter(v => v != null && v !== '').length;
  const hasKey = mapping['employee'] != null && mapping['employee'] !== '';
  const valueMapped = Object.entries(mapping).filter(([k, v]) => k !== 'employee' && v != null && v !== '').length;

  const commit = async () => {
    setError(''); setBusy(true); setResult(null);
    try {
      const r = await fetch(`${API}/commit`, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, branch_id: branchId, month, year, mapping }),
      });
      const d = await r.json();
      if (d.error) { setError(d.error); return; }
      setResult(d);
      loadTargets(); // refresh lock state
    } catch (e) {
      setError('Gagal menyimpan data.');
    } finally { setBusy(false); }
  };

  const reset = () => {
    setColumns([]); setToken(null); setMapping({}); setFileName(''); setResult(null); setError('');
    if (fileRef.current) fileRef.current.value = '';
  };

  return (
    <div className="app">
      <header className="topbar">
        <div className="brand">
          <span className="logo">📥</span>
          <div>
            <div className="title">Payroll Importer</div>
            <div className="subtitle">Impor Komisi &amp; Potongan dari Excel</div>
          </div>
        </div>
        <div className="controls">
          <select value={branchId || ''} onChange={e => setBranchId(Number(e.target.value))}>
            {branches.map(b => <option key={b.id} value={b.id}>{b.branch_name}</option>)}
          </select>
          <select value={month} onChange={e => setMonth(Number(e.target.value))}>
            {MONTHS.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
          </select>
          <input className="year" type="number" value={year} onChange={e => setYear(Number(e.target.value))} />
          <button className="btn-theme" onClick={() => setTheme(t => t === 'light' ? 'dark' : 'light')}>
            {theme === 'light' ? '🌙' : '☀️'}
          </button>
        </div>
      </header>

      {error && <div className="banner banner-error">{error}</div>}
      {locked && (
        <div className="banner banner-warn">
          🔒 Periode <b>{String(month).padStart(2, '0')}/{year}</b> sudah dikunci (payroll telah dibuat).
          Impor dinonaktifkan. Rollback penggajian periode ini dulu untuk impor ulang.
        </div>
      )}

      {!columns.length ? (
        <div className="upload-zone"
          onDragOver={e => { e.preventDefault(); e.currentTarget.classList.add('drag'); }}
          onDragLeave={e => e.currentTarget.classList.remove('drag')}
          onDrop={e => { e.preventDefault(); e.currentTarget.classList.remove('drag'); onFile(e.dataTransfer.files[0]); }}
          onClick={() => fileRef.current && fileRef.current.click()}>
          <div className="upload-icon">📄</div>
          <div className="upload-text">{busy ? 'Membaca file…' : 'Tarik file Excel ke sini atau klik untuk memilih'}</div>
          <div className="upload-hint">Format .xlsx / .xls — judul kolom bebas, akan dipetakan manual</div>
          <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv" hidden
            onChange={e => onFile(e.target.files[0])} />
        </div>
      ) : (
        <>
          <div className="filebar">
            <span>📄 <b>{fileName}</b> — {rowCount} baris · {columns.length} kolom</span>
            <div className="filebar-info">
              Tarik garis dari <b className="t-key">KUNCI/KOMISI/POTONGAN</b> (kiri) ke kolom Excel (kanan).
            </div>
            <button className="btn-ghost" onClick={reset}>Ganti file</button>
          </div>

          <Connector
            targets={targets} columns={columns} mapping={mapping}
            onConnect={connect} onDisconnect={disconnect}
          />

          <footer className="actionbar">
            <div className="status">
              {hasKey ? <span className="ok">✓ Kunci karyawan dipetakan</span> : <span className="no">✗ Kolom kunci karyawan belum dipetakan</span>}
              <span className="sep">·</span>
              <span>{valueMapped} kolom komisi/potongan dipetakan</span>
            </div>
            <button className="btn-primary" disabled={!hasKey || valueMapped === 0 || locked || busy} onClick={commit}>
              {busy ? 'Menyimpan…' : `Impor ke ${MONTHS[month - 1]} ${year}`}
            </button>
          </footer>
        </>
      )}

      {result && (
        <div className="modal-overlay" onClick={reset}>
          <div className="modal" onClick={e => e.stopPropagation()}>
            <div className="modal-icon">✅</div>
            <h2>Impor Selesai</h2>
            <ul className="result-list">
              <li><b>{result.matched}</b> karyawan cocok</li>
              <li><b>{result.insentif_saved}</b> nilai komisi disimpan</li>
              <li><b>{result.deduction_saved}</b> nilai potongan disimpan</li>
              {result.missing > 0 && <li className="warn"><b>{result.missing}</b> baris tidak cocok (dilewati)</li>}
            </ul>
            {result.missing_keys && result.missing_keys.length > 0 && (
              <div className="missing-box">
                <div className="missing-head">Tidak ditemukan ({result.missing_keys.length}):</div>
                <div className="missing-keys">{result.missing_keys.join(', ')}</div>
              </div>
            )}
            <button className="btn-primary" onClick={reset}>Selesai</button>
          </div>
        </div>
      )}
    </div>
  );
}
