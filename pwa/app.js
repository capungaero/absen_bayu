'use strict';
// API satu origin dengan PWA (PWA di /app, API di /absen/api)
var API = '/absen/api';
var TKEY = 'tiffany_emp_token';

var state = { tab:'schedule', user:null, schedMonth:(new Date()).getMonth()+1, schedYear:(new Date()).getFullYear(), payYear:(new Date()).getFullYear() };

function $(s){ return document.querySelector(s); }
function el(html){ var t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; }
function rp(n){ return 'Rp ' + (Number(n)||0).toLocaleString('id-ID'); }
function loader(on){ $('#loader').classList.toggle('hidden', !on); }
function token(){ return localStorage.getItem(TKEY); }

async function api(path, opts){
  opts = opts || {};
  opts.headers = Object.assign({'Content-Type':'application/json'}, opts.headers||{});
  var t = token();
  if(t) opts.headers['Authorization'] = 'Bearer ' + t;
  var r = await fetch(API + path, opts);
  var data = await r.json().catch(function(){ return {status:false, message:'Respon tidak valid'}; });
  if(r.status === 401){ logout(); throw new Error(data.message||'Sesi berakhir'); }
  return data;
}

/* ---------- AUTH ---------- */
$('#loginForm').addEventListener('submit', async function(e){
  e.preventDefault();
  var btn=$('#loginBtn'), msg=$('#loginMsg');
  msg.textContent=''; msg.className='msg'; btn.disabled=true; btn.textContent='Memproses...';
  try{
    var res = await fetch(API+'/login', {method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({email:$('#email').value.trim(), password:$('#password').value})});
    var data = await res.json();
    if(data.status){
      localStorage.setItem(TKEY, data.token);
      await boot();
    } else { msg.textContent=data.message||'Gagal masuk'; msg.className='msg err'; }
  }catch(err){ msg.textContent='Tidak bisa terhubung ke server'; msg.className='msg err'; }
  btn.disabled=false; btn.textContent='Masuk';
});

function logout(){
  localStorage.removeItem(TKEY);
  $('#appView').classList.add('hidden');
  $('#loginView').classList.remove('hidden');
}

/* ---------- NAV ---------- */
document.querySelectorAll('.nav-item').forEach(function(b){
  b.addEventListener('click', function(){
    document.querySelectorAll('.nav-item').forEach(function(x){x.classList.remove('active')});
    b.classList.add('active');
    state.tab = b.dataset.tab;
    render();
  });
});

/* ---------- RENDER ---------- */
async function render(){
  var c = $('#content'); loader(true);
  try{
    if(state.tab==='schedule') await renderSchedule(c);
    else if(state.tab==='payroll') await renderPayroll(c);
    else if(state.tab==='requests') await renderRequests(c);
    else await renderProfile(c);
  }catch(err){ c.innerHTML='<div class="empty">'+(err.message||'Terjadi kesalahan')+'</div>'; }
  loader(false);
}

async function renderSchedule(c){
  var d = await api('/schedule?month='+state.schedMonth+'&year='+state.schedYear);
  if(!d.status){ c.innerHTML='<div class="empty">'+(d.message||'Gagal memuat jadwal')+'</div>'; return; }
  state.days = d.days;
  var html = '<div class="period-nav"><button id="pPrev">‹</button><div class="label">'+d.month_name+' '+d.year+'</div><button id="pNext">›</button></div>';
  var badgeMap={present:['b-present','Hadir'],late:['b-late','Telat'],absent:['b-absent','Alpha'],permit:['b-permit','Izin'],off:['b-off','Libur'],scheduled:['b-scheduled','Jadwal'],none:['b-none','-']};
  d.days.forEach(function(x,i){
    var b=badgeMap[x.status]||badgeMap.none;
    var sub = x.time ? x.time : x.name;
    if(x.entry) sub = 'Masuk '+x.entry+(x.out?' · Pulang '+x.out:'');
    var dd = x.date.slice(8,10);
    var pray = (x.pray && x.pray.count) ? '<span class="pray-pill">🕌 '+x.pray.count+'</span>' : '';
    html += '<div class="day-row '+(x.status==='off'?'day-off':'')+'" data-idx="'+i+'">'
      + '<div class="day-date"><div class="d">'+dd+'</div><div class="w">'+x.day.slice(0,3)+'</div></div>'
      + '<div class="day-main"><div class="day-shift">'+x.code+pray+'</div><div class="day-sub">'+(sub||'-')+'</div></div>'
      + '<div class="badge '+b[0]+'">'+b[1]+'</div><span class="chev">›</span></div>';
  });
  c.innerHTML = html;
  $('#pPrev').onclick=function(){ shiftMonth(-1); };
  $('#pNext').onclick=function(){ shiftMonth(1); };
  c.querySelectorAll('.day-row').forEach(function(row){
    row.addEventListener('click', function(){ openDayDetail(parseInt(row.dataset.idx,10)); });
  });
}

