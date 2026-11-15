// Cache APENAS a casca estática do aplicativo. Dados e autenticação exigem internet.
const CACHE='matheus-pet-v3-flat-corrigido-2';
const ASSETS=['./','./index.html','./cliente.html','./admin.html','./privacidade.html','./style.css','./config.js','./common.js','./cliente.js','./admin.js','./icon.svg','./icon-192.png','./icon-512.png','./manifest.webmanifest'];
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(c=>c.addAll(ASSETS)).then(()=>self.skipWaiting())));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('matheus-pet-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',event=>{
  const request=event.request;
  if(request.method!=='GET'||!request.url.startsWith(self.location.origin))return;
  event.respondWith(fetch(request).then(response=>{
    if(response.ok){const copy=response.clone();event.waitUntil(caches.open(CACHE).then(c=>c.put(request,copy)))}
    return response;
  }).catch(()=>caches.match(request).then(cached=>cached||Response.error())));
});
