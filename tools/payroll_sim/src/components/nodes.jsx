import { Handle, Position } from '@xyflow/react';

const fmt = n => 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');

export function EmployeeNode({ data }) {
  return (
    <div className="node-base node-employee">
      <div className="node-header">👤 {data.name}</div>
      <div className="node-body">
        <div className="node-row"><span className="label">Jabatan</span><span className="val">{data.position}</span></div>
        <div className="node-row"><span className="label">Cabang</span><span className="val">{data.branch}</span></div>
        <div className="node-row"><span className="label">Masa kerja</span><span className="val">{data.masaKerja}</span></div>
        <div className="node-row"><span className="label">PTKP</span><span className="val">{data.ptkpStatus || '-'}</span></div>
      </div>
      <Handle type="source" position={Position.Bottom} style={{ background: '#2563eb' }} />
    </div>
  );
}

export function SalaryNode({ data }) {
  return (
    <div className="node-base node-salary">
      <Handle type="target" position={Position.Top} style={{ background: '#16a34a' }} />
      <div className="node-header">💰 Fee Pokok</div>
      <div className="node-body">
        <div className="node-row">
          <span className="label">Fee dasar</span>
          <span className="val" style={{ color: 'var(--pos)' }}>{fmt(data.salary)}</span>
        </div>
        {data.salaryMin > 0 && (
          <div className="node-row">
            <span className="label">Min. upah</span>
            <span className="val" style={{ color: 'var(--muted)' }}>{fmt(data.salaryMin)}</span>
          </div>
        )}
        {data.paymentReceive !== data.salary && (
          <div className="node-row">
            <span className="label">Dibayarkan</span>
            <span className="val" style={{ color: 'var(--amber)' }}>{fmt(data.paymentReceive)}</span>
          </div>
        )}
      </div>
      <Handle type="source" position={Position.Bottom} style={{ background: '#16a34a' }} />
    </div>
  );
}

export function IncomeNode({ data }) {
  const items  = data.items || [];
  const click  = data.onItemClick;
  return (
    <div className="node-base node-income">
      <Handle type="target" position={Position.Top} style={{ background: '#059669' }} />
      <div className="node-header">📈 Pemasukan Tambahan</div>
      <div className="node-body">
        {data.overtime > 0 && (
          <div className="node-row node-row-click" onClick={() => click?.({
            id: 'overtime', name: 'Lembur', amount: data.overtime, isAuto: true, autoCalc: true,
            hasOverride: false, source: 'Auto — mesin absensi',
            formulaDetail: `${data.overtimeHours}j × tarif lembur`, logItems: data.overtimeLog || [],
          }, 'income', false)}>
            <span className="label">Lembur {data.overtimeHours > 0 ? `(${data.overtimeHours}j)` : ''}</span>
            <span className="val" style={{ color: 'var(--pos)' }}>+{fmt(data.overtime)}</span>
          </div>
        )}
        {items.filter(x => x.active && x.amount > 0).map(x => (
          <div className="node-row node-row-click" key={x.id} onClick={() => click?.(x, 'income', false)}>
            <span className="label" style={{ maxWidth: 120, overflow:'hidden', whiteSpace:'nowrap', textOverflow:'ellipsis' }}>{x.name}</span>
            <span className="val" style={{ color: 'var(--pos)' }}>+{fmt(x.amount)}</span>
          </div>
        ))}
        {data.customBonus > 0 && (
          <div className="node-row">
            <span className="label">Bonus kustom</span>
            <span className="val" style={{ color: 'var(--purple)' }}>+{fmt(data.customBonus)}</span>
          </div>
        )}
        {data.overtime === 0 && !items.some(x => x.active && x.amount > 0) && data.customBonus === 0 && (
          <div className="node-row"><span className="label" style={{ color: 'var(--faint)' }}>—</span></div>
        )}
        <div className="node-total">
          <span>Subtotal</span>
          <span style={{ color: 'var(--pos)' }}>{fmt(data.total)}</span>
        </div>
      </div>
      <Handle type="source" position={Position.Bottom} style={{ background: '#059669' }} />
    </div>
  );
}