function openDayDetail(idx){
  var x = state.days && state.days[idx]; if(!x) return;
  var dateStr = x.day + ', ' + x.date.split('-').reverse().join('-');
  function lateTag(m){ return m>0 ? ' <span class="late-tag">telat '+m+'m</span>' : ''; }
  var work;
  if(x.entry || x.out){
    work = '<div class="kv"><span>Jam Masuk</span><span class="v">'+(x.entry||'—')+lateTag(x.entry_late)+'</span></div>'
         + '<div class="kv"><span>Jam Pulang</span><span class="v">'+(x.out||'—')+'</span></div>';
    var rest = x.rest || {};
    if(rest.keluar || rest.masuk){
      work += '<div class="kv"><span>Istirahat Keluar</span><span class="v">'+(rest.keluar||'—')+'</span></div>'
            + '<div class="kv"><span>Istirahat Masuk</span><span class="v">'+(rest.masuk||'—')+lateTag(rest.late)+'</span></div>';
    }
  } else {
    work = '<div class="kv"><span>Absen kerja</span><span class="v" style="color:var(--muted)">'+(x.status==='off'?'Libur':'Tidak ada')+'</span></div>';
  }
  var pray='';
  (x.pray && x.pray.items || []).forEach(function(p){
    var val = p.in ? (p.in + (p.out?' – '+p.out:' – —')) : '<span style="color:var(--muted)">Tidak absen</span>';
    var late = p.late>0 ? ' <span class="late-tag">telat '+p.late+'m</span>' : '';
    pray += '<div class="kv"><span>'+p.label+'</span><span class="v">'+val+late+'</span></div>';
  });
  var sheet = el('<div class="sheet-backdrop" id="sheetBd"><div class="sheet">'
    + '<div class="sheet-handle"></div>'
    + '<div class="sheet-title">'+dateStr+'</div>'
    + '<div class="sheet-sub">'+x.code+' · '+x.name+(x.time?' ('+x.time+')':'')+'</div>'
    + '<div class="sub-head">Absen Kerja</div>'+work
    + '<div class="sub-head">Absen Sholat</div>'+(pray||'<div class="kv"><span style="color:var(--muted)">Tidak ada data sholat</span></div>')
    + '<button class="btn-sheet-close" id="sheetClose">Tutup</button>'
    + '</div></div>');
  document.body.appendChild(sheet);
  function close(){ sheet.classList.add('closing'); setTimeout(function(){ sheet.remove(); }, 180); }
  sheet.addEventListener('click', function(e){ if(e.target===sheet) close(); });
  document.getElementById('sheetClose').onclick=close;
}
function shiftMonth(n){
  state.schedMonth += n;
  if(state.schedMonth<1){ state.schedMonth=12; state.schedYear--; }
  if(state.schedMonth>12){ state.schedMonth=1; state.schedYear++; }
  render();
}

function fmtDur(m){ m=Number(m)||0; if(m<=0) return '-'; var h=Math.floor(m/60), mm=m%60; return (h?h+'j ':'')+(mm?mm+'m':(h?'':'0m')); }

// Baris ringkasan; bila punya rincian (items) baris bisa diklik untuk buka detail.
function payRow(cls, label, valTxt, grp, si, idx, clickable){
  var chev = clickable ? '<span class="kv-chev">›</span>' : '';
  var attr = clickable ? ' class="kv '+cls+' kv-click" data-grp="'+grp+'" data-si="'+si+'" data-idx="'+idx+'"' : ' class="kv '+cls+'"';
  return '<div'+attr+'><span>'+escapeHtml(label)+chev+'</span><span class="v">'+valTxt+'</span></div>';
}

