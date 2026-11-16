(function(){'use strict';
let csrf='';let currentUser=null;
const $$=s=>Array.from(document.querySelectorAll(s));
const $=s=>document.querySelector(s);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const formatDate=s=>{if(!s)return '';const [y,m,d]=String(s).slice(0,10).split('-');return d+'/'+m+'/'+y};
const fmtMonth=(y,m)=>new Intl.DateTimeFormat('pt-BR',{month:'long',year:'numeric'}).format(new Date(y,m-1,1));
const dateISO=(y,m,d)=>`${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
const nowBR=()=>new Date(new Date().toLocaleString('en-US',{timeZone:'America/Sao_Paulo'}));
const today=()=>{const n=nowBR();return dateISO(n.getFullYear(),n.getMonth()+1,n.getDate())};
const hours=['08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00','17:00'];
function firstFreeDay(y,m,slots){
 const now=nowBR();const todayDate=dateISO(now.getFullYear(),now.getMonth()+1,now.getDate());
 const total=new Date(y,m,0).getDate();
 for(let d=1;d<=total;d++){
  const date=dateISO(y,m,d);if(date<todayDate||new Date(y,m-1,d).getDay()===0)continue;
  if(hours.some(h=>new Date(date+'T'+h+':00')>now&&!slots.some(s=>s.visit_date===date&&s.visit_time===h)))return date;
 }
 return '';
}
function toast(msg,error=false){let e=$('#toast');if(!e){e=document.createElement('div');e.id='toast';e.setAttribute('role','status');e.setAttribute('aria-live','polite');document.body.appendChild(e);}e.setAttribute('role',error?'alert':'status');e.setAttribute('aria-live',error?'assertive':'polite');e.textContent=msg;e.className='toast'+(error?' error':'')+' show';clearTimeout(window.__toastTimer);window.__toastTimer=setTimeout(()=>e.classList.remove('show'),6500);}
function formData(form){const x=Object.fromEntries(new FormData(form).entries());for(const c of form.querySelectorAll('input[type=checkbox]'))x[c.name]=c.checked;return x}
async function api(action,body=null,params={},retried=false){
 const qs=new URLSearchParams({action,...params});
 const url='api/index.php?'+qs.toString();
 const init={credentials:'same-origin',headers:{'Accept':'application/json'}};
 if(body!==null){init.method='POST';init.headers['Content-Type']='application/json';init.headers['X-CSRF-Token']=csrf;init.body=JSON.stringify(body);}
 let res;try{res=await fetch(url,init)}catch(e){throw Error('Sem conexão com o servidor. Verifique internet e hospedagem.');}
 let json;try{json=await res.json()}catch(e){throw Error('O servidor não retornou JSON. Confira PHP e os caminhos do site.');}
 if(!res.ok||json.ok===false){
   if(res.status===419&&body!==null&&!retried){
     // O servidor recusou a operação ANTES de processá-la. Renovamos o token uma única vez.
     await bootstrap();
     return api(action,body,params,true);
   }
   const message=res.status===419?'Sessão expirada ou cookies bloqueados. Atualize a página e tente novamente.':(json.error||`Erro HTTP ${res.status}`);
   const err=Error(message);err.status=res.status;throw err;
 }
 if(json.csrf)csrf=json.csrf;
 return json;
}
async function bootstrap(){const x=await api('bootstrap');csrf=x.csrf;return x}
function toggle(el,show){if(typeof el==='string')el=$(el);if(el)el.classList.toggle('hidden',!show)}
function switchPanel(name){$$('[data-panel]').forEach(s=>toggle(s,s.dataset.panel===name));$$('[data-nav]').forEach(b=>b.classList.toggle('active',b.dataset.nav===name));window.scrollTo({top:0,behavior:'smooth'});}
function bindNav(){document.addEventListener('click',e=>{const b=e.target.closest('[data-nav]');if(b){e.preventDefault();switchPanel(b.dataset.nav);}})}
function status(v){return `<span class="badge-status ${esc(v)}">${esc(({pending:'Em análise',confirmed:'Confirmado',declined:'Recusado',cancelled:'Cancelado'})[v]||v)}</span>`;}
function whats(phone){let d=String(phone||'').replace(/\D/g,'');if(d.length===10||d.length===11)d='55'+d;return d?'https://wa.me/'+d:'';}
function calendar(grid, y,m, slots,chosen,selectDay,adminMode=false){
 const first=new Date(y,m-1,1).getDay();const total=new Date(y,m,0).getDate();let html=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'].map(v=>`<span class="weekday">${v}</span>`).join('');
 for(let i=0;i<first;i++)html+='<span class="day empty-day"></span>';
 for(let d=1;d<=total;d++){
   const date=dateISO(y,m,d),sunday=new Date(y,m-1,d).getDay()===0,past=date<today(),dayCount=slots.filter(x=>x.visit_date===date).length;
   const now=nowBR();const freeFutureHours=hours.filter(h=>!slots.some(x=>x.visit_date===date&&x.visit_time===h)&&new Date(date+'T'+h+':00')>now).length;
   const allBusy=freeFutureHours===0;const unavailable=sunday||past||(allBusy&&!adminMode);
   html+=`<button type="button" class="day ${unavailable?'unavailable':dayCount?'busy':'active'} ${chosen===date?'selected':''}" data-day="${date}" ${unavailable?'disabled':''}><span>${d}</span>${!unavailable?'<small></small>':''}</button>`;
 }
 grid.innerHTML=html;grid.querySelectorAll('[data-day]').forEach(b=>b.addEventListener('click',()=>selectDay(b.dataset.day)));
}
function slotButtons(box,date,slots,chosen,selectTime,adminMode=false){
 if(!date){box.innerHTML='<div class="empty" style="grid-column:1/-1">Escolha um dia no calendário.</div>';return;}
 box.innerHTML=hours.map(t=>{
   const found=slots.find(s=>s.visit_date===date && s.visit_time===t),past=nowBR()>=new Date(date+'T'+t+':00');const blocked=!!found||past;
   let label=found?(found.state==='blocked'?'Bloqueado':found.state==='pending'?'Em análise':'Ocupado'):past?'Passou':'Livre';
   return `<button type="button" class="slot ${blocked?'taken':''} ${chosen===t?'selected':''}" data-time="${t}" ${!adminMode&&blocked?'disabled':''}><span>${t}</span><small>${label}</small></button>`;
 }).join('');
 box.querySelectorAll('[data-time]').forEach(b=>b.addEventListener('click',()=>selectTime(b.dataset.time)));
}
window.MPET={api,bootstrap,esc,formatDate,fmtMonth,dateISO,today,nowBR,hours,toast,formData,toggle,switchPanel,bindNav,status,whats,calendar,slotButtons,firstFreeDay};
})();
