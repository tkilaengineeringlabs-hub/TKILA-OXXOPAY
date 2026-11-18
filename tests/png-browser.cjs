// Real Chrome canvas -> real downloaded PNG -> independent raster barcode decoder.
const assert=require('node:assert/strict');const fs=require('node:fs');const path=require('node:path');const http=require('node:http');
const {execFileSync}=require('node:child_process');const {chromium}=require('playwright');const decode=require('./decode-png.cjs');
const sharp=require('sharp');
async function bars(input){const {data,info}=await sharp(input).flatten({background:'white'}).removeAlpha().raw().toBuffer({resolveWithObject:true});const row=Array.from({length:info.width},(_,x)=>data[(Math.floor(info.height/2)*info.width+x)*info.channels]<128?'1':'0').join('');const cropped=row.replace(/^0+|0+$/g,'');const runs=cropped.match(/(.)\1*/g).map(r=>r.length);const unit=Math.min(...runs);return runs.map(n=>n/unit);}
const root=path.resolve(__dirname,'..');const artifacts=path.join(root,'tests/artifacts');fs.mkdirSync(artifacts,{recursive:true});
const origin=s=>'http://127.0.0.1:'+s.address().port;
const listen=s=>new Promise(resolve=>s.listen(0,'127.0.0.1',resolve));let browser;
assert.equal(fs.readFileSync(path.join(root,'assets/css/oxxo-confirmation.css'),'utf8'),execFileSync('git',['show','7e428d7:assets/css/oxxo-confirmation.css'],{cwd:root,encoding:'utf8'}),'Confirmation CSS must remain unchanged');
// Until release, the script cache key remains 1.0.20; only that key may be bumped.
assert.equal(fs.readFileSync(path.join(root,'templates/confirmation.php'),'utf8').replace(/\?ver=1\.0\.(20|21)/,''),execFileSync('git',['show','005698e:templates/confirmation.php'],{cwd:root,encoding:'utf8'}),'Confirmation markup must remain unchanged');
const server=http.createServer((req,res)=>{
 try {
  const url=new URL(req.url,origin(server));
  if(url.pathname==='/') {
   res.setHeader('Content-Type','text/html; charset=utf-8');
   return res.end(execFileSync('php',['tests/render-ticket-fixture.php',origin(server),'https://blocked.invalid/provider-barcode.png',url.searchParams.get('reference')||'84000045432316'],{cwd:root}));
  }
  if(!/^\/(assets|images)\/[\w/.-]+$/.test(url.pathname)||url.pathname.includes('..')){res.statusCode=404;return res.end();}
  const file=path.join(root,url.pathname);res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':'image/png');res.end(fs.readFileSync(file));
 }catch(e){res.statusCode=500;res.end(String(e));}
});
(async()=>{
 assert.deepEqual(await decode(path.join(root,'tests/fixtures/barcode/femsa-84000045432316.png')),{text:'84000045432316',format:'CODE_128'});
 console.log('PASS official FEMSA sample independently decodes as CODE_128 / 84000045432316');
 await listen(server);
 browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_BIN||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 for(const mode of ['desktop','mobile','blocked','leading-zero','long-reference','invalid','no-canvas','png-failure','corrupt-png','logo-failure']) {
  const page=await browser.newPage({acceptDownloads:true,isMobile:mode==='mobile',hasTouch:mode==='mobile',deviceScaleFactor:mode==='mobile'?3:1,viewport:mode==='mobile'?{width:390,height:844}:{width:1280,height:1000}});
  await page.route('**/*',route=>{
   const r=route.request();
   if(r.url().startsWith('https://')||(mode==='blocked'&&['image','font'].includes(r.resourceType())&&!r.url().startsWith('data:')))return route.abort();
   return route.continue();
  });
  let downloads=0;page.on('download',()=>downloads++);
  const reference=mode==='leading-zero'?'00000045432316':mode==='long-reference'?'00123456789012345678':mode==='invalid'?'8400ABC':'84000045432316';
  await page.goto(origin(server)+'/?reference='+reference);
  await page.waitForFunction(()=>document.querySelector('.tkila-oxxo__download'));
  if(['no-canvas','png-failure','corrupt-png','logo-failure'].includes(mode))await page.evaluate(mode=>{
   if(mode==='no-canvas')HTMLCanvasElement.prototype.getContext=()=>null;
   if(mode==='png-failure')HTMLCanvasElement.prototype.toBlob=function(cb){cb(null);};
   if(mode==='corrupt-png'){
    const original=HTMLCanvasElement.prototype.toBlob;
    HTMLCanvasElement.prototype.toBlob=function(cb,type){this.getContext('2d').fillStyle='white';this.getContext('2d').fillRect(0,0,this.width,this.height);return original.call(this,cb,type);};
   }
   if(mode==='logo-failure'){
    const original=HTMLImageElement.prototype.decode;
    HTMLImageElement.prototype.decode=function(){return this.src.startsWith('data:')?Promise.reject(Error('Blocked logo')):original.call(this);};
   }
  },mode);
  const selectors=['.tkila-oxxo__reference','.tkila-oxxo__barcode','.tkila-oxxo__amount','.tkila-oxxo__expiry','.tkila-oxxo__order','.tkila-oxxo__brand','.tkila-oxxo__instructions'];
  const before=await Promise.all(selectors.map(s=>page.locator(s).evaluate(el=>el.outerHTML)));
  let requests=0;const count=r=>{if(!r.url().startsWith('data:')&&!r.url().startsWith('blob:')&&r.url()!=='about:blank')requests++;};page.on('request',count);
  // Observe only the export copy, without modifying its rendering.
  await page.evaluate(()=>{
   window.ticketCopies=[];
   const original=HTMLCanvasElement.prototype.toBlob;
   HTMLCanvasElement.prototype.toBlob=function(...args){
    const frame=[...document.querySelectorAll('iframe')].find(f=>f.contentDocument?.querySelector('.tkila-oxxo'));
    if(frame){const t=frame.contentDocument.querySelector('.tkila-oxxo');window.ticketCopies.push({html:t.outerHTML,buttons:t.querySelectorAll('button,a').length,countdown:t.querySelectorAll('.tkila-oxxo__countdown').length,logos:[...t.querySelectorAll('.tkila-oxxo__brand img,.tkila-oxxo__where img')].map(i=>i.src)});}
    return original.apply(this,args);
   };
  });
  const successful=['desktop','mobile','blocked','leading-zero','long-reference'].includes(mode);
  const downloadPromise=successful?page.waitForEvent('download',{timeout:60000}):null;
  await page.getByRole('button',{name:'Descargar ticket'}).click();
  if(successful) {
   const download=await downloadPromise;assert.equal(await download.failure(),null);
   const file=path.join(artifacts,`ticket-final-${mode}.png`);await download.saveAs(file);
   assert.deepEqual(await decode(file,true),{text:reference,format:'CODE_128'});
   const bytes=fs.readFileSync(file);assert.equal(bytes.subarray(0,8).toString('hex'),'89504e470d0a1a0a');
   assert.equal(bytes.readUInt32BE(16),3444);assert(bytes.readUInt32BE(20)>1500);
   const copy=await page.evaluate(()=>window.ticketCopies.at(-1));assert(copy);assert.equal(copy.buttons,0);assert.equal(copy.countdown,0);
   assert(copy.logos.length===2&&copy.logos.every(src=>src.startsWith('data:')));
   assert(copy.html.includes(reference));assert(copy.html.includes('500.00'));assert(copy.html.includes('31172'));
   if(mode==='desktop'){const source=await page.evaluate(html=>{const doc=new DOMParser().parseFromString(html,'text/html');return doc.querySelector('.tkila-oxxo__barcode').src;},copy.html);assert.deepEqual(await bars(Buffer.from(source.split(',')[1],'base64')),await bars(path.join(root,'tests/fixtures/barcode/femsa-84000045432316.png')),'Local symbol must match provider sample bar-for-bar');console.log('PASS local generated symbol matches provider example bar-for-bar');}
   assert.equal(await page.locator('.tkila-oxxo__download-notice').textContent(),'Ticket descargado');
   console.log('PASS real high-resolution PNG, exact CODE_128 decode, embedded logos, no controls/countdown:',mode);
  }else {
   await page.waitForFunction(()=>document.querySelector('.tkila-oxxo__download-notice').textContent.includes('No se descargó'),{timeout:60000});
   assert.equal(downloads,0);console.log('PASS invalid/incomplete PNG blocked before download:',mode);
  }
  page.off('request',count);assert.equal(requests,0,'Export must issue zero network requests');
  assert.deepEqual(await Promise.all(selectors.map(s=>page.locator(s).evaluate(el=>el.outerHTML))),before);
  assert.equal(await page.locator('.tkila-oxxo__download').isDisabled(),false);
  assert.equal(await page.locator('iframe').count(),0);
  await page.close();
 }
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();server.close();});