async function renderPayroll(c){
  var d = await api('/payroll?year='+state.payYear);
  if(!d.status){ c.innerHTML='<div class="empty">'+(d.message||'Gagal memuat gaji')+'</div>'; return; }
  state.slips = d.slips;
  var html = '<div class="period-nav"><button id="yPrev">‹</button><div class="label">Tahun '+d.year+'</div><button id="yNext">›</button></div>';
  if(!d.slips.length){ html += '<div class="empty">Belum ada slip gaji final untuk tahun ini.</div>'; }
  d.slips.forEach(function(s, si){
    html += '<div class="card">';
    html += '<div class="slip-thp"><div class="lbl">Take Home Pay</div><div class="amt">'+rp(s.thp)+'</div><div class="mo">'+s.month_name+' '+s.year+'</div></div>';
    // PENDAPATAN
    html += '<div class="sub-head">Pendapatan</div>';
    html += '<div class="kv pos"><span>Gaji Pokok</span><span class="v">'+rp(s.gaji_pokok)+'</span></div>';
    (s.bonus||[]).forEach(function(b, bi){
      var clickable = b.items && b.items.length > 0;
      html += payRow('pos', b.label, '+ '+rp(b.value), 'bonus', si, bi, clickable);
    });
    html += '<div class="kv total"><span>Total Pendapatan</span><span class="v" style="color:var(--green)">'+rp(s.gaji_pokok + s.total_bonus)+'</span></div>';
    // POTONGAN
    html += '<div class="sub-head">Potongan</div>';
    if(s.potongan && s.potongan.length){
      s.potongan.forEach(function(p, pi){
        var clickable = p.items && p.items.length > 0;
        html += payRow('neg', p.label, '- '+rp(p.value), 'potongan', si, pi, clickable);
      });
    } else { html += '<div class="kv"><span style="color:var(--muted)">Tidak ada potongan</span><span class="v">'+rp(0)+'</span></div>'; }
    html += '<div class="kv total"><span>Total Potongan</span><span class="v" style="color:var(--red)">- '+rp(s.total_potongan)+'</span></div>';
    // KEHADIRAN
    html += '<div class="chips"><div class="chip"><div class="n">'+s.kehadiran.hadir+'</div><div class="l">Hadir</div></div>'
      + '<div class="chip"><div class="n">'+s.kehadiran.telat+'</div><div class="l">Telat</div></div>'
      + '<div class="chip"><div class="n">'+s.kehadiran.lembur_jam+'</div><div class="l">Jam Lembur</div></div></div>';
    // REKAP SHOLAT
    if(s.sholat && s.sholat.length){
      html += '<div class="sub-head">Rekap Sholat ('+(s.sholat_total||0)+'x)</div>';
      s.sholat.forEach(function(sh){
        html += '<div class="kv"><span>'+sh.label+'</span><span class="v">'
          + (sh.count>0 ? sh.count+'x <span class="muted2">· '+fmtDur(sh.minutes)+'</span>' : '<span style="color:var(--muted)">0x</span>')
          + '</span></div>';
      });
    }
    html += '</div>';
  });
  c.innerHTML = html;
  $('#yPrev').onclick=function(){ state.payYear--; render(); };
  $('#yNext').onclick=function(){ state.payYear++; render(); };
  // Klik baris ringkasan → tampilkan rincian
  c.querySelectorAll('.kv-click').forEach(function(row){
    row.onclick=function(){
      var s = state.slips[+row.dataset.si]; if(!s) return;
      var item = s[row.dataset.grp][+row.dataset.idx]; if(!item) return;
      openPayDetail(item, row.dataset.grp==='bonus');
    };
  });
}

// Bottom-sheet rincian bonus/potongan
function openPayDetail(item, isBonus){
  var sign = isBonus ? '+ ' : '- ';
  var col  = isBonus ? 'var(--green)' : 'var(--red)';
  var rows = (item.items||[]).map(function(it){
    return '<div class="kv"><span>'+escapeHtml(it.label)+'</span><span class="v" style="color:'+col+'">'+sign+rp(it.value)+'</span></div>';
  }).join('') || '<div class="kv"><span style="color:var(--muted)">Tidak ada rincian</span></div>';
  var sheet = el('<div class="sheet-backdrop" id="sheetBd"><div class="sheet">'
    + '<div class="sheet-handle"></div>'
    + '<div class="sheet-title">'+escapeHtml(item.label)+'</div>'
    + '<div class="sheet-sub">Rincian '+(isBonus?'pendapatan':'potongan')+'</div>'
    + rows
    + '<div class="kv total"><span>Total</span><span class="v" style="color:'+col+'">'+sign+rp(item.value)+'</span></div>'
    + '<button class="btn-sheet-close" id="sheetClose">Tutup</button>'
    + '</div></div>');
  document.body.appendChild(sheet);
  function close(){ sheet.classList.add('closing'); setTimeout(function(){ sheet.remove(); }, 180); }
  sheet.addEventListener('click', function(e){ if(e.target===sheet) close(); });
  document.getElementById('sheetClose').onclick=close;
}