export function OutcomeNode({ data }) {
  const fineList   = data.fineList   || [];
  const deductions = data.deductions || [];
  const click      = data.onItemClick;
  return (
    <div className="node-base node-outcome">
      <Handle type="target" position={Position.Top} style={{ background: '#dc2626' }} />
      <div className="node-header">📉 Potongan</div>
      <div className="node-body">
        {fineList.map(x => (
          <div className="node-row node-row-click" key={x.id} onClick={() => click?.(x, 'outcome', true)}>
            <span className="label" style={{ maxWidth:120, overflow:'hidden', whiteSpace:'nowrap', textOverflow:'ellipsis' }}>{x.name}</span>
            <span className="val" style={{color:'var(--fine)'}}>-{fmt(x.amount)}</span>
          </div>
        ))}
        {deductions.filter(x => x.active && x.amount > 0).map(x => (
          <div className="node-row node-row-click" key={x.id} onClick={() => click?.(x, 'outcome', false)}>
            <span className="label" style={{ maxWidth:120, overflow:'hidden', whiteSpace:'nowrap', textOverflow:'ellipsis' }}>{x.name}</span>
            <span className="val" style={{ color: 'var(--neg)' }}>-{fmt(x.amount)}</span>
          </div>
        ))}
        {data.strip > 0 && <div className="node-row"><span className="label">Potongan strip</span><span className="val" style={{color:'var(--neg)'}}>-{fmt(data.strip)}</span></div>}
        {data.customDeduct > 0 && <div className="node-row"><span className="label">Potongan kustom</span><span className="val" style={{color:'var(--purple)'}}>-{fmt(data.customDeduct)}</span></div>}
        <div className="node-total">
          <span>Subtotal</span>
          <span style={{ color: 'var(--neg)' }}>-{fmt(data.total)}</span>
        </div>
      </div>
      <Handle type="source" position={Position.Bottom} style={{ background: '#dc2626' }} />
    </div>
  );
}

