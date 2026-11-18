// Real Chromium canvas, CORS enforcement, toBlob and downloaded-file checks.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname,'..');
const artifacts = path.join(root,'tests/artifacts');fs.mkdirSync(artifacts,{recursive:true});
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="120"><rect width="600" height="120" fill="white"/>'+Array.from({length:80},(_,i)=>`<rect x="${20+i*7}" y="10" width="${i%3+1}" height="100"/>`).join('')+'</svg>';
const serve = server=>new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const origin = server=>'http://127.0.0.1:'+server.address().port;
let browser;
const foreign = http.createServer((req,res)=>{res.setHeader('Content-Type','image/svg+xml');res.end(svg);});
let oldMode = false;
const oldScript = execFileSync('git',['show','005698e:assets/js/oxxo-ticket.js'],{cwd:root});
assert.equal(fs.readFileSync(path.join(root,'assets/css/oxxo-confirmation.css'),'utf8'),execFileSync('git',['show','005698e:assets/css/oxxo-confirmation.css'],{cwd:root,encoding:'utf8'}),'Page CSS must remain unchanged');
assert.equal(fs.readFileSync(path.join(root,'templates/confirmation.php'),'utf8').replace('?ver=1.0.20',''),execFileSync('git',['show','005698e:templates/confirmation.php'],{cwd:root,encoding:'utf8'}),'Only export script cache version may change in the template');
const local = http.createServer((req,res)=>{
 try {
  const url = new URL(req.url,origin(local));
  if(url.pathname==='/') {
    res.setHeader('Content-Type','text/html; charset=utf-8');
    return res.end(execFileSync('php',['tests/render-ticket-fixture.php',origin(local),url.searchParams.has('same')?origin(local)+'/barcode.svg':origin(foreign)+'/barcode.svg'],{cwd:root}));
  }
  if(url.pathname==='/barcode.svg'){res.setHeader('Content-Type','image/svg+xml');return res.end(svg);}
  if(url.pathname==='/assets/js/oxxo-ticket.js'&&oldMode){res.setHeader('Content-Type','text/javascript');return res.end(oldScript);}
  if(!/^\/(assets|images)\/[\w/.-]+$/.test(url.pathname)||url.pathname.includes('..')){res.statusCode=404;return res.end();}
  const file = path.join(root,url.pathname);
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':'image/png');res.end(fs.readFileSync(file));
 }catch(error){res.statusCode=500;res.end(String(error));}
});
(async()=>{
 await serve(foreign);await serve(local);
 browser = await chromium.launch({headless:true,executablePath:process.env.CHROME_BIN||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 const page = await browser.newPage({acceptDownloads:true});
 const errors=[];page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
 oldMode=true;
 await page.goto(origin(local));
 assert.equal(await page.locator('.tkila-oxxo__barcode').evaluate(i=>i.complete&&i.naturalWidth>0),true);
 await page.getByRole('button',{name:'Descargar ticket'}).click();
 await page.waitForFunction(()=>document.querySelector('.tkila-oxxo__download-notice').textContent.includes('No se pudo'));
 assert(errors.some(e=>/CORS|Access-Control-Allow-Origin/.test(e)),errors.join('\n'));
 fs.writeFileSync(path.join(artifacts,'cors-reproduction.txt'),errors.join('\n'));
 console.log('PASS v1.0.19 reproduction: image visible, fetch rejected by real browser CORS, no PNG');
 oldMode=false;
 for(const mode of ['tainted','blocked','safe','mobile']) {
  if(mode==='blocked')await page.route('**/*',route=>['image','font'].includes(route.request().resourceType())?route.abort():route.continue());
  if(mode==='mobile')await page.setViewportSize({width:390,height:844});
  await page.goto(origin(local)+(mode==='safe'?'/?same=1':''));
  const selectors=['.tkila-oxxo__reference','.tkila-oxxo__barcode','.tkila-oxxo__amount','.tkila-oxxo__expiry','.tkila-oxxo__order'];
  const before=await Promise.all(selectors.map(s=>page.locator(s).evaluate(el=>el.outerHTML)));
  let requests=0;const count=()=>requests++;page.on('request',count);
  const downloadPromise=page.waitForEvent('download');
  await page.getByRole('button',{name:'Descargar ticket'}).click();
  const download=await downloadPromise;assert.equal(await download.failure(),null);
  const file=path.join(artifacts,`ticket-${mode}.png`);await download.saveAs(file);
  page.off('request',count);assert.equal(requests,0,'Export must issue no requests');
  const bytes=fs.readFileSync(file);
  assert.equal(bytes.subarray(0,8).toString('hex'),'89504e470d0a1a0a');
  assert.equal(bytes.readUInt32BE(16),1200);assert.equal(bytes.readUInt32BE(20),1000);
  const decoded=await page.evaluate(async data=>{
    const blob=await (await fetch('data:image/png;base64,'+data)).blob();
    const image=await createImageBitmap(blob);const c=document.createElement('canvas');c.width=image.width;c.height=image.height;
    const ctx=c.getContext('2d');ctx.drawImage(image,0,0);
    const dark=(x,y,w,h)=>{const pixels=ctx.getImageData(x,y,w,h).data;let count=0;for(let i=0;i<pixels.length;i+=4)if(pixels[i]<100&&pixels[i+1]<100&&pixels[i+2]<100)count++;return count;};
    return {width:image.width,height:image.height,reference:dark(50,565,1100,55),amount:dark(50,195,1100,75),barcode:dark(50,320,1100,220)};
  },bytes.toString('base64'));
  assert(decoded.reference>100&&decoded.amount>100,'PNG must contain actual painted text');
  assert(decoded.barcode>100,'PNG must contain barcode or numeric-reference guidance');
  const after=await Promise.all(selectors.map(s=>page.locator(s).evaluate(el=>el.outerHTML)));
  assert.deepEqual(after,before,'Visible confirmation must remain unchanged');
  const notice=await page.locator('.tkila-oxxo__download-notice').textContent();
  assert.equal(notice,mode==='safe'?'Ticket descargado':'Ticket descargado con referencia numérica; el código de barras no se pudo incluir.');
  console.log('PASS real Chromium PNG decode, painted data, download, zero export requests, confirmation intact:',mode);
  if(mode==='blocked')await page.unroute('**/*');
 }
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();local.close();foreign.close();});