async function renderProfile(c){
  var u = state.user || {};
  var rows = [['NIK / Kode',u.code],['Jabatan',u.position],['Penempatan',u.location],['Sub Departemen',u.division],['Cabang',u.branch]];
  var html = '<div class="card"><div class="prof-head"><img src="'+(u.photo||'icons/icon-192.png')+'" onerror="this.src=\'icons/icon-192.png\'"><div class="nm">'+(u.name||'-')+'</div><div class="ps">'+(u.position||'')+'</div></div>';
  rows.forEach(function(r){ html+='<div class="prow"><span class="k">'+r[0]+'</span><span class="v">'+(r[1]||'-')+'</span></div>'; });
  html += '<button class="btn-logout" id="btnLogout">Keluar</button></div>';
  c.innerHTML = html;
  $('#btnLogout').onclick=function(){ if(confirm('Keluar dari aplikasi?')) logout(); };
}

/* ---------- PENGAJUAN ---------- */
function fdate(s){ if(!s) return '-'; var p=s.split('-'); return p[2]+'-'+p[1]+'-'+p[0]; }
function cap(s){ return s ? s.charAt(0).toUpperCase()+s.slice(1) : s; }
function escapeHtml(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }
function statusBadge(st){ var m={pending:['s-pending','Menunggu'],approve:['s-ok','Disetujui'],deny:['s-no','Ditolak'],cancel:['s-no','Dibatalkan']}; return m[st]||['s-pending',cap(st)]; }
function previewFile(input, sel){ var box=$(sel); box.innerHTML=''; if(input.files&&input.files[0]){ var img=document.createElement('img'); img.src=URL.createObjectURL(input.files[0]); box.appendChild(img); } }
function toast(msg){ var t=el('<div class="toast">'+escapeHtml(msg)+'</div>'); document.body.appendChild(t);
  setTimeout(function(){ t.classList.add('show'); },10); setTimeout(function(){ t.classList.remove('show'); setTimeout(function(){t.remove();},300); },2600); }

async function renderRequests(c){
  var d = await api('/requests');
  if(!d.status){ c.innerHTML='<div class="empty">'+(d.message||'Gagal memuat')+'</div>'; return; }
  var html = '<div class="req-actions"><button class="req-btn req-leave" id="btnAjuIzin">＋ Ajukan Izin</button>'
    + '<button class="req-btn req-ot" id="btnAjuLembur">＋ Ajukan Lembur</button></div>';
  html += '<div class="section-title">Riwayat Izin</div>';
  if(!d.leaves.length) html += '<div class="empty" style="padding:14px">Belum ada pengajuan izin.</div>';
  d.leaves.forEach(function(l){ var s=statusBadge(l.status); var rng=l.start===l.end?fdate(l.start):fdate(l.start)+' – '+fdate(l.end);
    html += '<div class="req-card"><div class="req-row"><div><b>'+cap(l.type)+'</b><div class="req-sub">'+rng+'</div></div><span class="sbadge '+s[0]+'">'+s[1]+'</span></div>'
      + '<div class="req-reason">'+escapeHtml(l.reason)+'</div>'
      + (l.reject?'<div class="req-reject">Ditolak: '+escapeHtml(l.reject)+'</div>':'')
      + (l.proof?'<a class="req-proof" href="'+l.proof+'" target="_blank">📎 Lihat bukti</a>':'')+'</div>';
  });
  html += '<div class="section-title" style="margin-top:14px">Riwayat Lembur</div>';
  if(!d.overtimes.length) html += '<div class="empty" style="padding:14px">Belum ada pengajuan lembur.</div>';
  d.overtimes.forEach(function(o){ var s=statusBadge(o.status);
    html += '<div class="req-card"><div class="req-row"><div><b>Lembur '+o.hour+' jam</b><div class="req-sub">'+fdate(o.date)+'</div></div><span class="sbadge '+s[0]+'">'+s[1]+'</span></div>'
      + (o.reject?'<div class="req-reject">Ditolak: '+escapeHtml(o.reject)+'</div>':'')
      + (o.proof?'<a class="req-proof" href="'+o.proof+'" target="_blank">📎 Lihat bukti</a>':'')+'</div>';
  });
  c.innerHTML = html;
  $('#btnAjuIzin').onclick = openLeaveForm;
  $('#btnAjuLembur').onclick = openOvertimeForm;
}

function makeSheet(inner){
  var sheet = el('<div class="sheet-backdrop" id="sheetBd"><div class="sheet"><div class="sheet-handle"></div>'+inner+'</div></div>');
  document.body.appendChild(sheet);
  sheet.addEventListener('click', function(e){ if(e.target===sheet) sheet.remove(); });
  return sheet;
}

