(function(){'use strict';
const P=window.MPET,{api,bootstrap,esc,formatDate,fmtMonth,dateISO,nowBR,hours,toast,formData,toggle,switchPanel,bindNav,status,whats,calendar,slotButtons,firstFreeDay}=P;
const $=s=>document.querySelector(s),$$=s=>Array.from(document.querySelectorAll(s));
function setCalendarStatus(msg,error=false){const el=document.querySelector('#calendarStatus');if(el){el.textContent=(error?'⚠️ ':'✓ ')+msg;el.classList.toggle('bad',error);}}
let me=null,counts={},bookings=[],clients=[],manual=[],pets=[],slots=[],selectedDate='',month={y:nowBR().getFullYear(),m:nowBR().getMonth()+1},filter='pending',calendarLoaded=false,monthRequest=0;
function showAuth(message=''){toggle('#adminAuth',true);toggle('#adminApp',false);toggle('#adminNav',false);$('#errorBanner').textContent=message;toggle('#errorBanner',!!message);const el=$('#adminLoginMessage');if(el){el.textContent=message;toggle(el,!!message);}}
function showApp(){
 toggle('#adminAuth',false);toggle('#adminApp',true);toggle('#adminNav',true);
 $('#adminEmail').textContent=me.email;
 if(!selectedDate)selectedDate=firstManageableDay();
 renderCalendar();setCalendarStatus('Carregando horários do MySQL…');
 switchPanel('calendar');
}
async function refreshData(){const [dash,c]=await Promise.all([api('admin_dashboard'),api('admin_clients')]);counts=dash.counts;bookings=dash.bookings;clients=c.clients;manual=c.manual;pets=c.pets;renderDashboard();renderRequests();renderClients();renderDayBookings();}
async function readMonth(){
 const req=++monthRequest;
 const queriedMonth=dateISO(month.y,month.m,1).slice(0,7);
 setCalendarStatus('Consultando disponibilidade atual…');
 try{
  const x=await api('availability',null,{month:queriedMonth});
  if(req!==monthRequest)return;
  if(!Array.isArray(x.slots))throw Error('Resposta de disponibilidade inválida.');
  slots=x.slots;calendarLoaded=true;
  if(!selectedDate||!selectedDate.startsWith(queriedMonth)||selectedDate<dateISO(nowBR().getFullYear(),nowBR().getMonth()+1,nowBR().getDate()))
   selectedDate=firstFreeDay(month.y,month.m,slots)||firstManageableDay();
  renderCalendar();setCalendarStatus('Agenda conectada · horários atualizados.');
 }catch(e){
  if(req!==monthRequest)return;
  calendarLoaded=false;slots=[];
  if(!selectedDate||!selectedDate.startsWith(queriedMonth))selectedDate=firstManageableDay();
  renderCalendar();setCalendarStatus('Não foi possível consultar os horários: '+e.message+'. Toque em Atualizar agenda.',true);
 }
}
function requestCard(b,compact=false){const canRespond=b.status==='pending',phoneLink=whats(b.client_phone);return `<article class="item"><div class="item-header"><div><h3>🐾 ${esc(b.pet_name)} · ${formatDate(b.visit_date)} às ${esc(String(b.visit_time).slice(0,5))}</h3><p><strong>${esc(b.client_name)}</strong> · ${esc(b.service)}</p><p>📍 ${esc(b.street)}, ${esc(b.house_number)} · ${esc(b.neighborhood)} · ${esc(b.city)}</p><p>📲 ${esc(b.client_phone)} ${phoneLink?` · <a class="inline-link" href="${esc(phoneLink)}" target="_blank" rel="noopener noreferrer">WhatsApp</a>`:''}</p></div>${status(b.status)}</div>${b.details?`<p>📝 ${esc(b.details)}</p>`:''}${canRespond&&!compact?`<div class="action-row"><button type="button" class="btn soft sm" data-status="confirmed" data-id="${b.id}">✓ Confirmar</button><button type="button" class="btn danger sm" data-status="declined" data-id="${b.id}">✕ Recusar</button></div>`:''}</article>`;}
function renderDashboard(){$('#countClients').textContent=counts.clients||0;$('#countPending').textContent=counts.pending||0;$('#countPets').textContent=counts.pets||0;$('#countConfirmed').textContent=counts.confirmed||0;$('#recentRequests').innerHTML=bookings.length?bookings.slice(0,3).map(b=>requestCard(b,true)).join(''):'<div class="empty">Não há pedidos ainda.</div>'}
function renderRequests(){const list=filter==='pending'?bookings.filter(b=>b.status==='pending'):bookings;$('#adminRequestList').innerHTML=list.length?list.map(b=>requestCard(b)).join(''):'<div class="empty">Nenhuma solicitação nesta categoria.</div>';$$('[data-filter]').forEach(b=>b.classList.toggle('active',b.dataset.filter===filter));}
function address(c){return [c.street,c.house_number,c.neighborhood,c.city].filter(Boolean).map(esc).join(' · ')}
function renderClients(){const term=$('#clientSearch').value.trim().toLocaleLowerCase('pt-BR');const all=[...clients.map(x=>({...x,_manual:false})),...manual.map(x=>({...x,_manual:true}))].filter(x=>[x.full_name,x.phone,x.email].some(k=>String(k||'').toLocaleLowerCase('pt-BR').includes(term))).sort((a,b)=>Number(b.favorite)-Number(a.favorite)||a.full_name.localeCompare(b.full_name,'pt-BR'));
 $('#adminClientList').innerHTML=all.length?all.map(c=>{const id=c.id,wh=whats(c.phone),petsOf=(!c._manual)?pets.filter(p=>Number(p.user_id)===Number(c.id)):[];return `<article class="item"><div class="item-header"><div><h3>${c.favorite?'⭐ ':'👤 '}${esc(c.full_name)}</h3><p>${esc(c.phone||'Sem telefone')} ${wh?`· <a class="inline-link" href="${esc(wh)}" target="_blank" rel="noopener noreferrer">WhatsApp</a>`:''}</p><p>${address(c)}</p><p>${c._manual?'📁 Contato manual':'🔐 Conta registrada'} · ${Number(c.monthly_frequency)||0} visita(s)/mês · ${petsOf.length} pet(s)</p></div></div><div class="action-row"><button type="button" class="btn soft sm" data-edit="${c._manual?'manual':'registered'}" data-id="${id}">${c._manual?'Editar cliente':'Ver dados e estrelas'}</button></div></article>`}).join(''):'<div class="empty">Nenhum cliente cadastrado ainda.</div>';
}
function tagModal(id){const c=clients.find(x=>Number(x.id)===id);if(!c)return;const f=$('#tagForm');f.reset();f.elements.id.value=c.id;f.elements.favorite.checked=Number(c.favorite)===1;f.elements.monthly_frequency.value=c.monthly_frequency||0;f.elements.admin_notes.value=c.admin_notes||'';$('#tagTitle').textContent=c.full_name;$('#tagDetails').innerHTML=`<p class="meta"><strong>📧</strong> ${esc(c.email)}<br><strong>📲</strong> ${esc(c.phone)}<br><strong>📍</strong> ${address(c)}<br><strong>Complemento:</strong> ${esc(c.reference||'—')}<br><strong>Observações:</strong> ${esc(c.notes||'—')}</p><div class="hintbox">Pets: ${pets.filter(p=>Number(p.user_id)===id).map(p=>esc(p.name)).join(', ')||'Nenhum registrado'}</div>`;toggle('#tagModal',true);}
function manualModal(id=0){const c=manual.find(x=>Number(x.id)===id);const f=$('#manualForm');f.reset();for(const k of ['id','full_name','phone','street','house_number','neighborhood','city','reference','notes','monthly_frequency'])f.elements[k].value=c?.[k]??(k==='monthly_frequency'?0:'');f.elements.favorite.checked=!!Number(c?.favorite||0);toggle('#deleteManual',!!c);toggle('#manualModal',true);}
function renderDayBookings(){const e=$('#adminDayBookings');if(!e)return;
 const items=selectedDate?bookings.filter(b=>b.visit_date===selectedDate&&['pending','confirmed'].includes(b.status)):[];
 e.innerHTML=!selectedDate?'<div class="empty">Selecione uma data no calendário para ver as visitas.</div>':items.length?items.map(b=>requestCard(b)).join(''):'<div class="empty">Nenhuma visita solicitada ou confirmada neste dia.</div>';
}
function firstManageableDay(){
 const now=nowBR();const min=dateISO(now.getFullYear(),now.getMonth()+1,now.getDate());
 for(let i=1;i<=new Date(month.y,month.m,0).getDate();i++){
  const d=new Date(month.y,month.m-1,i),s=dateISO(month.y,month.m,i);
  if(s<min||d.getDay()===0)continue;
  if(hours.some(h=>new Date(s+'T'+h+':00')>now))return s;
 }
 return '';
}
function dayTimeSlots(){if(!selectedDate)return [];const now=nowBR();return hours.filter(h=>new Date(selectedDate+'T'+h+':00')>now).map(h=>({hour:h,slot:slots.find(s=>s.visit_date===selectedDate&&s.visit_time===h)}));}
function renderAvailabilitySummary(){
 if(!calendarLoaded){
  $('#dayAvailabilitySummary').textContent='Aguardando conexão para mostrar a disponibilidade real.';
  $('#openDay').disabled=true;$('#closeDay').disabled=true;
  $('#monthAvailabilitySummary').textContent='A disponibilidade será exibida após a atualização.';
  return;
 }
 const day=dayTimeSlots();const free=day.filter(s=>!s.slot).length,locked=day.filter(s=>s.slot?.state==='blocked').length,busy=day.filter(s=>s.slot&&s.slot.state!=='blocked').length;
 $('#dayAvailabilitySummary').textContent=selectedDate?`${free} livre(s) · ${busy} com pedido · ${locked} bloqueado(s) nesta data`:'Selecione uma data para gerenciar.';
 $('#openDay').disabled=!selectedDate||!locked;
 $('#closeDay').disabled=!selectedDate||!free;
 const now=nowBR();let freeMonth=0,blockedMonth=0,pendingMonth=0;
 for(let i=1;i<=new Date(month.y,month.m,0).getDate();i++){
  const d=new Date(month.y,month.m-1,i);if(d.getDay()===0)continue;
  const date=dateISO(month.y,month.m,i);
  for(const hour of hours){if(new Date(date+'T'+hour+':00')<=now)continue;const r=slots.find(s=>s.visit_date===date&&s.visit_time===hour);if(!r)freeMonth++;else if(r.state==='blocked')blockedMonth++;else pendingMonth++;}
 }
 $('#monthAvailabilitySummary').innerHTML=`<span>🟢 <b>${freeMonth}</b> livres</span><span>🟠 <b>${pendingMonth}</b> com pedidos</span><span>🔒 <b>${blockedMonth}</b> bloqueados</span>`;
}
async function updateWholeDay(mode){
 if(!calendarLoaded){toast('Atualize o calendário antes de alterar a agenda.',true);return;}
 if(!selectedDate)return;
 const label=mode==='open'?'liberar os horários bloqueados':'bloquear todos os horários livres';
 if(!confirm(`Deseja ${label} do dia ${formatDate(selectedDate)}? Pedidos já existentes serão mantidos.`))return;
 const btn=$('#'+(mode==='open'?'openDay':'closeDay'));btn.disabled=true;
 try{const r=await api('admin_day_availability',{visit_date:selectedDate,mode});await readMonth();toast(r.message+' '+r.changed+' horário(s) alterado(s).');}
 catch(e){toast('Não foi possível atualizar o dia: '+e.message,true);}
 finally{renderAvailabilitySummary();}
}
function renderCalendar(){const title=$('#adminMonth');title.textContent=fmtMonth(month.y,month.m);$('#adminPrev').disabled=month.y===nowBR().getFullYear()&&month.m===nowBR().getMonth()+1;$('#adminNext').disabled=(month.y-nowBR().getFullYear())*12+(month.m-nowBR().getMonth()-1)>=12;calendar($('#adminCalendar'),month.y,month.m,slots,selectedDate,(date)=>{selectedDate=date;renderCalendar()},true);
 $('#adminCalendar').classList.toggle('offline',!calendarLoaded);
 $('#adminDayTitle').textContent=selectedDate?'Horários de '+formatDate(selectedDate):'Nenhuma data futura neste mês — avance para o próximo';
 if(calendarLoaded)slotButtons($('#adminSlots'),selectedDate,slots,'',time=>toggleBlock(time),true);
 else $('#adminSlots').innerHTML='<div class="empty" style="grid-column:1/-1">Aguardando confirmação dos horários no servidor. Use <b>Atualizar agenda</b> para consultar.</div>';
 renderAvailabilitySummary();renderDayBookings();}
async function toggleBlock(time){if(!calendarLoaded){toast('Sem conexão com a agenda.',true);return;}if(!selectedDate)return;const slot=slots.find(s=>s.visit_date===selectedDate&&s.visit_time===time);if(slot&&slot.state!=='blocked'){toast('Este horário tem pedido. Use a aba Pedidos para gerenciá-lo.',true);return;}const action=slot?'liberar':'bloquear';if(!confirm(`Deseja ${action} ${formatDate(selectedDate)} às ${time}?`))return;try{await api('admin_toggle_block',{visit_date:selectedDate,visit_time:time});await readMonth();toast('Disponibilidade atualizada.');}catch(e){toast(e.message,true)}}
async function changeStatus(id,newStatus){if(!confirm(newStatus==='confirmed'?'Confirmar esta visita para o cliente?':'Recusar o pedido e liberar o horário?'))return;try{await api('admin_status',{id,status:newStatus});await Promise.all([refreshData(),readMonth()]);toast(newStatus==='confirmed'?'Visita confirmada.':'Pedido recusado; horário liberado.')}catch(e){toast(e.message,true)}}
async function saveTag(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');b.disabled=true;try{await api('admin_tag',formData(f));toggle('#tagModal',false);await refreshData();toast('Informações do cliente salvas.')}catch(err){toast(err.message,true)}finally{b.disabled=false}}
async function saveManual(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');b.disabled=true;try{await api('admin_manual_save',formData(f));toggle('#manualModal',false);await refreshData();toast('Cliente salvo com sucesso.')}catch(err){toast(err.message,true)}finally{b.disabled=false}}
async function deleteManual(){const id=Number($('#manualForm').elements.id.value);if(!id||!confirm('Excluir definitivamente somente este cadastro manual?'))return;try{await api('admin_manual_delete',{id});toggle('#manualModal',false);await refreshData();toast('Cadastro manual excluído.')}catch(e){toast(e.message,true)}}
async function importBackup(input){const file=input.files[0];if(!file)return;try{if(file.size>2*1024*1024)throw Error('O backup precisa ter menos de 2 MB.');const obj=JSON.parse(await file.text());if(!Array.isArray(obj.clients))throw Error('Este arquivo não parece um backup de clientes da V2.');const keys=new Set(manual.map(c=>`${String(c.full_name).toLowerCase()}|${String(c.phone).replace(/\D/g,'')}`));const list=[];for(const c of obj.clients){const name=String(c.name||'').trim();if(!name)continue;const phone=String(c.phone||'');const key=`${name.toLowerCase()}|${phone.replace(/\D/g,'')}`;if(keys.has(key))continue;keys.add(key);list.push({full_name:name.slice(0,120),phone:phone.slice(0,25),street:String(c.street||'').slice(0,160),house_number:String(c.number||'').slice(0,25),neighborhood:String(c.neighborhood||'').slice(0,90),city:String(c.city||'').slice(0,100),reference:String(c.complement||'').slice(0,180),notes:String(c.notes||'').slice(0,3000),favorite:!!(c.star??c.favorite),monthly_frequency:Number(c.visits??c.visitsPerMonth??c.recurrence??0)});if(list.length>=200)break;}
 if(!list.length){toast('Nenhum novo contato para importar.');return;}if(!confirm(`Importar ${list.length} contato(s) como cadastros manuais? Logins antigos não são transferidos.`))return;
 const r=await api('admin_manual_bulk',{clients:list});await refreshData();toast(`${r.created} contato(s) importado(s).`);
 }catch(e){toast(e.message,true)}finally{input.value='';}}
async function login(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');toggle('#errorBanner',false);toggle('#adminLoginMessage',false);b.disabled=true;try{const x=await api('login',{...formData(f),audience:'admin'});if(x.user.role!=='admin'){await api('logout',{});throw Error('Sua conta não foi autorizada como administrador. Use o acesso de clientes.');}me=x.user;await signedIn();toast(f.elements.remember_me?.checked&&!x.remember_active?'Bem-vindo! O login persistente ainda precisa da atualização SQL e HTTPS.':'Bem-vindo, Mateus!',!!(f.elements.remember_me?.checked&&!x.remember_active))}catch(err){showAuth('Não foi possível entrar: '+err.message);toast(err.message,true)}finally{b.disabled=false}}
async function signedIn(){showApp();const results=await Promise.allSettled([refreshData(),readMonth()]);results.forEach((r,i)=>{if(r.status==='rejected'){if(i===1)setCalendarStatus('Não foi possível carregar a agenda. Atualize a página.',true);else toast('Erro ao carregar clientes e pedidos: '+r.reason.message,true);}});}
async function changePassword(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');b.disabled=true;try{await api('change_password',formData(f));f.reset();toast('Senha do administrador atualizada.');}catch(err){toast(err.message,true);}finally{b.disabled=false;}}
async function moveMonth(n){month.m+=n;if(month.m>12){month.m=1;month.y++}if(month.m<1){month.m=12;month.y--}selectedDate='';calendarLoaded=false;renderCalendar();await readMonth();}
function bind(){bindNav();$('#adminLogin').onsubmit=login;const logoutAndHome=async()=>{
  try{await api('logout',{});me=null;location.replace('index.php?saida=1')}
  catch(e){toast('Não foi possível sair. Tente novamente: '+e.message,true)}
 };
 $('#adminLogout').onclick=logoutAndHome;
 document.querySelectorAll('[data-admin-exit]').forEach(link=>link.addEventListener('click',async e=>{e.preventDefault();await logoutAndHome()}));
 $('#adminRetry').onclick=()=>readMonth();
 $('#adminToday').onclick=()=>{const n=nowBR();month={y:n.getFullYear(),m:n.getMonth()+1};selectedDate='';calendarLoaded=false;renderCalendar();readMonth();};$('#copyLink').onclick=async()=>{const url=new URL('cliente.php',location.href).href;try{await navigator.clipboard.writeText(url);toast('Link copiado!')}catch(e){prompt('Copie o link:',url)}};$$('[data-filter]').forEach(b=>b.onclick=()=>{filter=b.dataset.filter;renderRequests()});$('#adminRequestList').onclick=e=>{const b=e.target.closest('[data-status]');if(b)changeStatus(Number(b.dataset.id),b.dataset.status)};$('#clientSearch').oninput=renderClients;$('#newManual').onclick=()=>manualModal();$('#adminClientList').onclick=e=>{const b=e.target.closest('[data-edit]');if(!b)return;b.dataset.edit==='manual'?manualModal(Number(b.dataset.id)):tagModal(Number(b.dataset.id))};$('#tagForm').onsubmit=saveTag;$('#manualForm').onsubmit=saveManual;$('#closeTag').onclick=()=>toggle('#tagModal',false);$('#closeManual').onclick=()=>toggle('#manualModal',false);$('#deleteManual').onclick=deleteManual;$('#backupV2').onchange=e=>importBackup(e.target);$('#adminPrev').onclick=()=>moveMonth(-1);$('#adminNext').onclick=()=>moveMonth(1);$('#openDay').onclick=()=>updateWholeDay('open');$('#closeDay').onclick=()=>updateWholeDay('close');$('#adminPasswordForm').onsubmit=changePassword;$('#adminDayBookings').onclick=e=>{const b=e.target.closest('[data-status]');if(b)changeStatus(Number(b.dataset.id),b.dataset.status);};$$('.modal-shade').forEach(e=>e.onclick=a=>{if(a.target===e)toggle(e,false)});}
async function start(){bind();renderCalendar();try{await bootstrap();try{const x=await api('me');if(x.user.role!=='admin'){showAuth('Esta conta não tem acesso de administrador.');return;}me=x.user;await signedIn();}catch(err){showAuth(err.status===401?'':err.message)}}catch(err){showAuth('Não foi possível iniciar: '+err.message);}setInterval(()=>{if(me&&!document.hidden)readMonth()},15000);setInterval(()=>{if(me&&!document.hidden)refreshData().catch(e=>toast('Não foi possível atualizar os pedidos: '+e.message,true))},45000);}
window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});
start();
})();
