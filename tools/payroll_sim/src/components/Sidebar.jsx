import { useState } from 'react';

const fmt = n => 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');

function LogTable({ items, isFine }) {
  if (!items || items.length === 0) return null;
  const shown = items.slice(0, 6);
  const rest  = items.length - 6;
  return (
    <div className="item-log">
      {shown.map((l, i) => (
        <div key={i} className="log-row">
          <span className="log-date">{l.date}</span>
          <span className="log-info">
            {l.waktu ? l.waktu + ' ' : ''}
            {l.type  ? l.type  + ' ' : ''}
            {l.minutes > 0 ? l.minutes + ' mnt' : ''}
            {l.note  ? l.note  : ''}
            {l.pct   ? l.pct + '%' : ''}
          </span>
          <span className="log-amount">{isFine ? '-' : '+'}{fmt(l.amount)}</span>
        </div>
      ))}
      {rest > 0 && <div className="log-more">+{rest} lainnya</div>}
    </div>
  );
}

function CondList({ conditions }) {
  if (!conditions || conditions.length === 0) return null;
  return (
    <div className="cond-list">
      {conditions.map((c, i) => (
        <div key={i} className={`cond-row ${c.ok ? 'ok' : 'no'}`}>
          <span className="cond-mark">{c.ok ? '✓' : '✗'}</span>
          <span className="cond-label">{c.label}</span>
          {c.value && <span className="cond-value">{c.value}</span>}
        </div>
      ))}
    </div>
  );
}

function ItemDetail({ item, isFine }) {
  const isCommission = item.formula === 'auto_commission';
  return (
    <div className="item-detail">
      <div className="item-detail-row">
        <span className="detail-label">Sumber</span>
        <span className={`detail-source ${item.autoCalc ? 'auto' : item.hasOverride ? 'hr' : 'empty'}`}>
          {item.autoCalc ? '⚙ ' : item.hasOverride ? '👤 ' : '— '}{item.source}
        </span>
      </div>
      <div className="item-detail-row">
        <span className="detail-label">Nilai</span>
        <span className="detail-value">{item.formulaDetail}</span>
      </div>
      {isCommission && (
        <>
          <div className="item-detail-row" style={{ marginTop: 2 }}>
            <span className="detail-label">Syarat</span>
            <span className={`detail-source ${item.eligible ? 'auto' : 'empty'}`} style={{ fontWeight: 600 }}>
              {item.eligible ? '✓ Memenuhi semua syarat' : '✗ Tidak memenuhi'}
            </span>
          </div>
          <CondList conditions={item.conditions} />
        </>
      )}
      {!isCommission && item.formula && item.formula !== 'none' && (
        <div className="item-detail-row">
          <span className="detail-label">Formula</span>
          <span className="detail-formula">
            {item.formula === 'per_presence' ? 'nominal × jumlah hadir' : item.formula}
          </span>
        </div>
      )}
      {isCommission && item.logItems?.length > 0 && (
        <div className="cond-pray">
          {item.logItems.map((l, i) => (
            <span key={i} className={`pray-chip ${l.qualify ? 'ok' : ''}`}>{l.date}: {l.count}</span>
          ))}
        </div>
      )}
      {!isCommission && <LogTable items={item.logItems} isFine={isFine} />}
    </div>
  );
}

function ItemCard({ item, isBonus, isFine, active, onToggle, onDragStart, onRemove }) {
  const [expanded, setExpanded] = useState(false);
  const activeClass = active
    ? (isFine ? 'active-fine' : isBonus ? 'active-bonus' : 'active-deduct')
    : '';
  const isAuto = item.isAuto;

  return (
    <div className={`item-card-wrap${expanded ? ' expanded' : ''}`}>
      <div
        className={`item-card ${activeClass}`}
        draggable={!isAuto}
        onDragStart={e => !isAuto && onDragStart(e, item)}
        onClick={() => isAuto && setExpanded(v => !v)}
        style={isAuto ? { cursor: 'pointer' } : {}}
      >
        {isAuto ? (
          <span
            className={`badge-a${item.autoCalc ? ' badge-calc' : item.hasOverride ? ' badge-hr' : ' badge-empty'}`}
            title={item.autoCalc ? 'Dihitung otomatis sistem' : item.hasOverride ? 'Input HR' : 'Tidak ada nilai'}
          >A</span>
        ) : (
          <span className="drag-handle">⠿</span>
        )}

        <div className="item-body">
          <div className="item-name" title={item.name}>{item.name}</div>
          {item.formula === 'auto_commission' ? (
            <div className="item-meta" style={{ color: item.eligible ? '#34d399' : '#f87171' }}>
              {item.eligible ? '✓ syarat terpenuhi' : '✗ tidak memenuhi syarat'}
            </div>
          ) : isAuto ? (
            <div className="item-meta">
              {item.autoCalc ? '⚙ otomatis' : item.hasOverride ? '👤 HR input' : '— tidak ada nilai'}
            </div>
          ) : (
            <div className="item-meta">Kustom</div>
          )}
        </div>

        {item.amount > 0 && (
          <span className={`item-amount ${isBonus ? 'green' : 'red'}`}>
            {isBonus ? '+' : '-'}{fmt(item.amount)}
          </span>
        )}

        {isAuto ? (
          <div className="auto-actions" onClick={e => e.stopPropagation()}>
            <button
              className={`toggle-btn${active ? ' tok-on' : ''}`}
              title={active ? 'Nonaktifkan' : 'Aktifkan'}
              onClick={() => onToggle(item.id)}
            >{active ? '✓' : '○'}</button>
            <span
              className="chevron"
              onClick={e => { e.stopPropagation(); setExpanded(v => !v); }}
            >{expanded ? '▲' : '▼'}</span>
          </div>
        ) : (
          <div className="auto-actions" onClick={e => e.stopPropagation()}>
            <button className={`toggle-btn${active ? ' tok-on' : ''}`} onClick={() => onToggle(item.id)}>{active ? '✓' : '○'}</button>
            <button className="toggle-btn" title="Hapus" onClick={() => onRemove(item.id)}>✕</button>
          </div>
        )}
      </div>

      {isAuto && expanded && <ItemDetail item={item} isFine={isFine} />}
    </div>
  );
}

