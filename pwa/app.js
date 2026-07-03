'use strict';
// API satu origin dengan PWA (PWA di /app, API di /absen/api)
var API = '/absen/api';
var TKEY = 'tiffany_emp_token';

var state = { tab:'schedule', user:null, schedMonth:(new Date()).getMonth()+1, schedYear:(new Date()).getFullYear(), payMonth:(new Date()).getMonth()+1, payYear:(new Date()).getFullYear(), payCache:{} };

function $(s){ return document.querySelector(s); }
function el(html){ var t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstChild; }
function rp(n){ return 'Rp ' + (Number(n)||0).toLocaleString('id-ID'); }
function minuteText(m){ m=Number(m)||0; return m>0 ? m+' menit' : ''; }
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

var demoBtn = $('#demoBtn');
if(demoBtn){
  demoBtn.addEventListener('click', async function(){
    var msg=$('#loginMsg'); msg.textContent=''; msg.className='msg';
    demoBtn.disabled=true; demoBtn.textContent='Memuat demo...';
    try{
      var res = await fetch(API+'/demo_login', {method:'POST'});
      var data = await res.json();
      if(data.status){ localStorage.setItem(TKEY, data.token); await boot(); }
      else { msg.textContent=data.message||'Demo tidak tersedia'; msg.className='msg err'; }
    }catch(err){ msg.textContent='Tidak bisa terhubung ke server'; msg.className='msg err'; }
    demoBtn.disabled=false; demoBtn.textContent='▶ Coba Demo (tanpa login)';
  });
}

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
    else if(state.tab==='bpjs') await renderBpjs(c);
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
    // Cek tidak lengkap
    var warns = [];
    var hasEntry = !!x.entry, hasOut = !!x.out;
    if((hasEntry && !hasOut) || (!hasEntry && hasOut)) warns.push('absen tdk lengkap');
    if(x.entry_late > 0) warns.push('masuk telat '+minuteText(x.entry_late));
    if(x.out_early > 0) warns.push('pulang cepat '+minuteText(x.out_early));
    var rest = x.rest || {};
    if((rest.keluar && !rest.masuk) || (!rest.keluar && rest.masuk)) warns.push('istirahat tdk lengkap');
    if(rest.late > 0) warns.push('istirahat telat '+minuteText(rest.late));
    var prayIncomplete = false;
    var prayLate = 0;
    (x.pray && x.pray.items || []).forEach(function(p){
      if((p.in && !p.out) || (!p.in && p.out)) prayIncomplete = true;
      if(p.late > 0) prayLate += p.late;
    });
    if(prayIncomplete) warns.push('sholat tdk lengkap');
    if(prayLate > 0) warns.push('sholat telat '+minuteText(prayLate));
    var warnHtml = warns.length ? '<div class="day-warn">'+warns.join(' · ')+'</div>' : '';
    html += '<div class="day-row '+(x.status==='off'?'day-off':'')+'" data-idx="'+i+'">'
      + '<div class="day-date"><div class="d">'+dd+'</div><div class="w">'+x.day.slice(0,3)+'</div></div>'
      + '<div class="day-main"><div class="day-shift">'+x.code+pray+'</div><div class="day-sub">'+(sub||'-')+'</div>'+warnHtml+'</div>'
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
  var tl = '<span class="tl-tag">tidak lengkap</span>';
  var work;
  if(x.entry || x.out){
    var entryVal = (x.entry||'—')+lateTag(x.entry_late);
    var outVal = x.out||'—';
    if(x.out_early > 0) outVal += ' <span class="late-tag">pulang cepat '+x.out_early+'m</span>';
    if(x.entry && !x.out) outVal = '— '+tl;
    if(!x.entry && x.out) entryVal = '— '+tl;
    work = '<div class="kv"><span>Jam Masuk</span><span class="v">'+entryVal+'</span></div>'
         + '<div class="kv"><span>Jam Pulang</span><span class="v">'+outVal+'</span></div>';
    var rest = x.rest || {};
    if(rest.keluar || rest.masuk){
      var rOutVal = rest.keluar||'—';
      var rInVal = (rest.masuk||'—')+lateTag(rest.late);
      if(rest.keluar && !rest.masuk) rInVal = '— '+tl;
      if(!rest.keluar && rest.masuk) rOutVal = '— '+tl;
      work += '<div class="kv"><span>Istirahat Keluar</span><span class="v">'+rOutVal+'</span></div>'
            + '<div class="kv"><span>Istirahat Masuk</span><span class="v">'+rInVal+'</span></div>';
    }
  } else {
    work = '<div class="kv"><span>Absen kerja</span><span class="v" style="color:var(--muted)">'+(x.status==='off'?'Libur':'Tidak ada')+'</span></div>';
  }
  var pray='';
  (x.pray && x.pray.items || []).forEach(function(p){
    var val;
    if(p.in && p.out){
      val = p.in+' – '+p.out;
    } else if(p.in && !p.out){
      val = p.in+' – — '+tl;
    } else if(!p.in && p.out){
      val = '— – '+p.out+' '+tl;
    } else {
      val = '<span style="color:var(--muted)">Tidak absen</span>';
    }
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

var MONTH_NAMES = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

async function fetchPayYear(yr){
  if(state.payCache[yr]) return state.payCache[yr];
  var d = await api('/payroll?year='+yr);
  if(!d.status) return null;
  var map = {};
  (d.slips||[]).forEach(function(s){ map[s.month] = s; });
  state.payCache[yr] = map;
  return map;
}

function payNav(delta){
  var m = state.payMonth + delta;
  var y = state.payYear;
  if(m < 1){ m = 12; y--; }
  if(m > 12){ m = 1; y++; }
  state.payMonth = m; state.payYear = y;
  render();
}

async function renderPayroll(c){
  var yr = state.payYear, mo = state.payMonth;
  var map = await fetchPayYear(yr);
  if(!map){ c.innerHTML='<div class="empty">Gagal memuat data gaji</div>'; return; }
  var s = map[mo] || null;

  var html = '<div class="period-nav"><button id="yPrev">‹</button><div class="label">'+MONTH_NAMES[mo]+' '+yr+'</div><button id="yNext">›</button></div>';

  if(!s){
    html += '<div class="empty">Belum ada slip gaji final untuk bulan ini.</div>';
  } else {
    state.slips = [s];
    var si = 0;
    html += '<div class="card">';
    html += '<div class="slip-thp"><div class="lbl">Take Home Pay</div><div class="amt">'+rp(s.thp)+'</div><div class="mo">'+s.month_name+' '+s.year+'</div></div>';
    html += '<div class="sub-head">Pendapatan</div>';
    html += '<div class="kv pos kv-click" id="gajiPokokRow" style="cursor:pointer"><span>Gaji Pokok <span class="kv-chev">›</span></span><span class="v">'+rp(s.gaji_pokok)+'</span></div>';
    (s.bonus||[]).forEach(function(b, bi){
      var clickable = b.items && b.items.length > 0;
      html += payRow('pos', b.label, '+ '+rp(b.value), 'bonus', si, bi, clickable);
    });
    html += '<div class="kv total"><span>Total Pendapatan</span><span class="v" style="color:var(--green)">'+rp(s.gaji_pokok + s.total_bonus)+'</span></div>';
    html += '<div class="sub-head">Potongan</div>';
    if(s.potongan && s.potongan.length){
      s.potongan.forEach(function(p, pi){
        var clickable = p.items && p.items.length > 0;
        html += payRow('neg', p.label, '- '+rp(p.value), 'potongan', si, pi, clickable);
      });
    } else { html += '<div class="kv"><span style="color:var(--muted)">Tidak ada potongan</span><span class="v">'+rp(0)+'</span></div>'; }
    html += '<div class="kv total"><span>Total Potongan</span><span class="v" style="color:var(--red)">- '+rp(s.total_potongan)+'</span></div>';
    html += '<div class="chips"><div class="chip"><div class="n">'+s.kehadiran.hadir+'</div><div class="l">Hadir</div></div>'
      + '<div class="chip"><div class="n">'+s.kehadiran.telat+'</div><div class="l">Telat</div></div>'
      + '<div class="chip"><div class="n">'+s.kehadiran.lembur_jam+'</div><div class="l">Jam Lembur</div></div></div>';
    if(s.sholat && s.sholat.length){
      html += '<div class="sub-head">Rekap Sholat ('+(s.sholat_total||0)+'x)</div>';
      s.sholat.forEach(function(sh){
        html += '<div class="kv"><span>'+sh.label+'</span><span class="v">'
          + (sh.count>0 ? sh.count+'x <span class="muted2">· '+fmtDur(sh.minutes)+'</span>' : '<span style="color:var(--muted)">0x</span>')
          + '</span></div>';
      });
    }
    html += '</div>';
  }
  c.innerHTML = html;
  $('#yPrev').onclick=function(){ payNav(-1); };
  $('#yNext').onclick=function(){ payNav(1); };
  var gpRow = document.getElementById('gajiPokokRow');
  if(gpRow && s){
    gpRow.onclick = function(){ openGajiDetail(s.gaji_detail, s.gaji_pokok, s.month_name+' '+s.year); };
  }
  c.querySelectorAll('.kv-click').forEach(function(row){
    if(row.id === 'gajiPokokRow') return;
    row.onclick=function(){
      var s = state.slips[+row.dataset.si]; if(!s) return;
      var grp = row.dataset.grp, idx = +row.dataset.idx;
      var item = s[grp][idx]; if(!item) return;
      if(grp==='potongan' && item.label==='Denda' && s.fine_detail){
        openFineDetail(s.fine_detail, s.month_name+' '+s.year);
      } else {
        openPayDetail(item, grp==='bonus');
      }
    };
  });
}

// Bottom-sheet rincian bonus/potongan
function openPayDetail(item, isBonus){
  var sign = isBonus ? '+ ' : '- ';
  var col  = isBonus ? 'var(--green)' : 'var(--red)';
  var hasSub = (item.items||[]).some(function(it){ return !!it.sub; });
  var rows = '';
  (item.items||[]).forEach(function(it){
    if(hasSub){
      rows += '<table class="fd-tbl"><tr><td>'+escapeHtml(it.label)+'</td><td class="tc">'+escapeHtml(it.sub||'')+'</td><td class="tr" style="color:'+col+'">'+sign+rp(it.value)+'</td></tr></table>';
    } else {
      rows += '<div class="kv"><span>'+escapeHtml(it.label)+'</span><span class="v" style="color:'+col+'">'+sign+rp(it.value)+'</span></div>';
    }
    if(it.days && it.days.length){
      rows += '<div class="ded-days"><table class="fd-tbl"><tr class="ded-days-hdr"><td>Hari/Tanggal</td><td class="tc">Masuk</td><td class="tc">Pulang</td><td class="tc">Kurang</td></tr>';
      it.days.forEach(function(dy){
        var dur = dy.short >= 60 ? Math.floor(dy.short/60)+'j '+dy.short%60+'m' : dy.short+'m';
        rows += '<tr><td>'+escapeHtml(dy.day)+', '+escapeHtml(dy.date)+'</td><td class="tc">'+escapeHtml(dy['in'])+'</td><td class="tc">'+escapeHtml(dy.out)+'</td><td class="tc" style="color:var(--red)">'+dur+'</td></tr>';
      });
      rows += '</table></div>';
    }
  });
  if(!rows) rows = '<div class="kv"><span style="color:var(--muted)">Tidak ada rincian</span></div>';
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

function openGajiDetail(gd, gajiPokok, period){
  if(!gd){ return; }
  var html = '<div class="sheet-handle"></div>'
    + '<div class="sheet-title">Gaji Pokok</div>'
    + '<div class="sheet-sub">'+period+'</div>';
  html += '<div class="fd-total-box"><div class="fd-total-label">Gaji Pokok Diterima</div><div class="fd-total-val" style="color:var(--green)">'+rp(gajiPokok)+'</div></div>';
  html += '<div class="sub-head">Perhitungan</div>';
  html += '<div class="kv"><span>Gaji Full</span><span class="v">'+rp(gd.gaji_full)+'</span></div>';
  var potAlpha = gd.pot_alpha || 0;
  var potOff = gd.pot_off || 0;
  if(potAlpha > 0) html += '<div class="kv neg"><span>Pot. Alpha</span><span class="v">- '+rp(potAlpha)+'</span></div>';
  if(potOff > 0) html += '<div class="kv neg"><span>Pot. Tidak Masuk</span><span class="v">- '+rp(potOff)+'</span></div>';
  html += '<div class="kv total"><span>Gaji Pokok</span><span class="v" style="color:var(--green)">'+rp(gajiPokok)+'</span></div>';
  html += '<div class="sub-head">Rekap Kehadiran</div>';
  html += '<div class="kv"><span>Hadir</span><span class="v">'+gd.hadir+' / '+gd.max_hadir+' hari</span></div>';
  html += '<div class="kv"><span>Tepat Waktu</span><span class="v" style="color:var(--green)">'+gd.tepat_waktu+' hari</span></div>';
  if(gd.telat > 0) html += '<div class="kv"><span>Terlambat</span><span class="v" style="color:var(--amber)">'+gd.telat+' hari</span></div>';
  if(gd.setengah > 0) html += '<div class="kv"><span>Setengah Hari</span><span class="v" style="color:var(--amber)">'+gd.setengah+' hari</span></div>';
  if(gd.cuti > 0) html += '<div class="kv"><span>Cuti</span><span class="v">'+gd.cuti+' hari</span></div>';
  if(gd.sakit > 0) html += '<div class="kv"><span>Sakit</span><span class="v">'+gd.sakit+' hari</span></div>';
  if(gd.izin > 0) html += '<div class="kv"><span>Izin</span><span class="v">'+gd.izin+' hari</span></div>';
  var totalAlpha = (gd.alpha_weekday||0) + (gd.alpha_weekend||0);
  if(totalAlpha > 0){
    html += '<div class="kv"><span>Alpha</span><span class="v" style="color:var(--red)">'+totalAlpha+' hari</span></div>';
    if(gd.alpha_weekday > 0) html += '<div class="kv" style="padding-left:12px"><span class="muted">Hari kerja</span><span class="v muted">'+gd.alpha_weekday+'</span></div>';
    if(gd.alpha_weekend > 0) html += '<div class="kv" style="padding-left:12px"><span class="muted">Weekend</span><span class="v muted">'+gd.alpha_weekend+'</span></div>';
  }
  html += '<button class="btn-sheet-close" id="sheetClose">Tutup</button>';
  var sheet = el('<div class="sheet-backdrop" id="sheetBd"><div class="sheet">'+html+'</div></div>');
  document.body.appendChild(sheet);
  function close(){ sheet.classList.add('closing'); setTimeout(function(){ sheet.remove(); }, 180); }
  sheet.addEventListener('click', function(e){ if(e.target===sheet) close(); });
  document.getElementById('sheetClose').onclick=close;
}

// Format tanggal YYYY-MM-DD → "dd MMM YYYY"
function fmtDate(s){
  if(!s) return '-';
  var m=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
  var p=s.split('-');
  return parseInt(p[2],10)+' '+m[parseInt(p[1],10)-1]+' '+p[0];
}

// Detail denda bottom-sheet with tabs
function openFineDetail(fd, period){
  var d = fd.detail || {};
  var entry = d.entry || {};
  var rest = d.rest || {};
  var pray = d.pray || {};
  var leave = d.leave || {};

  function tableRows(arr, minuteLabel){
    if(!arr || !arr.length) return '<div class="fd-empty">Tidak ada data</div>';
    return '<table class="fd-tbl">' + arr.map(function(r){
      var mid = r.half ? 'Tdk finger' : ((parseInt(r.in_minute,10)||0)+' '+(minuteLabel||'Menit'));
      return '<tr><td>'+fmtDate(r.date)+'</td><td class="tc">'+mid+'</td><td class="tr">'+rp(r.amount)+'</td></tr>';
    }).join('') + '</table>';
  }

  function sectionBlock(title, count, amount, arr, minuteLabel){
    if(!arr || !arr.length) return '';
    return '<div class="fd-sec">'
      + '<div class="fd-sec-title"><span>'+title+'</span><span class="fd-cnt">'+count+'x</span><span class="fd-amt">'+rp(amount)+'</span></div>'
      + tableRows(arr, minuteLabel)
      + '</div>';
  }

  // Tab: Kehadiran
  var tabKehadiran = '';
  var dayLate = (entry.day||{}).late || [];
  var dayHalf = (entry.day||{}).half || [];
  var dayEarly = (entry.day||{}).early_leave || [];
  var dayWknd = (entry.day||{}).weekend || [];
  var daySpecial = (entry.day||{}).special_double || [];
  tabKehadiran += sectionBlock('Terlambat', dayLate.length, entry.amount_in_late||0, dayLate);
  tabKehadiran += sectionBlock('Finger Tidak Lengkap', dayHalf.length, entry.amount_in_half||0, dayHalf);
  tabKehadiran += sectionBlock('Pulang Lebih Awal', dayEarly.length, entry.amount_early_leave||0, dayEarly);
  if(dayWknd.length){
    tabKehadiran += '<div class="fd-sec"><div class="fd-sec-title"><span>Alpha Weekend</span><span class="fd-cnt">'+dayWknd.length+'x</span><span class="fd-amt">'+rp((entry.amount_in_weekend||0)-(entry.amount_in_special_double||0))+'</span></div>'
      + '<table class="fd-tbl">' + dayWknd.map(function(r){ return '<tr><td>'+fmtDate(r.date)+'</td><td class="tc">'+r.in_count+'x</td><td class="tr">'+rp(r.amount)+'</td></tr>'; }).join('') + '</table></div>';
  }
  if(daySpecial.length){
    tabKehadiran += '<div class="fd-sec"><div class="fd-sec-title"><span>Alpha Tgl Khusus</span><span class="fd-cnt">'+daySpecial.length+'x</span><span class="fd-amt">'+rp(entry.amount_in_special_double||0)+'</span></div>'
      + '<table class="fd-tbl">' + daySpecial.map(function(r){ return '<tr><td>'+fmtDate(r.date)+'</td><td class="tc">'+r.in_count+'x</td><td class="tr">'+rp(r.amount)+'</td></tr>'; }).join('') + '</table></div>';
  }
  if(!tabKehadiran) tabKehadiran = '<div class="fd-empty">Tidak ada denda kehadiran</div>';

  // Tab: Istirahat
  var restLate = (rest.late) || [];
  var tabIstirahat = sectionBlock('Telat Istirahat', restLate.length, (rest.total||{}).in_fine||0, restLate);
  if(!tabIstirahat) tabIstirahat = '<div class="fd-empty">Tidak ada denda istirahat</div>';

  // Tab: Sholat
  var prayNames = {subuh:'Subuh',dzuhur:'Dzuhur',ashar:'Ashar',maghrib:'Maghrib',isha:'Isya',friday:'Jumat'};
  var prayDetail = (pray.detail) || {};
  var tabSholat = '';
  Object.keys(prayDetail).forEach(function(pk){
    var pv = prayDetail[pk];
    if(!pv.late || !pv.late.length) return;
    tabSholat += sectionBlock(prayNames[pk]||pk, pv.late.length, (pv.total||{}).amount||0, pv.late);
  });
  if(!tabSholat) tabSholat = '<div class="fd-empty">Tidak ada denda sholat</div>';

  // Tab: Izin
  var leaveTypes = (leave.type) || {};
  var leaveNames = {izin:'Izin',sakit:'Sakit',cuti:'Cuti'};
  var tabIzin = '';
  Object.keys(leaveTypes).forEach(function(lk){
    var lv = leaveTypes[lk];
    if(!lv.day || !lv.day.length) return;
    tabIzin += '<div class="fd-sec"><div class="fd-sec-title"><span>'+(leaveNames[lk]||lk)+'</span><span class="fd-cnt">'+lv.day.length+'x</span><span class="fd-amt">'+rp(lv.total_amount||0)+'</span></div>'
      + '<table class="fd-tbl">' + lv.day.map(function(d){ return '<tr><td>'+fmtDate(d.date)+'</td><td class="tc">'+d.percent+'%</td><td class="tr">'+rp(d.amount)+'</td></tr>'; }).join('') + '</table></div>';
  });
  if(!tabIzin) tabIzin = '<div class="fd-empty">Tidak ada denda izin</div>';

  var tabs = [
    {id:'kehadiran', label:'Kehadiran', content:tabKehadiran},
    {id:'istirahat', label:'Istirahat', content:tabIstirahat},
    {id:'sholat', label:'Sholat', content:tabSholat},
    {id:'izin', label:'Izin', content:tabIzin}
  ];

  var tabBtns = tabs.map(function(t,i){ return '<button class="fd-tab'+(i===0?' active':'')+'" data-tab="'+t.id+'">'+t.label+'</button>'; }).join('');
  var tabPanels = tabs.map(function(t,i){ return '<div class="fd-panel" id="fdp-'+t.id+'" style="'+(i>0?'display:none':'')+'">'+t.content+'</div>'; }).join('');

  var sheet = el('<div class="sheet-backdrop" id="sheetBd"><div class="sheet fd-sheet">'
    + '<div class="sheet-handle"></div>'
    + '<div class="sheet-title" style="display:flex;align-items:center;gap:6px"><span style="font-size:18px">📋</span> Detail Denda — '+escapeHtml(period)+'</div>'
    + '<div class="fd-total-box"><div class="fd-total-label">Total Denda</div><div class="fd-total-val">'+rp(fd.amount)+'</div></div>'
    + '<div class="fd-tabs">'+tabBtns+'</div>'
    + tabPanels
    + '<button class="btn-sheet-close" id="sheetClose">Tutup</button>'
    + '</div></div>');

  document.body.appendChild(sheet);
  sheet.querySelectorAll('.fd-tab').forEach(function(btn){
    btn.onclick = function(){
      sheet.querySelectorAll('.fd-tab').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      sheet.querySelectorAll('.fd-panel').forEach(function(p){ p.style.display='none'; });
      sheet.querySelector('#fdp-'+btn.dataset.tab).style.display='';
    };
  });
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

/* ---------- BPJS ---------- */
async function renderBpjs(c){
  var d = await api('/bpjs');
  if(!d.status){ c.innerHTML='<div class="empty">'+(d.message||'Gagal memuat data BPJS')+'</div>'; return; }
  state.bpjsInsentif = d.mandiri_insentif || 0;

  var html = '<div class="req-actions"><button class="req-btn req-ot" id="btnKirimBpjs">＋ Kirim Bukti Bayar BPJS</button></div>';
  html += '<div class="section-title">Riwayat Pembayaran BPJS</div>';

  if(!d.history.length){
    html += '<div class="empty" style="padding:14px">Belum ada riwayat pembayaran BPJS.</div>';
  } else {
    d.history.forEach(function(b){
      var st, sub;
      if(b.pay_mode === 'kantor'){
        st = ['s-ok','Dibayar Kantor'];
        var parts = [];
        if(b.kesehatan) parts.push('Kesehatan '+rp(b.kesehatan));
        if(b.ketenagakerjaan) parts.push('Ketenagakerjaan '+rp(b.ketenagakerjaan));
        sub = parts.length ? 'Potongan: '+parts.join(' · ') : 'Ditanggung perusahaan';
      } else if(b.status === 'approved'){
        st = ['s-ok','Mandiri · Disetujui'];
        sub = 'Insentif: '+rp(b.insentif);
      } else {
        st = ['s-pending','Menunggu ACC'];
        sub = 'Bukti terkirim, menunggu persetujuan admin';
      }
      html += '<div class="req-card"><div class="req-row"><div><b>'+b.month_name+' '+b.year+'</b>'
        + '<div class="req-sub">'+sub+'</div></div><span class="sbadge '+st[0]+'">'+st[1]+'</span></div>'
        + (b.proof?'<a class="req-proof" href="'+b.proof+'" target="_blank">📎 Lihat bukti</a>':'')+'</div>';
    });
  }
  c.innerHTML = html;
  $('#btnKirimBpjs').onclick = openBpjsForm;
}

function openBpjsForm(){
  var now = new Date(), curM = now.getMonth()+1, curY = now.getFullYear();
  var monthOpts = '';
  for(var m=1;m<=12;m++){ monthOpts += '<option value="'+m+'"'+(m===curM?' selected':'')+'>'+MONTH_NAMES[m]+'</option>'; }
  var yearOpts = '';
  for(var y=curY;y>=curY-2;y--){ yearOpts += '<option value="'+y+'">'+y+'</option>'; }
  var sheet=makeSheet('<div class="sheet-title">Kirim Bukti Bayar BPJS</div><form id="bpjsForm" class="reqform">'
    + '<p class="muted" style="margin:0 0 8px">Untuk peserta bayar mandiri. Bukti akan diverifikasi admin; setelah disetujui Anda menerima insentif '+rp(state.bpjsInsentif||0)+'.</p>'
    + '<div class="form2"><div><label>Bulan</label><select name="month">'+monthOpts+'</select></div>'
    + '<div><label>Tahun</label><select name="year">'+yearOpts+'</select></div></div>'
    + '<label>Foto Bukti Pembayaran <span class="muted">(wajib)</span></label>'
    + '<input type="file" name="bpjs_proof" id="bpjsFile" accept="image/*" capture="environment" required><div id="bpjsPrev" class="filePrev"></div>'
    + '<p class="msg" id="bpjsMsg"></p><button class="btn-primary" id="bpjsSubmit" type="submit">Kirim Bukti</button>'
    + '<button class="btn-sheet-close" type="button" id="bpjsCancel">Batal</button></form>');
  $('#bpjsCancel').onclick=function(){ sheet.remove(); };
  $('#bpjsFile').onchange=function(){ previewFile(this,'#bpjsPrev'); };
  $('#bpjsForm').onsubmit=function(e){ e.preventDefault(); submitForm('/submit_bpjs', this, '#bpjsSubmit', '#bpjsMsg', function(){ sheet.remove(); }); };
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

/* ---------- LAPORAN KETIDAKCOCOKAN DATA ---------- */
document.getElementById('btnReport').addEventListener('click', openReportForm);

async function openReportForm(){
  var credits = {used:0, max:5, remaining:5};
  try { credits = await api('/get_report_credits'); } catch(e){}
  var used      = credits.used      || 0;
  var max       = credits.max       || 5;
  var remaining = credits.remaining !== undefined ? credits.remaining : (max - used);
  var exhausted = remaining <= 0;

  var creditColor = remaining > 2 ? '#22c55e' : remaining > 0 ? '#f59e0b' : '#ef4444';
  var creditHtml  =
    '<div class="rpt-credit-bar">'
    + '<span>📊 Kredit <b>Salah Input Data</b>:</span>'
    + '<span style="color:'+creditColor+';font-weight:700">'+remaining+' tersisa</span>'
    + '<span style="color:#9ca3af;font-size:11px">('+used+'/'+max+' terpakai)</span>'
    + '</div>';

  var sheet = makeSheet(
    '<div class="sheet-title">🚩 Laporkan Masalah Data</div>'
    + '<p class="rpt-hint">Temukan data yang tidak sesuai? Ceritakan masalahnya dan lampirkan screenshot atau foto sebagai bukti.</p>'
    + '<form id="rptForm" class="reqform">'
    + '<label>Kategori Masalah <span class="muted">(wajib)</span></label>'
    + '<div class="rpt-cat-box">'
    + '<label class="rpt-cat-opt'+(exhausted?' rpt-cat-disabled':'')+'" id="rptCatSalah">'
    + '<input type="radio" name="category" value="salah_input"'+(exhausted?' disabled':'')+' required>'
    + ' Salah Input Data</label>'
    + '<label class="rpt-cat-opt" id="rptCatError">'
    + '<input type="radio" name="category" value="error_aplikasi" required>'
    + ' Error Aplikasi</label>'
    + '</div>'
    + creditHtml
    + '<label>Deskripsi Masalah <span class="muted">(wajib)</span></label>'
    + '<textarea name="description" rows="4" required placeholder="Contoh: Jam masuk saya 07:30 tapi di sistem tertulis 08:15 pada tanggal 20 Juni 2026..."></textarea>'
    + '<label>Lampiran Bukti <span class="muted">(opsional · foto atau screenshot)</span></label>'
    + '<input type="file" name="report_file" id="rptFile" accept="image/*,application/pdf">'
    + '<div id="rptPrev" class="filePrev"></div>'
    + '<p class="msg" id="rptMsg"></p>'
    + '<button class="btn-primary" id="rptSubmit" type="submit">Kirim Laporan</button>'
    + '<button class="btn-sheet-close" type="button" id="rptCancel">Batal</button>'
    + '</form>'
  );
  document.getElementById('rptCancel').onclick = function(){ sheet.remove(); };
  document.getElementById('rptFile').onchange   = function(){ previewFile(this,'#rptPrev'); };
  // highlight selected category
  sheet.querySelectorAll('input[name="category"]').forEach(function(r){
    r.addEventListener('change', function(){
      sheet.querySelectorAll('.rpt-cat-opt').forEach(function(o){ o.classList.remove('rpt-cat-selected'); });
      this.closest('.rpt-cat-opt').classList.add('rpt-cat-selected');
    });
  });
  // if salah_input exhausted, auto-select error_aplikasi
  if(exhausted){
    var errRadio = sheet.querySelector('input[value="error_aplikasi"]');
    if(errRadio){ errRadio.checked = true; errRadio.closest('.rpt-cat-opt').classList.add('rpt-cat-selected'); }
  }
  document.getElementById('rptForm').onsubmit = function(e){
    e.preventDefault();
    submitForm('/submit_report', this, '#rptSubmit', '#rptMsg', function(){ sheet.remove(); });
  };
}

if('serviceWorker' in navigator){
  window.addEventListener('load', function(){ navigator.serviceWorker.register('sw.js').catch(function(){}); });
}
