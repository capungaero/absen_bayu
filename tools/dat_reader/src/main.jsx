import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './index.css';

// Error boundary: cegah white-screen kalau ada error render tak terduga.
class ErrorBoundary extends React.Component {
  constructor(p){ super(p); this.state = { err: null }; }
  static getDerivedStateFromError(err){ return { err }; }
  render(){
    if (this.state.err) return (
      <div style={{ maxWidth: 560, margin: '60px auto', padding: 24, fontFamily: 'Inter, sans-serif',
        background: '#fff', border: '1px solid #fca5a5', borderRadius: 12, color: '#0f172a' }}>
        <h2 style={{ margin: '0 0 8px', color: '#dc2626' }}>Terjadi kesalahan</h2>
        <p style={{ fontSize: 14, color: '#475569' }}>Aplikasi mengalami error. Muat ulang halaman untuk mencoba lagi.</p>
        <button onClick={() => location.reload()}
          style={{ marginTop: 12, padding: '9px 18px', background: '#2563eb', color: '#fff',
            border: 'none', borderRadius: 8, cursor: 'pointer', fontWeight: 600 }}>Muat ulang</button>
        <pre style={{ marginTop: 14, fontSize: 11, color: '#94a3b8', whiteSpace: 'pre-wrap' }}>
          {String(this.state.err && this.state.err.message || this.state.err)}
        </pre>
      </div>
    );
    return this.props.children;
  }
}

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <ErrorBoundary><App /></ErrorBoundary>
  </React.StrictMode>
);
