import { useState, useEffect, useRef, useCallback } from 'react';
import FlowCanvas from './components/FlowCanvas.jsx';
import Sidebar from './components/Sidebar.jsx';

const API = import.meta.env.VITE_API_BASE ?? '/api';
const MONTHS = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function buildFineList(outcome) {
  const fb = outcome.fineBreakdown || {};
  const fl = outcome.fineLog || {};
  const lateLog  = fl.late  || [];
  const defs = [
    { id: 'lateFine',       name: 'Denda Terlambat',    amount: fb.lateFine,       log: lateLog.filter(x => !x.note) },
    { id: 'halfFine',       name: 'Setengah Hadir',      amount: fb.halfFine,       log: lateLog.filter(x => x.note === '½ hari') },
    { id: 'restFine',       name: 'Terlambat Istirahat', amount: fb.restFine,       log: fl.rest  || [] },
    { id: 'prayFine',       name: 'Denda Sholat',        amount: fb.prayFine,       log: fl.pray  || [] },
    { id: 'leaveFine',      name: 'Potongan Izin/Cuti',  amount: fb.leaveFine,      log: fl.leave || [] },
    { id: 'alfaWeekday',    name: 'Alfa Hari Biasa',     amount: fb.alfaWeekday,    log: (fl.alfa || []).filter(x => x.type === 'weekday') },
    { id: 'alfaWeekend',    name: 'Alfa Hari Libur',     amount: fb.alfaWeekend,    log: (fl.alfa || []).filter(x => x.type === 'weekend') },
    { id: 'alfaSpecial',    name: 'Alfa Hari Khusus',    amount: fb.alfaSpecial,    log: (fl.alfa || []).filter(x => x.type === 'special') },
    { id: 'earlyLeaveFine', name: 'Pulang Awal',         amount: fb.earlyLeaveFine, log: [] },
  ];
  return defs.filter(x => (x.amount || 0) > 0).map(x => {
    const amt = Math.round(x.amount || 0);
    const detail = x.log.length > 0
      ? `${x.log.length} ${x.id === 'prayFine' ? 'waktu sholat' : 'hari/kejadian'} = Rp ${amt.toLocaleString('id-ID')}`
      : (x.id === 'earlyLeaveFine' ? 'Kumulatif menit × tarif/jam' : 'Total terhitung');
    return { id: x.id, name: x.name, amount: amt, active: true, isAuto: true, autoCalc: true,
      hasOverride: false, source: 'Auto — sistem absensi', formulaDetail: detail, logItems: x.log };
  });
}

