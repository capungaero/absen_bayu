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
    else await renderProfile(c);
  }catch(err){ c.innerHTML='<div class="empty">'+(err.message||'Terjadi kesalahan')+'</div>'; }
  loader(false);
}

async function renderSchedule(c){
  var d = await api('/schedule?month='+state.schedMonth+'&year='+state.schedYear);
  if(!d.status){ c.innerHTML='<div class="empty">'+(d.message||'Gagal memuat jadwal')+'</div>'; return; }
  var html = '<div class="period-nav"><button id="pPrev">‹</button><div class="label">'+d.month_name+' '+d.year+'</div><button id="pNext">›</button></div>';
  var badgeMap={present:['b-present','Hadir'],late:['b-late','Telat'],absent:['b-absent','Alpha'],permit:['b-permit','Izin'],off:['b-off','Libur'],scheduled:['b-scheduled','Jadwal'],none:['b-none','-']};
  d.days.forEach(function(x){
    var b=badgeMap[x.status]||badgeMap.none;
    var sub = x.time ? x.time : x.name;
    if(x.entry) sub = 'Masuk '+x.entry+(x.out?' · Pulang '+x.out:'');
    var dd = x.date.slice(8,10);
    html += '<div class="day-row '+(x.status==='off'?'day-off':'')+'">'
      + '<div class="day-date"><div class="d">'+dd+'</div><div class="w">'+x.day.slice(0,3)+'</div></div>'
      + '<div class="day-main"><div class="day-shift">'+x.code+'</div><div class="day-sub">'+(sub||'-')+'</div></div>'
      + '<div class="badge '+b[0]+'">'+b[1]+'</div></div>';
  });
  c.innerHTML = html;
  $('#pPrev').onclick=function(){ shiftMonth(-1); };
  $('#pNext').onclick=function(){ shiftMonth(1); };
}
function shiftMonth(n){
  state.schedMonth += n;
  if(state.schedMonth<1){ state.schedMonth=12; state.schedYear--; }
  if(state.schedMonth>12){ state.schedMonth=1; state.schedYear++; }
  render();
}

async function renderPayroll(c){
  var d = await api('/payroll?year='+state.payYear);
  if(!d.status){ c.innerHTML='<div class="empty">'+(d.message||'Gagal memuat gaji')+'</div>'; return; }
  var html = '<div class="period-nav"><button id="yPrev">‹</button><div class="label">Tahun '+d.year+'</div><button id="yNext">›</button></div>';
  if(!d.slips.length){ html += '<div class="empty">Belum ada slip gaji final untuk tahun ini.</div>'; }
  d.slips.forEach(function(s){
    html += '<div class="card">';
    html += '<div class="slip-thp"><div class="lbl">Take Home Pay</div><div class="amt">'+rp(s.thp)+'</div><div class="mo">'+s.month_name+' '+s.year+'</div></div>';
    html += '<div class="sub-head">Pendapatan</div>';
    s.pendapatan.forEach(function(p){ if(p.value) html+='<div class="kv pos"><span>'+p.label+'</span><span class="v">'+rp(p.value)+'</span></div>'; });
    html += '<div class="kv total"><span>Total Pendapatan + Bonus</span></div>';
    html += '<div class="sub-head">Potongan</div>';
    var anyCut=false;
    s.potongan.forEach(function(p){ if(p.value){ anyCut=true; html+='<div class="kv neg"><span>'+p.label+'</span><span class="v">- '+rp(p.value)+'</span></div>'; } });
    if(!anyCut) html+='<div class="kv"><span>Tidak ada potongan</span><span class="v">'+rp(0)+'</span></div>';
    html += '<div class="kv total"><span>Total Potongan</span><span class="v" style="color:var(--red)">- '+rp(s.total_potongan)+'</span></div>';
    html += '<div class="chips"><div class="chip"><div class="n">'+s.kehadiran.hadir+'</div><div class="l">Hadir</div></div>'
      + '<div class="chip"><div class="n">'+s.kehadiran.telat+'</div><div class="l">Telat</div></div>'
      + '<div class="chip"><div class="n">'+s.kehadiran.lembur_jam+'</div><div class="l">Jam Lembur</div></div></div>';
    html += '</div>';
  });
  c.innerHTML = html;
  $('#yPrev').onclick=function(){ state.payYear--; render(); };
  $('#yNext').onclick=function(){ state.payYear++; render(); };
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
