# Changelog rename Gaji->Fee / Penggajian->Pembagian Fee
# Mode: APPLIED
# PPh21 dikecualikan. ID/DB/rute/class tidak disentuh.

## tools\payroll_sim\src\components\nodes.jsx  (2 baris)
  L24
    - <div className="node-header">💰 Gaji Pokok</div>
    + <div className="node-header">💰 Fee Pokok</div>
  L27
    - <span className="label">Gaji dasar</span>
    + <span className="label">Fee dasar</span>

## tools\payroll_sim\src\components\Sidebar.jsx  (1 baris)
  L209
    - const empty = <div style={{ padding: '16px 14px', color: '#475569', fontSize: 12 }}>Hitung gaji untuk melihat daftar</div>;
    + const empty = <div style={{ padding: '16px 14px', color: '#475569', fontSize: 12 }}>Hitung fee untuk melihat daftar</div>;

## tools\dat_reader\src\App.jsx  (1 baris)
  L203
    - {[['period', 'Periode Gaji'], ['range', 'Rentang Tgl'], ['date', 'Per Tanggal']].map(([v, l]) => (
    + {[['period', 'Periode Fee'], ['range', 'Rentang Tgl'], ['date', 'Per Tanggal']].map(([v, l]) => (

## tools\payroll_importer\src\App.jsx  (1 baris)
  L174
    - Impor dinonaktifkan. Rollback penggajian periode ini dulu untuk impor ulang.
    + Impor dinonaktifkan. Rollback pembagian fee periode ini dulu untuk impor ulang.

## pwa\app.js  (7 baris)
  L224
    - if(!map){ c.innerHTML='<div class="empty">Gagal memuat data gaji</div>'; return; }
    + if(!map){ c.innerHTML='<div class="empty">Gagal memuat data fee</div>'; return; }
  L230
    - html += '<div class="empty">Belum ada slip gaji final untuk bulan ini.</div>';
    + html += '<div class="empty">Belum ada slip pembagian fee final untuk bulan ini.</div>';
  L237
    - html += '<div class="kv pos kv-click" id="gajiPokokRow" style="cursor:pointer"><span>Gaji Pokok <span class="kv-chev">›</span></span><span class="v">'+rp(s.gaji_pokok)+'</span></div>';
    + html += '<div class="kv pos kv-click" id="gajiPokokRow" style="cursor:pointer"><span>Fee Pokok <span class="kv-chev">›</span></span><span class="v">'+rp(s.gaji_pokok)+'</span></div>';
  L325
    - + '<div class="sheet-title">Gaji Pokok</div>'
    + + '<div class="sheet-title">Fee Pokok</div>'
  L327
    - html += '<div class="fd-total-box"><div class="fd-total-label">Gaji Pokok Diterima</div><div class="fd-total-val" style="color:var(--green)">'+rp(gajiPokok)+'</div></div>';
    + html += '<div class="fd-total-box"><div class="fd-total-label">Fee Pokok Diterima</div><div class="fd-total-val" style="color:var(--green)">'+rp(gajiPokok)+'</div></div>';
  L329
    - html += '<div class="kv"><span>Gaji Full</span><span class="v">'+rp(gd.gaji_full)+'</span></div>';
    + html += '<div class="kv"><span>Fee Full</span><span class="v">'+rp(gd.gaji_full)+'</span></div>';
  L334
    - html += '<div class="kv total"><span>Gaji Pokok</span><span class="v" style="color:var(--green)">'+rp(gajiPokok)+'</span></div>';
    + html += '<div class="kv total"><span>Fee Pokok</span><span class="v" style="color:var(--green)">'+rp(gajiPokok)+'</span></div>';

## pwa\index.html  (1 baris)
  L45
    - <button class="nav-item" data-tab="payroll"><span class="ico">💰</span><span>Gaji</span></button>
    + <button class="nav-item" data-tab="payroll"><span class="ico">💰</span><span>Fee</span></button>

## pwa\manifest.json  (1 baris)
  L4
    - "description": "Jadwal shift & slip gaji karyawan Tiffany Houseware & Mart",
    + "description": "Jadwal shift & slip pembagian fee karyawan Tiffany Houseware & Mart",
