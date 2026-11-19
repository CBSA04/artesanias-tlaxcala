'use strict';
document.querySelector('[data-menu]')?.addEventListener('click', e => {const b=e.currentTarget,n=document.getElementById(b.getAttribute('aria-controls'));n.hidden=!n.hidden;b.setAttribute('aria-expanded',String(!n.hidden));});
document.querySelectorAll('[data-dismiss]').forEach(b=>b.addEventListener('click',()=>b.closest('.notice').remove()));
document.querySelectorAll('[data-password]').forEach(b=>b.addEventListener('click',()=>{const i=document.getElementById(b.dataset.password),show=i.type==='password';i.type=show?'text':'password';b.textContent=show?'Ocultar':'Mostrar';b.setAttribute('aria-label',show?'Ocultar contraseña':'Mostrar contraseña');}));
document.querySelectorAll('[data-autosubmit]').forEach(i=>i.addEventListener('change',()=>i.form.requestSubmit()));
document.querySelectorAll('[data-submit-once]').forEach(f=>f.addEventListener('submit',()=>{f.querySelectorAll('button[type="submit"],button:not([type])').forEach(b=>{b.disabled=true;b.dataset.label=b.textContent;b.textContent='Procesando…';});}));
window.addEventListener('pageshow',()=>document.querySelectorAll('[data-label]').forEach(b=>{b.disabled=false;b.textContent=b.dataset.label;}));
