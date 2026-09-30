import React, { useEffect, useRef, useState } from 'react';

function normalize(s) {
  return (s || '').toLowerCase();
}

/** Dropdown satu-pilihan yang bisa diketik utk memfilter opsi (Lokasi, Pengawas). */
export function SearchableSelect({ value, onChange, options, placeholder = 'Ketik untuk mencari…', emptyText = 'Tidak ada hasil' }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const boxRef = useRef(null);

  const selected = options.find((o) => String(o.id) === String(value));

  useEffect(() => {
    const onClickOutside = (e) => {
      if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', onClickOutside);
    return () => document.removeEventListener('mousedown', onClickOutside);
  }, []);

  const q = normalize(query.trim());
  const filtered = q === '' ? options : options.filter((o) => normalize(o.label).includes(q));

  return (
    <div className="searchable-select" ref={boxRef}>
      <input
        type="text"
        value={open ? query : (selected ? selected.label : '')}
        placeholder={placeholder}
        onFocus={() => { setQuery(''); setOpen(true); }}
        onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
      />
      {selected && !open && (
        <button type="button" className="searchable-select-clear" aria-label="Hapus pilihan" onClick={() => onChange('')}>×</button>
      )}
      {open && (
        <div className="searchable-select-menu">
          {filtered.length === 0 && <div className="searchable-select-empty">{emptyText}</div>}
          {filtered.map((o) => (
            <div
              key={o.id}
              className="searchable-select-option"
              onMouseDown={(e) => { e.preventDefault(); onChange(String(o.id)); setQuery(''); setOpen(false); }}
            >
              {o.label}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/** Checklist banyak-pilihan (Mitra) yang bisa diketik utk memfilter daftar, tanpa scroll manual. */
export function SearchableChecklist({ values, onToggle, options, placeholder = 'Ketik untuk mencari…', emptyText = 'Tidak ada hasil' }) {
  const [query, setQuery] = useState('');
  const q = normalize(query.trim());
  const filtered = q === '' ? options : options.filter((o) => normalize(o.label).includes(q));

  return (
    <div>
      <input
        type="text"
        value={query}
        placeholder={placeholder}
        onChange={(e) => setQuery(e.target.value)}
        style={{ marginBottom: 6 }}
      />
      <div className="pj-checklist">
        {filtered.map((o) => (
          <label key={o.id} className="check-row" style={{ marginBottom: 4 }}>
            <input type="checkbox" checked={values.includes(String(o.id))} onChange={() => onToggle(String(o.id))} />
            {o.label}
          </label>
        ))}
        {filtered.length === 0 && <div style={{ fontSize: 12, color: 'var(--muted)' }}>{emptyText}</div>}
      </div>
    </div>
  );
}