function CustomItemForm({ type, onSave, onCancel }) {
  const [label, setLabel] = useState('');
  const [amount, setAmount] = useState('');

  const handleSave = () => {
    const amt = parseInt(String(amount).replace(/\D/g, ''), 10);
    if (!label.trim() || !amt) return;
    onSave({ id: `custom-${Date.now()}`, name: label.trim(), amount: amt, type, custom: true, isAuto: false });
    setLabel(''); setAmount('');
  };

  return (
    <div className="custom-form">
      <input placeholder="Keterangan..." value={label} onChange={e => setLabel(e.target.value)} />
      <input
        placeholder="Nominal (Rp)"
        value={amount}
        onChange={e => {
          const v = e.target.value.replace(/\D/g, '');
          setAmount(v ? parseInt(v).toLocaleString('id-ID') : '');
        }}
      />
      <div className="form-actions">
        <button className="btn-save" onClick={handleSave}>✓ Simpan</button>
        <button className="btn-cancel" onClick={onCancel}>✕</button>
      </div>
    </div>
  );
}

export default function Sidebar({ result, customItems, showAll, onCustomItemAdd, onCustomItemRemove, onToggle, onDragStart }) {
  const [showBonusForm,  setShowBonusForm]  = useState(false);
  const [showDeductForm, setShowDeductForm] = useState(false);

  const hasData = item => item.amount > 0 || item.hasOverride;
  const allInsentif   = result?.income?.insentifList  || [];
  const allDeduction  = result?.outcome?.deductionList || [];
  const insentifList  = showAll ? allInsentif  : allInsentif.filter(hasData);
  const deductionList = showAll ? allDeduction : allDeduction.filter(hasData);
  const fineList      = result?.outcome?.fineList      || [];
  const customBonus   = customItems.filter(x => x.type === 'bonus');
  const customDeduct  = customItems.filter(x => x.type === 'deduction');

  const handleSave = item => {
    onCustomItemAdd(item);
    if (item.type === 'bonus') setShowBonusForm(false);
    else setShowDeductForm(false);
  };

  const empty = <div style={{ padding: '16px 14px', color: '#475569', fontSize: 12 }}>Hitung gaji untuk melihat daftar</div>;

  return (
    <div className="sidebar">

      {/* ── BONUS / INSENTIF ── */}
      <div className="sidebar-header">
        <span>📈 Bonus / Insentif</span>
        <button onClick={() => { setShowBonusForm(v => !v); setShowDeductForm(false); }}>
          {showBonusForm ? '✕' : '+ Tambah'}
        </button>
      </div>
      {showBonusForm && <CustomItemForm type="bonus" onSave={handleSave} onCancel={() => setShowBonusForm(false)} />}
      <div className="sidebar-section">
        {insentifList.length === 0 && customBonus.length === 0 && empty}
        {insentifList.map(item => (
          <ItemCard key={item.id} item={item} isBonus={true} active={item.active}
            onToggle={id => onToggle('insentif', id)}
            onDragStart={onDragStart} onRemove={() => {}} />
        ))}
        {customBonus.map(item => (
          <ItemCard key={item.id} item={item} isBonus={true} active={true}
            onToggle={() => {}} onDragStart={onDragStart} onRemove={id => onCustomItemRemove(id)} />
        ))}
      </div>

      <div className="sidebar-divider" />

      {/* ── DENDA ── */}
      <div className="sidebar-header">
        <span>⚠ Denda</span>
      </div>
      <div className="sidebar-section">
        {fineList.length === 0 && empty}
        {fineList.map(item => (
          <ItemCard key={item.id} item={item} isBonus={false} isFine={true} active={item.active}
            onToggle={id => onToggle('fine', id)}
            onDragStart={onDragStart} onRemove={() => {}} />
        ))}
      </div>

      <div className="sidebar-divider" />

      {/* ── POTONGAN ── */}
      <div className="sidebar-header">
        <span>📉 Potongan</span>
        <button onClick={() => { setShowDeductForm(v => !v); setShowBonusForm(false); }}>
          {showDeductForm ? '✕' : '+ Tambah'}
        </button>
      </div>
      {showDeductForm && <CustomItemForm type="deduction" onSave={handleSave} onCancel={() => setShowDeductForm(false)} />}
      <div className="sidebar-section">
        {deductionList.length === 0 && customDeduct.length === 0 && empty}
        {deductionList.map(item => (
          <ItemCard key={item.id} item={item} isBonus={false} active={item.active}
            onToggle={id => onToggle('deduction', id)}
            onDragStart={onDragStart} onRemove={() => {}} />
        ))}
        {customDeduct.map(item => (
          <ItemCard key={item.id} item={item} isBonus={false} active={true}
            onToggle={() => {}} onDragStart={onDragStart} onRemove={id => onCustomItemRemove(id)} />
        ))}
      </div>

    </div>
  );
}
