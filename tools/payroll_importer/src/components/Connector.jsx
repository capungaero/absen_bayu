import { useRef, useState, useLayoutEffect, useEffect, useCallback } from 'react';

const KIND_COLOR = { key: 'var(--amber)', insentif: 'var(--pos)', deduction: 'var(--neg)' };

export default function Connector({ targets, columns, mapping, onConnect, onDisconnect }) {
  const wrapRef = useRef(null);
  const leftDots = useRef({});   // targetKey -> el
  const rightDots = useRef({});  // colIndex  -> el
  const [pos, setPos] = useState({ left: {}, right: {} }); // measured centers
  const [drag, setDrag] = useState(null); // {side,id,x,y,mx,my,moved}
  const [armed, setArmed] = useState(null); // {side,id}

  // reverse map: colIndex -> targetKey
  const colToTarget = {};
  for (const [tk, ci] of Object.entries(mapping)) if (ci !== '' && ci != null) colToTarget[ci] = tk;

  const measure = useCallback(() => {
    const wrap = wrapRef.current;
    if (!wrap) return;
    const b = wrap.getBoundingClientRect();
    const center = (el) => {
      if (!el) return null;
      const a = el.getBoundingClientRect();
      return { x: a.left + a.width / 2 - b.left, y: a.top + a.height / 2 - b.top };
    };
    const left = {}, right = {};
    for (const k in leftDots.current) { const c = center(leftDots.current[k]); if (c) left[k] = c; }
    for (const k in rightDots.current) { const c = center(rightDots.current[k]); if (c) right[k] = c; }
    setPos({ left, right });
  }, []);

  // Measure after layout whenever the structure/mapping changes.
  useLayoutEffect(() => { measure(); }, [measure, targets, columns, mapping]);

  // Re-measure on resize / scroll.
  useEffect(() => {
    const wrap = wrapRef.current;
    const r = () => measure();
    window.addEventListener('resize', r);
    wrap && wrap.addEventListener('scroll', r, true);
    return () => { window.removeEventListener('resize', r); wrap && wrap.removeEventListener('scroll', r, true); };
  }, [measure]);

  const path = (p1, p2) => {
    if (!p1 || !p2) return '';
    const dx = Math.max(40, Math.abs(p2.x - p1.x) * 0.5);
    return `M ${p1.x} ${p1.y} C ${p1.x + dx} ${p1.y}, ${p2.x - dx} ${p2.y}, ${p2.x} ${p2.y}`;
  };

  // ---- click-to-connect ----
  const clickDot = (side, id) => {
    id = side === 'left' ? id : Number(id);
    if (side === 'left') {
      if (mapping[id] != null && mapping[id] !== '') { onDisconnect(id); return; }
    } else {
      const tk = colToTarget[id];
      if (tk) { onDisconnect(tk); return; }
    }
    if (armed && armed.side !== side) {
      const targetKey = side === 'left' ? id : armed.id;
      const colIndex = side === 'right' ? id : Number(armed.id);
      onConnect(targetKey, colIndex);
      setArmed(null);
    } else if (armed && armed.side === side && armed.id === id) {
      setArmed(null);
    } else {
      setArmed({ side, id });
    }
  };

  // ---- drag hybrid ----
  const onDotDown = (side, id) => (e) => {
    e.preventDefault();
    const src = side === 'left' ? pos.left[id] : pos.right[id];
    if (!src) return;
    setDrag({ side, id, x: src.x, y: src.y, moved: false });
  };

  useEffect(() => {
    if (!drag) return;
    const move = (e) => {
      const wrap = wrapRef.current; if (!wrap) return;
      const b = wrap.getBoundingClientRect();
      const pt = e.touches ? e.touches[0] : e;
      setDrag(d => d && ({ ...d, mx: pt.clientX - b.left, my: pt.clientY - b.top, moved: true }));
    };
    const up = (e) => {
      const pt = e.changedTouches ? e.changedTouches[0] : e;
      const el = document.elementFromPoint(pt.clientX, pt.clientY);
      const dot = el && el.closest && el.closest('[data-dot]');
      setDrag(d => {
        if (dot) {
          const dside = dot.getAttribute('data-side');
          const did = dot.getAttribute('data-id');
          if (d && dside !== d.side) {
            const targetKey = dside === 'left' ? did : d.id;
            const colIndex = dside === 'right' ? Number(did) : Number(d.id);
            onConnect(targetKey, colIndex);
            setArmed(null);
          } else if (d && !d.moved) {
            clickDot(d.side, d.id);
          }
        } else if (d && !d.moved) {
          clickDot(d.side, d.id);
        }
        return null;
      });
    };
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', up);
    window.addEventListener('touchmove', move, { passive: false });
    window.addEventListener('touchend', up);
    return () => {
      window.removeEventListener('mousemove', move);
      window.removeEventListener('mouseup', up);
      window.removeEventListener('touchmove', move);
      window.removeEventListener('touchend', up);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [drag]);

  const Dot = ({ side, id, color, innerRef }) => {
    const isArmed = armed && armed.side === side && armed.id === id;
    const connected = side === 'left'
      ? (mapping[id] != null && mapping[id] !== '')
      : !!colToTarget[id];
    return (
      <span
        ref={innerRef}
        data-dot data-side={side} data-id={id}
        className={`dot dot-${side}${connected ? ' dot-on' : ''}${isArmed ? ' dot-armed' : ''}`}
        style={{ '--dot': color }}
        onMouseDown={onDotDown(side, id)}
        onTouchStart={onDotDown(side, id)}
        title={connected ? 'Klik untuk lepas' : 'Klik / tarik untuk hubungkan'}
      />
    );
  };

  return (
    <div className="connector" ref={wrapRef}>
      <svg className="wires">
        {Object.entries(mapping).map(([tk, ci]) => {
          if (ci === '' || ci == null) return null;
          const t = targets.find(x => x.key === tk);
          const color = t ? KIND_COLOR[t.kind] : 'var(--muted)';
          return <path key={tk} d={path(pos.left[tk], pos.right[ci])}
            stroke={color} strokeWidth="2.5" fill="none" />;
        })}
        {drag && drag.moved && drag.mx != null && (
          <path d={path({ x: drag.x, y: drag.y }, { x: drag.mx, y: drag.my })}
            stroke="var(--muted)" strokeWidth="2" strokeDasharray="5 4" fill="none" />
        )}
      </svg>

      <div className="col col-left">
        <div className="col-head">Kolom Database</div>
        {targets.map(t => (
          <div key={t.key} className={`item item-${t.kind}${mapping[t.key] != null && mapping[t.key] !== '' ? ' item-on' : ''}`}>
            <span className={`badge badge-${t.kind}`}>
              {t.kind === 'key' ? 'KUNCI' : t.kind === 'insentif' ? 'KOMISI' : 'POTONGAN'}
            </span>
            <span className="item-label">{t.label}</span>
            <Dot side="left" id={t.key} color={KIND_COLOR[t.kind]} innerRef={el => (leftDots.current[t.key] = el)} />
          </div>
        ))}
      </div>

      <div className="col col-right">
        <div className="col-head">Kolom Excel</div>
        {columns.map(c => {
          const tk = colToTarget[c.index];
          const color = tk ? KIND_COLOR[(targets.find(t => t.key === tk) || {}).kind] : 'var(--muted)';
          return (
            <div key={c.index} className={`item item-excel${tk ? ' item-on' : ''}`}>
              <Dot side="right" id={c.index} color={color} innerRef={el => (rightDots.current[c.index] = el)} />
              <span className="item-label">
                {c.name}
                {c.samples && c.samples.length > 0 && <span className="samples">{c.samples.slice(0, 3).join(' · ')}</span>}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
