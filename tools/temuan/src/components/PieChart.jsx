import React, { useMemo, useState } from 'react';

// Palet kategorikal tervalidasi (urutan TETAP, jangan diacak -- urutan ini yang
// lolos cek CVD-safety). Lihat skill dataviz/references/palette.md.
const SLOT_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
const MAX_SLICES = 7; // sisa dilipat ke "Lainnya" (slot ke-8, abu-abu netral)
const OTHER_COLOR = '#94a3b8';

function polar(cx, cy, r, angleDeg) {
  const a = ((angleDeg - 90) * Math.PI) / 180;
  return { x: cx + r * Math.cos(a), y: cy + r * Math.sin(a) };
}

function arcPath(cx, cy, rOuter, rInner, startAngle, endAngle) {
  const large = endAngle - startAngle > 180 ? 1 : 0;
  const p1 = polar(cx, cy, rOuter, startAngle);
  const p2 = polar(cx, cy, rOuter, endAngle);
  const p3 = polar(cx, cy, rInner, endAngle);
  const p4 = polar(cx, cy, rInner, startAngle);
  return [
    `M ${p1.x} ${p1.y}`,
    `A ${rOuter} ${rOuter} 0 ${large} 1 ${p2.x} ${p2.y}`,
    `L ${p3.x} ${p3.y}`,
    `A ${rInner} ${rInner} 0 ${large} 0 ${p4.x} ${p4.y}`,
    'Z',
  ].join(' ');
}

/** Donut chart part-to-whole. data: [{name, count}], sudah terurut server-side. */
export default function PieChart({ data, emptyLabel = 'Tidak ada data pada rentang ini.' }) {
  const [hoverIdx, setHoverIdx] = useState(null);
  const slices = useMemo(() => {
    if (!data || data.length === 0) return [];
    let items = data;
    if (data.length > MAX_SLICES) {
      const head = data.slice(0, MAX_SLICES);
      const otherCount = data.slice(MAX_SLICES).reduce((s, d) => s + d.count, 0);
      items = [...head, { name: 'Lainnya', count: otherCount, isOther: true }];
    }
    const total = items.reduce((s, d) => s + d.count, 0) || 1;
    let angle = 0;
    return items.map((d, i) => {
      const frac = d.count / total;
      const start = angle;
      const end = angle + frac * 360;
      angle = end;
      return {
        ...d,
        pct: frac * 100,
        color: d.isOther ? OTHER_COLOR : SLOT_COLORS[i % SLOT_COLORS.length],
        start,
        end,
      };
    });
  }, [data]);

  if (slices.length === 0) {
    return <div style={{ color: 'var(--muted)', fontSize: 13, padding: '20px 0' }}>{emptyLabel}</div>;
  }

  const cx = 90, cy = 90, rOuter = 82, rInner = 46;

  return (
    <div style={{ display: 'flex', gap: 24, flexWrap: 'wrap', alignItems: 'center' }}>
      <div style={{ position: 'relative', flexShrink: 0 }}>
        <svg width="180" height="180" viewBox="0 0 180 180" role="img" aria-label="Diagram lingkaran distribusi temuan">
          {slices.map((s, i) => (
            <path
              key={s.name}
              d={arcPath(cx, cy, rOuter, rInner, s.start, s.end)}
              fill={s.color}
              stroke="var(--card)"
              strokeWidth={2}
              opacity={hoverIdx === null || hoverIdx === i ? 1 : 0.35}
              onMouseEnter={() => setHoverIdx(i)}
              onMouseLeave={() => setHoverIdx(null)}
              style={{ cursor: 'pointer', transition: 'opacity 0.12s' }}
            >
              <title>{`${s.name}: ${s.count} (${s.pct.toFixed(1)}%)`}</title>
            </path>
          ))}
          <text x={cx} y={cy - 4} textAnchor="middle" fontSize="20" fontWeight="700" fill="var(--text)">
            {slices.reduce((s, d) => s + d.count, 0)}
          </text>
          <text x={cx} y={cy + 14} textAnchor="middle" fontSize="10" fill="var(--muted)">
            total temuan
          </text>
        </svg>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6, minWidth: 180, flex: 1 }}>
        {slices.map((s, i) => (
          <div
            key={s.name}
            onMouseEnter={() => setHoverIdx(i)}
            onMouseLeave={() => setHoverIdx(null)}
            style={{
              display: 'flex', alignItems: 'center', gap: 8, fontSize: 13,
              opacity: hoverIdx === null || hoverIdx === i ? 1 : 0.45, cursor: 'default',
            }}
          >
            <span style={{ width: 10, height: 10, borderRadius: 3, background: s.color, flexShrink: 0 }} />
            <span style={{ flex: 1, color: 'var(--text)' }}>{s.name}</span>
            <span style={{ color: 'var(--muted)', fontVariantNumeric: 'tabular-nums' }}>
              {s.count} ({s.pct.toFixed(1)}%)
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}