function openLeaveForm(){
  var today=new Date().toISOString().slice(0,10);
  var sheet=makeSheet('<div class="sheet-title">Ajukan Izin</div><form id="leaveForm" class="reqform">'
    + '<label>Jenis Izin</label><select name="leave_type" id="lvType"><option value="izin">Izin</option><option value="cuti">Cuti</option><option value="sakit">Sakit</option></select>'
    + '<div class="form2"><div><label>Dari Tanggal</label><input type="date" name="leave_start" min="'+today+'" required></div>'
    + '<div><label>Sampai Tanggal</label><input type="date" name="leave_end" min="'+today+'" required></div></div>'
    + '<label>Alasan</label><textarea name="leave_reason" rows="2" required placeholder="Tulis alasan izin..."></textarea>'
    + '<label>Foto Bukti / Dokumen <span class="muted" id="lvReq">(opsional · wajib untuk sakit)</span></label>'
    + '<input type="file" name="leave_proof" id="lvFile" accept="image/*" capture="environment"><div id="lvPrev" class="filePrev"></div>'
    + '<p class="msg" id="lvMsg"></p><button class="btn-primary" id="lvSubmit" type="submit">Kirim Pengajuan</button>'
    + '<button class="btn-sheet-close" type="button" id="lvCancel">Batal</button></form>');
  $('#lvCancel').onclick=function(){ sheet.remove(); };
  $('#lvType').onchange=function(){ $('#lvReq').textContent = this.value==='sakit' ? '(WAJIB untuk sakit)' : '(opsional)'; };
  $('#lvFile').onchange=function(){ previewFile(this,'#lvPrev'); };
  $('#leaveForm').onsubmit=function(e){ e.preventDefault(); submitForm('/submit_leave', this, '#lvSubmit', '#lvMsg', function(){ sheet.remove(); }); };
}

function openOvertimeForm(){
  var today=new Date().toISOString().slice(0,10);
  var sheet=makeSheet('<div class="sheet-title">Ajukan Lembur</div><form id="otForm" class="reqform">'
    + '<label>Tanggal Lembur</label><input type="date" name="overtime_date" max="'+today+'" required>'
    + '<label>Lama Lembur (jam)</label><input type="number" name="overtime_hour" step="0.5" min="0.5" required placeholder="mis. 2">'
    + '<label>Foto Bukti <span class="muted">(wajib)</span></label>'
    + '<input type="file" name="overtime_proof" id="otFile" accept="image/*" capture="environment" required><div id="otPrev" class="filePrev"></div>'
    + '<p class="msg" id="otMsg"></p><button class="btn-primary" id="otSubmit" type="submit">Kirim Pengajuan</button>'
    + '<button class="btn-sheet-close" type="button" id="otCancel">Batal</button></form>');
  $('#otCancel').onclick=function(){ sheet.remove(); };
  $('#otFile').onchange=function(){ previewFile(this,'#otPrev'); };
  $('#otForm').onsubmit=function(e){ e.preventDefault(); submitForm('/submit_overtime', this, '#otSubmit', '#otMsg', function(){ sheet.remove(); }); };
}

async function submitForm(path, form, btnSel, msgSel, onOk){
  var btn=$(btnSel), msg=$(msgSel), orig=btn.textContent;
  msg.textContent=''; msg.className='msg'; btn.disabled=true; btn.textContent='Mengirim...';
  try{
    var r = await fetch(API+path, {method:'POST', headers:{'Authorization':'Bearer '+token()}, body:new FormData(form)});
    var data = await r.json();
    if(r.status===401){ logout(); return; }
    if(data.status){ onOk(); toast(data.message||'Pengajuan terkirim'); render(); }
    else { msg.textContent=data.message||'Gagal mengirim'; msg.className='msg err'; }
  }catch(err){ msg.textContent='Gagal terhubung ke server'; msg.className='msg err'; }
  btn.disabled=false; btn.textContent=orig;
}

/* ---------- BOOT ---------- */
async function boot(){
  loader(true);
  try{
    var p = await api('/profile');
    if(!p.status) throw new Error(p.message);
    state.user = p.user;
    $('#uName').textContent = p.user.name;
    if(p.user.photo) $('#uPhoto').src = p.user.photo;
    $('#loginView').classList.add('hidden');
    $('#appView').classList.remove('hidden');
    await render();
  }catch(err){ loader(false); logout(); return; }
  loader(false);
}

if(token()) boot(); else $('#loginView').classList.remove('hidden');

if('serviceWorker' in navigator){
  window.addEventListener('load', function(){ navigator.serviceWorker.register('sw.js').catch(function(){}); });
}
