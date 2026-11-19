/* Optional visual acceptance: fresh disposable --demo database only.
   npm install
   npx playwright install chromium
   TEST_URL=http://127.0.0.1:8080 npm run test:browser -- --confirm-demo
   Saves actual screenshots in docs/capturas/. */
'use strict';
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
if(!process.argv.includes('--confirm-demo'))throw Error('Use una base desechable con --demo y pase --confirm-demo.');
const base=process.env.TEST_URL||'http://127.0.0.1:8080';
const out=path.join(__dirname,'../docs/capturas');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch();const errors=[];
 try{
  const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
  async function screen(route,name){
   await page.goto(base+route);await page.locator('main').waitFor();
   for(const width of [1440,768,390,350]){
    await page.setViewportSize({width,height:950});await page.evaluate(()=>document.fonts.ready);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`Overflow: ${route} ${width}`);
    assert.equal(await page.locator('img').evaluateAll(imgs=>imgs.some(i=>i.complete&&i.naturalWidth===0)),false,`Imagen rota: ${route}`);
    if(width===1440||width===390)await page.screenshot({path:path.join(out,`${name}-${width}.png`),fullPage:true});
   }
  }
  await screen('/','catalogo');await screen('/producto/1','producto');await screen('/registro','registro');
  const stamp=Date.now();
  await page.getByLabel('Nombre completo').fill('Comprador navegador');await page.getByLabel('Correo electrónico').fill(`browser-${stamp}@demo.test`);
  await page.getByLabel('Contraseña',{exact:true}).fill('Browser2026!');await page.getByLabel('Confirma tu contraseña').fill('Browser2026!');
  await Promise.all([page.waitForURL(base+'/'),page.getByRole('button',{name:'Crear mi cuenta'}).click()]);
  await page.goto(base+'/producto/1');await page.getByRole('button',{name:'Agregar a mi selección'}).click();await page.waitForURL('**/carrito');
  await screen('/carrito','carrito');await page.getByRole('button',{name:'Confirmar mi pedido'}).click();await page.waitForURL('**/pedidos/*');
  assert.match(await page.locator('main').innerText(),/No se ha realizado un cobro/);
  await screen('/cuenta','cuenta');await screen('/pedidos','pedidos');
  await page.getByRole('button',{name:'Cerrar sesión'}).click();
  async function login(email){await page.goto(base+'/ingresar');await page.getByLabel('Correo electrónico').fill(email);await page.getByLabel('Contraseña',{exact:true}).fill('Tlaxcala2026!');await page.getByRole('button',{name:'Ingresar a mi cuenta'}).click();await page.waitForURL(/\/(vendedor|admin)$/);}
  await login('vendedor@demo.test');await screen('/vendedor','taller');await screen('/vendedor/productos/nuevo','nueva-pieza');
  await page.getByRole('button',{name:'Cerrar sesión'}).click();await login('admin@demo.test');await screen('/admin','administracion');await screen('/admin/usuarios/nuevo','nueva-cuenta');
  await page.setViewportSize({width:390,height:844});await page.getByRole('button',{name:'Abrir menú'}).click();assert.equal(await page.locator('#mobile-nav').isVisible(),true);
  assert.deepEqual(errors,[]);console.log('OK: flujo de compra, navegación por roles, imágenes y 4 tamaños; capturas en docs/capturas.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