export function DetailNode({ data }) {
  const item   = data.item || {};
  const isFine = data.isFine;
  const isCommission = item.conditions?.length > 0;
  // Komisi sholat pakai chip (count), bukan tabel uang
  const prayLog = isCommission ? (item.logItems || []) : [];
  const logs   = isCommission ? [] : (item.logItems || []);
  const shown  = logs.slice(0, 10);
  const rest   = logs.length - 10;
  const amtColor = isFine ? 'var(--fine)' : 'var(--pos)';
  const sign     = isFine ? '-' : '+';

  return (
    <div className="node-base node-detail">
      <Handle type="target" position={Position.Left} style={{ background: '#475569' }} />
      <div className="node-header" style={{ justifyContent: 'space-between' }}>
        <span style={{ overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>
          {isFine ? '⚠' : '📋'} {item.name}
        </span>
        <button
          onClick={data.onClose}
          style={{ background:'none', border:'none', color:'#dbeafe', cursor:'pointer', padding:'0 0 0 8px', fontSize:13, flexShrink:0 }}
        >✕</button>
      </div>
      <div className="node-body">
        {/* Summary row */}
        <div className="node-row" style={{ paddingBottom:6, marginBottom:6, borderBottom:'1px solid var(--border)' }}>
          <span className="label" style={{ color: item.autoCalc ? 'var(--pos)' : '#2563eb' }}>
            {item.autoCalc ? '⚙ Otomatis' : '👤 HR input'}
          </span>
          <span className="val" style={{ color: amtColor }}>{sign}{fmt(item.amount)}</span>
        </div>

        {/* Formula / description */}
        {item.formulaDetail && (
          <div style={{ fontSize:11, color:'var(--dim)', marginBottom: (item.conditions?.length || shown.length) ? 8 : 0 }}>{item.formulaDetail}</div>
        )}

        {/* Syarat komisi (checklist) */}
        {item.conditions?.length > 0 && (
          <div style={{ display:'flex', flexDirection:'column', gap:3, marginBottom: shown.length ? 8 : 0 }}>
            {item.conditions.map((c, i) => (
              <div key={i} style={{ display:'flex', gap:5, fontSize:11, alignItems:'baseline',
                padding:'2px 4px', borderRadius:3, background: c.ok ? 'rgba(16,185,129,.1)' : 'rgba(248,113,113,.1)' }}>
                <span style={{ fontWeight:800, color: c.ok ? 'var(--pos)' : 'var(--neg)' }}>{c.ok ? '✓' : '✗'}</span>
                <span style={{ color:'var(--text-2)', flex:1, lineHeight:1.3 }}>{c.label}</span>
                {c.value && <span style={{ color:'var(--dim)', fontStyle:'italic', whiteSpace:'nowrap' }}>{c.value}</span>}
              </div>
            ))}
          </div>
        )}

        {/* Rekap sholat (chip) */}
        {prayLog.length > 0 && (
          <div style={{ display:'flex', flexWrap:'wrap', gap:4 }}>
            {prayLog.map((l, i) => (
              <span key={i} style={{ fontSize:10, padding:'2px 6px', borderRadius:10,
                background: l.qualify ? 'rgba(16,185,129,.12)' : 'var(--inset)',
                border:`1px solid ${l.qualify ? '#059669' : 'var(--border)'}`,
                color: l.qualify ? '#059669' : 'var(--dim)' }}>{l.date}: {l.count}</span>
            ))}
          </div>
        )}

        {/* Log table */}
        {shown.length > 0 && (
          <div style={{ display:'flex', flexDirection:'column', gap:4 }}>
            <div style={{ display:'flex', gap:6, fontSize:10, color:'var(--faint)', paddingBottom:3, borderBottom:'1px solid var(--border)' }}>
              <span style={{ minWidth:68 }}>Tanggal</span>
              <span style={{ flex:1 }}>Keterangan</span>
              <span>Jumlah</span>
            </div>
            {shown.map((l, i) => (
              <div key={i} style={{ display:'flex', gap:6, fontSize:11, alignItems:'center' }}>
                <span style={{ color:'var(--dim)', minWidth:68, whiteSpace:'nowrap' }}>{l.date}</span>
                <span style={{ color:'var(--muted)', flex:1, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>
                  {[l.waktu, l.type, l.minutes > 0 ? l.minutes+' mnt' : '', l.note, l.pct ? l.pct+'%' : ''].filter(Boolean).join(' ') || '—'}
                </span>
                <span style={{ color:amtColor, fontWeight:600, whiteSpace:'nowrap' }}>{sign}{fmt(l.amount)}</span>
              </div>
            ))}
            {rest > 0 && (
              <div style={{ fontSize:10, color:'var(--faint)', textAlign:'center', paddingTop:2 }}>+{rest} lainnya</div>
            )}
          </div>
        )}

        {shown.length === 0 && !isCommission && (
          <div style={{ fontSize:11, color:'var(--faint)', textAlign:'center', padding:'4px 0' }}>
            {item.amount > 0 ? 'Total terhitung otomatis' : '— Tidak ada data'}
          </div>
        )}
      </div>
    </div>
  );
}

export function ThpNode({ data }) {
  const isDebt = data.debt > 0;
  return (
    <div className="node-base node-thp">
      <Handle type="target" position={Position.Top} style={{ background: '#0ea5e9' }} />
      <div className="node-header">🏦 Take Home Pay</div>
      <div className={`thp-amount ${isDebt ? 'debt' : ''}`}>
        {fmt(data.thp)}
      </div>
      {isDebt && (
        <div className="debt-badge">⚠ Hutang: {fmt(data.debt)}</div>
      )}
      <div className="node-body" style={{ paddingTop: 0 }}>
        <div className="node-row"><span className="label" style={{ color:'#bae6fd' }}>Total income</span><span className="val" style={{color:'#86efac'}}>{fmt(data.totalIncome)}</span></div>
        <div className="node-row"><span className="label" style={{ color:'#bae6fd' }}>Total potongan</span><span className="val" style={{color:'#fca5a5'}}>{fmt(data.totalOutcome)}</span></div>
      </div>
    </div>
  );
}

export const nodeTypes = {
  employee: EmployeeNode,
  salary:   SalaryNode,
  income:   IncomeNode,
  outcome:  OutcomeNode,
  thp:      ThpNode,
  detail:   DetailNode,
};
