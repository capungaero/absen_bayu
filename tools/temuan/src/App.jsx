import React, { useEffect, useState, useCallback } from 'react';
import { apiGet, getToken, clearToken } from './api.js';
import Login from './components/Login.jsx';
import Dashboard from './components/Dashboard.jsx';
import ReportForm from './components/ReportForm.jsx';
import Admin from './components/Admin.jsx';

export default function App() {
  const [me, setMe] = useState(null);
  const [checking, setChecking] = useState(!!getToken());
  const [tab, setTab] = useState('dashboard');

  const loadMe = useCallback(async () => {
    if (!getToken()) { setChecking(false); return; }
    try {
      const data = await apiGet('/me');
      setMe(data.user);
    } catch {
      clearToken();
      setMe(null);
    } finally {
      setChecking(false);
    }
  }, []);

  useEffect(() => { loadMe(); }, [loadMe]);

  const logout = () => { clearToken(); setMe(null); setTab('dashboard'); };

  if (checking) {
    return <div className="login-wrap"><div style={{ color: '#64748b' }}>Memuat…</div></div>;
  }

  if (!me) {
    return <Login onLogin={loadMe} />;
  }

  return (
    <>
      <header className="app-header">
        <div>
          <h1>🚩 Temuan</h1>
          <div className="sub">{me.name} · {me.branch || '-'}</div>
        </div>
        <button className="btn-logout" onClick={logout}>Keluar</button>
      </header>

      <nav className="tabs">
        <button className={tab === 'dashboard' ? 'active' : ''} onClick={() => setTab('dashboard')}>Dashboard</button>
        <button className={tab === 'lapor' ? 'active' : ''} onClick={() => setTab('lapor')}>+ Lapor</button>
        {me.is_admin && (
          <button className={tab === 'kelola' ? 'active' : ''} onClick={() => setTab('kelola')}>Kelola</button>
        )}
      </nav>

      <main className="container">
        {tab === 'dashboard' && <Dashboard me={me} onSessionEnd={logout} />}
        {tab === 'lapor' && <ReportForm me={me} onDone={() => setTab('dashboard')} onSessionEnd={logout} />}
        {tab === 'kelola' && me.is_admin && <Admin me={me} onSessionEnd={logout} />}
      </main>
    </>
  );
}