export default function App() {
  const [query,    setQuery]    = useState('');
  const [results,  setResults]  = useState([]);
  const [selected, setSelected] = useState(null);
  const [month,    setMonth]    = useState(new Date().getMonth() + 1);
  const [year,     setYear]     = useState(new Date().getFullYear());
  const [loading,  setLoading]  = useState(false);
  const [calcData, setCalcData] = useState(null);
  const [error,    setError]    = useState('');
  const [customItems, setCustomItems] = useState([]);
  const [showAll,     setShowAll]     = useState(true);
  const [theme,       setTheme]       = useState(() => localStorage.getItem('ps-theme') || 'dark');
  const searchTimer = useRef(null);

  // Terapkan & simpan tema
  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('ps-theme', theme);
  }, [theme]);

  // Debounce search
  useEffect(() => {
    if (query.length < 2) { setResults([]); return; }
    clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(async () => {
      try {
        const r = await fetch(`${API}/employees?q=${encodeURIComponent(query)}`);
        setResults(await r.json());
      } catch { setResults([]); }
    }, 300);
  }, [query]);

  const selectEmployee = emp => {
    setSelected(emp);
    setQuery(emp.name);
    setResults([]);
  };

  const calculate = async () => {
    if (!selected) return;
    setLoading(true);
    setError('');
    try {
      const r = await fetch(`${API}/salary`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ employeeId: selected.id, month, year, customItems }),
      });
      const data = await r.json();
      if (data.error) throw new Error(data.error);
      data.outcome.fineList = buildFineList(data.outcome);
      setCustomItems(prev => prev.filter(x => x.custom));
      setCalcData(data);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  // Toggle aktif/nonaktif insentif atau deduction dari sidebar
  const handleToggle = useCallback((type, id) => {
    setCalcData(prev => {
      if (!prev) return prev;
      const clone = JSON.parse(JSON.stringify(prev));
      if (type === 'insentif') {
        const item = clone.income.insentifList.find(x => x.id === id);
        if (item) {
          item.active = !item.active;
          clone.income.insentif = clone.income.insentifList.filter(x => x.active).reduce((s, x) => s + x.amount, 0);
        }
      } else if (type === 'deduction') {
        const item = clone.outcome.deductionList.find(x => x.id === id);
        if (item) {
          item.active = !item.active;
          clone.outcome.deduction = clone.outcome.deductionList.filter(x => x.active).reduce((s, x) => s + x.amount, 0);
        }
      } else if (type === 'fine') {
        const item = clone.outcome.fineList.find(x => x.id === id);
        if (item) {
          item.active = !item.active;
          clone.outcome.fine = clone.outcome.fineList.filter(x => x.active).reduce((s, x) => s + x.amount, 0);
        }
      }
      return clone;
    });
  }, []);

  const handleCustomAdd = useCallback(item => {
    setCustomItems(prev => [...prev, item]);
  }, []);

  const handleCustomRemove = useCallback(id => {
    setCustomItems(prev => prev.filter(x => x.id !== id));
  }, []);

  // Drag-and-drop from sidebar onto canvas
  const onDragStart = useCallback((e, item) => {
    e.dataTransfer.setData('application/json', JSON.stringify(item));
    e.dataTransfer.effectAllowed = 'copy';
  }, []);

  const onDrop = useCallback(e => {
    e.preventDefault();
    try {
      const item = JSON.parse(e.dataTransfer.getData('application/json'));
      if (!item.custom) return; // Master items handled via toggle
      handleCustomAdd({ ...item, id: `custom-${Date.now()}` });
    } catch {}
  }, [handleCustomAdd]);

  const onDragOver = useCallback(e => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; }, []);

  const years = [];
  for (let y = new Date().getFullYear(); y >= new Date().getFullYear() - 3; y--) years.push(y);

  return (
    <>
      {/* ── TOP BAR ── */}
      <div className="topbar">
        <div className="logo">
          Payroll Sim
          <span>Tiffany Houseware — read-only</span>
        </div>

        {/* Search */}
        <div className="search-wrap">
          <input
            placeholder="Cari nama atau kode mitra kerja..."
            value={query}
            onChange={e => { setQuery(e.target.value); if (!e.target.value) setSelected(null); }}
            onKeyDown={e => e.key === 'Enter' && results[0] && selectEmployee(results[0])}
          />
          {results.length > 0 && (
            <div className="search-dropdown">
              {results.map(emp => (
                <div key={emp.id} className="emp-item" onClick={() => selectEmployee(emp)}>
                  <div className="name">{emp.name} <span style={{color:'#64748b',fontWeight:400}}>({emp.code})</span></div>
                  <div className="meta">{emp.position} · {emp.branch}</div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Period */}
        <div className="period-select">
          <select value={month} onChange={e => setMonth(Number(e.target.value))}>
            {MONTHS.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
          </select>
          <select value={year} onChange={e => setYear(Number(e.target.value))}>
            {years.map(y => <option key={y} value={y}>{y}</option>)}
          </select>
        </div>

        <button className="btn-calc" onClick={calculate} disabled={!selected || loading}>
          {loading ? '⏳ Menghitung...' : '▶ Hitung'}
        </button>

        {error && <span style={{ color: '#f87171', fontSize: 12 }}>⚠ {error}</span>}

        <button
          className={`btn-filter${showAll ? '' : ' btn-filter-on'}`}
          onClick={() => setShowAll(v => !v)}
          title={showAll ? 'Tampilkan item yang ada data saja' : 'Tampilkan semua item'}
        >
          {showAll ? '🔽 Semua item' : '🔼 Ada data'}
        </button>

        <button
          className="btn-theme"
          onClick={() => setTheme(t => t === 'dark' ? 'light' : 'dark')}
          title={theme === 'dark' ? 'Beralih ke tema terang' : 'Beralih ke tema gelap'}
        >
          {theme === 'dark' ? '☀️' : '🌙'}
        </button>

        {calcData && (
          <span style={{ fontSize: 12, color: '#4ade80', whiteSpace: 'nowrap' }}>
            THP: Rp {Math.round(calcData.summary.thp).toLocaleString('id-ID')}
          </span>
        )}
      </div>

      {/* ── MAIN ── */}
      <div className="main-layout">
        <Sidebar
          result={calcData}
          customItems={customItems}
          showAll={showAll}
          onCustomItemAdd={handleCustomAdd}
          onCustomItemRemove={handleCustomRemove}
          onToggle={handleToggle}
          onDragStart={onDragStart}
        />
        <FlowCanvas
          result={calcData}
          customItems={customItems}
          onDrop={onDrop}
          onDragOver={onDragOver}
        />
      </div>
    </>
  );
}
