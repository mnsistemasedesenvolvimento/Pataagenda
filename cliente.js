(function(){'use strict';
const P=window.MPET,{api,bootstrap,esc,formatDate,fmtMonth,dateISO,today,nowBR,hours,toast,formData,toggle,switchPanel,bindNav,status,calendar,slotButtons,firstFreeDay,calendarOffline}=P;
const $=s=>document.querySelector(s),$$=s=>Array.from(document.querySelectorAll(s));
let user=null,pets=[],bookings=[],slots=[],chosenDate='',chosenTime='',month={y:nowBR().getFullYear(),m:nowBR().getMonth()+1};
let publicSlots=[],publicDate='',publicMonth={...month},publicLoaded=false;
function setCalendarStatus(msg,error=false){const x=$('#calendarStatus');if(x){x.textContent=(error?'⚠️ ':'✓ ')+msg;x.classList.toggle('bad',error);}}
function errorBanner(msg){$('#errorBanner').textContent=msg;toggle('#errorBanner',!!msg);}
function authMessage(which,msg){const el=$(which==='signup'?'#signupMessage':'#loginMessage');if(!el)return;el.textContent=msg;toggle(el,!!msg);if(msg)el.scrollIntoView({behavior:'smooth',block:'center'});}

function showAuth(isSignup){toggle('#adminSessionNotice',false);authMessage('signup','');authMessage('login','');toggle('#publicPreview',true);toggle('#authSection',true);toggle('#clientApp',false);toggle('#clientNav',false);toggle('#signupForm',isSignup);toggle('#loginForm',!isSignup);$('#showSignup').classList.toggle('active',isSignup);$('#showLogin').classList.toggle('active',!isSignup);}
function showApp(){toggle('#adminSessionNotice',false);toggle('#publicPreview',false);toggle('#authSection',false);toggle('#clientApp',true);toggle('#clientNav',true);$('#clientGreeting').textContent=`Olá, ${user.full_name.split(/\s+/)[0]}! 🐾`;switchPanel('home');}
async function fetchPets(){pets=(await api('pets')).pets;renderPets();renderPetOptions();}
async function fetchBookings(){bookings=(await api('bookings')).bookings;renderBookings();}
function showAdminSession(){
 user=null;toggle('#publicPreview',true);toggle('#authSection',false);toggle('#clientApp',false);toggle('#clientNav',false);toggle('#adminSessionNotice',true);
}
async function exitAdminSession(){
 const button=$('#endAdminSession');button.disabled=true;
 try{await api('logout',{});location.replace('cliente.php?saida=1')}
 catch(e){button.disabled=false;toast('Não foi possível encerrar a sessão. '+e.message,true)}
}

function publicStatus(message,error=false){const el=$('#publicStatus');if(el){el.textContent=message;el.classList.toggle('bad',error);}}
function renderPublicCalendar(){
 $('#publicMonth').textContent=fmtMonth(publicMonth.y,publicMonth.m);
 $('#publicPrev').disabled=publicMonth.y===nowBR().getFullYear()&&publicMonth.m===nowBR().getMonth()+1;$('#publicNext').disabled=(publicMonth.y-nowBR().getFullYear())*12+(publicMonth.m-nowBR().getMonth()-1)>=12;
 if(publicLoaded)calendar($('#publicDays'),publicMonth.y,publicMonth.m,publicSlots,publicDate,d=>{publicDate=d;renderPublicCalendar();});
 else calendarOffline($('#publicDays'),publicMonth.y,publicMonth.m);
 $('#publicDayTitle').textContent=!publicLoaded?'Aguardando conexão com a agenda':publicDate?'Disponibilidade de '+formatDate(publicDate):'Nenhum horário livre neste mês — avance para o próximo';
 if(publicLoaded)slotButtons($('#publicSlots'),publicDate,publicSlots,'',t=>{publicStatus('Horário '+t+' livre. Crie sua conta para solicitar esse atendimento.');$('#publicGoAuth').focus();});
 else $('#publicSlots').innerHTML='<div class="empty" style="grid-column:1/-1">Os horários só aparecem após confirmar a disponibilidade com o servidor.</div>';
}
async function readPublicMonth(){const x=await api('availability',null,{month:dateISO(publicMonth.y,publicMonth.m,1).slice(0,7)});publicSlots=x.slots;publicLoaded=true;if(!publicDate)publicDate=firstFreeDay(publicMonth.y,publicMonth.m,publicSlots);renderPublicCalendar();publicStatus('Atualização automática a cada 15 segundos. Horários sujeitos à confirmação.');}
async function publicMoveMonth(delta){publicMonth.m+=delta;if(publicMonth.m>12){publicMonth.m=1;publicMonth.y++;}if(publicMonth.m<1){publicMonth.m=12;publicMonth.y--;}publicDate='';try{await readPublicMonth();}catch(e){publicLoaded=false;renderPublicCalendar();publicStatus('Não foi possível consultar o calendário: '+e.message,true);}}
async function changePassword(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');b.disabled=true;try{await api('change_password',formData(f));f.reset();toast('Senha atualizada com sucesso!');}catch(err){toast(err.message,true);}finally{b.disabled=false;}}
async function readMonth(){const x=await api('availability',null,{month:dateISO(month.y,month.m,1).slice(0,7)});slots=x.slots;if(chosenTime&&slots.some(v=>v.visit_date===chosenDate&&v.visit_time===chosenTime)){chosenTime='';toggle('#bookingBox',false);toast('O horário selecionado acabou de ficar ocupado. Escolha outro.',true);}if(!chosenDate)chosenDate=firstFreeDay(month.y,month.m,slots);renderCalendar();setCalendarStatus('Agenda atualizada.');}
function renderPets(){
 $('#petList').innerHTML=pets.length?pets.map(p=>`<article class="item"><div class="item-header"><div><h3>🐾 ${esc(p.name)}</h3><p>${esc(p.species)}${p.breed?' · '+esc(p.breed):''}</p>${p.care_notes?`<p>${esc(p.care_notes)}</p>`:''}</div><button type="button" class="btn soft sm" data-editpet="${p.id}">Editar</button></div></article>`).join(''):'<div class="empty">Você ainda não cadastrou nenhum animal. Toque em “Cadastrar pet”.</div>';
 $('#nextStep').innerHTML=!pets.length?'👆 Passo 2: cadastre seu primeiro pet para solicitar uma visita.':'🐾 Seus pets estão cadastrados! Agora abra a Agenda e escolha uma data.';
}
function renderPetOptions(){const s=$('#bookingPet'),previous=s.value;s.innerHTML='<option value="">Selecione seu pet</option>'+pets.map(p=>`<option value="${p.id}">${esc(p.name)} (${esc(p.species)})</option>`).join('');if(pets.some(p=>String(p.id)===previous))s.value=previous;}
function bookingCard(b,compact=false){const canCancel=['pending','confirmed'].includes(b.status);return `<article class="item"><div class="item-header"><div><h3>🐾 ${esc(b.pet_name)} · ${formatDate(b.visit_date)} às ${esc(String(b.visit_time).slice(0,5))}</h3><p>${esc(b.service)}</p></div>${status(b.status)}</div>${b.details?`<p>📝 ${esc(b.details)}</p>`:''}${canCancel&&!compact?`<div class="action-row"><button class="btn danger sm" type="button" data-cancel="${b.id}">Cancelar solicitação</button></div>`:''}</article>`;}
function renderBookings(){
 $('#bookingList').innerHTML=bookings.length?bookings.map(b=>bookingCard(b)).join(''):'<div class="empty">Você ainda não solicitou uma visita. Acesse a Agenda e escolha um horário livre.</div>';
 $('#recentBookings').innerHTML=bookings.length?bookings.slice(0,2).map(b=>bookingCard(b,true)).join(''):'<div class="empty">Suas solicitações aparecerão aqui.</div>';
}
function renderCalendar(){
 $('#monthTitle').textContent=fmtMonth(month.y,month.m);
 $('#prevMonth').disabled=month.y===nowBR().getFullYear() && month.m===nowBR().getMonth()+1;$('#nextMonth').disabled=(month.y-nowBR().getFullYear())*12+(month.m-nowBR().getMonth()-1)>=12;
 calendar($('#calendarDays'),month.y,month.m,slots,chosenDate,(date)=>{chosenDate=date;chosenTime='';renderCalendar();});
 $('#slotTitle').textContent=chosenDate?'Horários de '+formatDate(chosenDate):'Nenhum horário livre neste mês — avance para o próximo';
 slotButtons($('#slotButtons'),chosenDate,slots,chosenTime,(time)=>{chosenTime=time;renderSlotsSelection();});
 renderSlotsSelection();
}
function renderSlotsSelection(){
 if(chosenDate)slotButtons($('#slotButtons'),chosenDate,slots,chosenTime,(time)=>{chosenTime=time;renderSlotsSelection();});
 toggle('#bookingBox',!!chosenDate&&!!chosenTime);
 if(chosenDate&&chosenTime){$('#chosenDate').textContent=`📆 ${formatDate(chosenDate)} às ${chosenTime}`;if(!pets.length){$('#bookingPet').innerHTML='<option value="">Cadastre um pet primeiro na aba Pets</option>';}}
}
function renderProfile(){const form=$('#profileForm');['full_name','phone','street','house_number','neighborhood','city','reference','notes'].forEach(k=>form.elements[k].value=user[k]||'');$('#profileEmail').textContent=user.email;}
function petModal(p=null){const f=$('#petForm');f.reset();f.elements.id.value=p?.id||'';for(const k of ['name','species','breed','care_notes'])if(p)f.elements[k].value=p[k]||'';toggle('#removePet',!!p);toggle('#petModal',true);}
async function signedIn(u){user=u;showApp();renderProfile();const results=await Promise.allSettled([fetchPets(),fetchBookings(),readMonth()]);results.forEach((r,i)=>{if(r.status==='rejected'){if(i===2)setCalendarStatus('Não foi possível carregar a agenda. Use o botão Atualizar.',true);else toast('Falha ao carregar '+(i===0?'pets':'pedidos')+': '+r.reason.message,true);}});}
async function signin(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');errorBanner('');authMessage('login','');b.disabled=true;try{const x=await api('login',{...formData(f),audience:'client'});if(x.user.role!=='client'){throw Error('Esta conta pertence ao administrador. Saia dela antes de usar agendamentos.')}await signedIn(x.user);toast(f.elements.remember_me?.checked&&!x.remember_active?'Você entrou, mas o login persistente não foi ativado. Confira a atualização SQL e use HTTPS.':x.remember_active?'Bem-vindo! Seu acesso ficará lembrado neste celular.':'Bem-vindo de volta!',!!(f.elements.remember_me?.checked&&!x.remember_active))}catch(err){errorBanner('Não foi possível entrar: '+err.message);authMessage('login','Não foi possível entrar: '+err.message);toast(err.message,true);}finally{b.disabled=false;}}
async function signup(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');errorBanner('');authMessage('signup','');b.disabled=true;try{const x=await api('register',formData(f));await signedIn(x.user);toast(f.elements.remember_me?.checked&&!x.remember_active?'Cadastro realizado! Para lembrar o login, instale a atualização SQL e use HTTPS.':'Cadastro realizado! Agora cadastre seu pet.',!!(f.elements.remember_me?.checked&&!x.remember_active));switchPanel('pets');petModal();}catch(err){errorBanner('Não foi possível cadastrar: '+err.message);authMessage('signup','Não foi possível cadastrar: '+err.message);toast(err.message,true);}finally{b.disabled=false;}}
async function savePet(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');b.disabled=true;try{await api('pet_save',formData(f));toggle('#petModal',false);await fetchPets();toast('Seu pet foi salvo!');}catch(err){toast(err.message,true)}finally{b.disabled=false;}}
async function deletePet(){const id=Number($('#petForm').elements.id.value);if(!id||!confirm('Deseja remover este pet do cadastro?'))return;try{await api('pet_remove',{id});toggle('#petModal',false);await fetchPets();toast('Pet removido.')}catch(err){toast(err.message,true)}}
async function submitBooking(e){e.preventDefault();if(!chosenDate||!chosenTime){toast('Escolha uma data e horário.',true);return;}if(!pets.length){toast('Cadastre seu pet primeiro.',true);switchPanel('pets');return;}
 const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');b.disabled=true;
 try{await api('book',{...formData(f),visit_date:chosenDate,visit_time:chosenTime});chosenTime='';f.reset();await Promise.all([readMonth(),fetchBookings()]);toast('Solicitação enviada ao Mateus!');switchPanel('bookings');}catch(err){toast(err.message,true);await readMonth().catch(()=>{});}finally{b.disabled=false;}}
async function cancel(id){if(!confirm('Deseja cancelar este pedido? O horário será liberado.'))return;try{await api('cancel_booking',{id});await Promise.all([fetchBookings(),readMonth()]);toast('Pedido cancelado.')}catch(err){toast(err.message,true)}}
async function updateProfile(e){e.preventDefault();const f=e.currentTarget,b=e.submitter||f.querySelector('button[type=submit]');b.disabled=true;try{const x=await api('update_profile',formData(f));user=x.user;showApp();toast('Seu cadastro foi atualizado.');switchPanel('profile');}catch(err){toast(err.message,true)}finally{b.disabled=false;}}
async function moveMonth(delta){month.m+=delta;if(month.m>12){month.m=1;month.y++}if(month.m<1){month.m=12;month.y--}chosenDate='';chosenTime='';try{await readMonth()}catch(e){toast(e.message,true)}}
function bind(){bindNav();$('#showSignup').onclick=()=>{errorBanner('');showAuth(true)};$('#showLogin').onclick=()=>{errorBanner('');showAuth(false)};$('#signupForm').onsubmit=signup;$('#loginForm').onsubmit=signin;$('#newPet').onclick=()=>petModal();$('#closePet').onclick=()=>toggle('#petModal',false);$('#petModal').addEventListener('click',e=>{if(e.target===$('#petModal'))toggle('#petModal',false)});$('#petForm').onsubmit=savePet;$('#removePet').onclick=deletePet;$('#petList').onclick=e=>{const b=e.target.closest('[data-editpet]');if(b)petModal(pets.find(p=>Number(p.id)===Number(b.dataset.editpet)));};$('#bookingForm').onsubmit=submitBooking;$('#bookingList').onclick=e=>{const b=e.target.closest('[data-cancel]');if(b)cancel(Number(b.dataset.cancel));};$('#profileForm').onsubmit=updateProfile;$('#clientLogout').onclick=async()=>{try{await api('logout',{});user=null;location.replace('cliente.php?saida=1')}catch(e){toast('Não foi possível sair: '+e.message,true)}};
$('#endAdminSession').onclick=exitAdminSession;$('#prevMonth').onclick=()=>moveMonth(-1);$('#nextMonth').onclick=()=>moveMonth(1);$('#publicPrev').onclick=()=>publicMoveMonth(-1);$('#publicNext').onclick=()=>publicMoveMonth(1);$('#publicGoAuth').onclick=()=>{
 const adminNotice=$('#adminSessionNotice');
 if(!adminNotice.classList.contains('hidden')){
   adminNotice.scrollIntoView({behavior:'smooth'});$('#endAdminSession').focus({preventScroll:true});return;
 }
 showAuth(true);$('#authSection').scrollIntoView({behavior:'smooth'});
 $('#signupForm [name=full_name]').focus({preventScroll:true});
};$('#clientPasswordForm').onsubmit=changePassword;}
async function start(){bind();renderPublicCalendar();try{await bootstrap();try{const x=await api('me');if(x.user.role==='admin'){showAdminSession();await readPublicMonth().catch(e=>publicStatus('Agenda temporariamente indisponível: '+e.message,true));return}await signedIn(x.user)}catch(err){if(err.status!==401)errorBanner(err.message);showAuth(true);await readPublicMonth().catch(e=>publicStatus('Agenda temporariamente indisponível: '+e.message,true))}}catch(err){errorBanner('Falha ao iniciar. '+err.message);showAuth(true);await readPublicMonth().catch(()=>{});}setInterval(async()=>{if(document.hidden)return;if(!user){if(!$('#publicPreview').classList.contains('hidden'))await readPublicMonth().catch(e=>publicStatus('Sem conexão com a agenda.',true));return;}try{await readMonth()}catch(err){setCalendarStatus('Sem conexão com a agenda. Tente novamente.',true);}},15000);setInterval(async()=>{if(!user||document.hidden)return;try{await fetchBookings()}catch(err){}},30000);}
window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});
start();
})();
