const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/oxxo-ticket.js', 'utf8');
async function run(mode) {
  let callback, requests = 0, clicks = 0, drawings = 0, canvases = 0;
  const reference = Object.freeze({textContent:'12345678901234567890'});
  const barcode = Object.freeze({src:'https://barcode.invalid/code.png',complete:mode!=='blocked',naturalWidth:600,naturalHeight:120});
  const notice = {textContent:''};
  const fields = {'.tkila-oxxo__reference':reference, '.tkila-oxxo__barcode':mode==='missing'?null:barcode,
    '.tkila-oxxo__download-notice':notice};
  const card = Object.freeze({querySelector:s => Object.hasOwn(fields,s)?fields[s]:Object.freeze({textContent:'sample'})});
  const button = {disabled:false, closest:()=>card, addEventListener:(event, cb)=>{assert.equal(event,'click');callback=cb;}};
  const ctx = {fillRect(){},fillText(){},drawImage(){drawings++;}};
  const document = {querySelectorAll:()=>[button],body:{appendChild(){}},createElement:tag=>{
    if(tag!=='canvas')return {click(){clicks++;},remove(){}};
    const main = canvases++ === 0;
    return {getContext:()=>mode==='canvas'&&main?null:main?ctx:{drawImage(){},getImageData(){if(mode==='tainted')throw Error('SecurityError');}},
      toBlob:cb=>cb(mode==='blob'?null:{})};
  }};
  vm.runInNewContext(source,{document,window:{setTimeout:cb=>cb()},
    fetch:()=>{requests++;throw Error('No network permitted');},
    URL:{createObjectURL:()=> 'blob:test',revokeObjectURL(){}}});
  assert.equal(canvases,0);assert.equal(clicks,0);
  await callback();
  assert.equal(requests,0);assert.equal(button.disabled,false);
  assert.equal(reference.textContent,'12345678901234567890');assert.equal(barcode.src,'https://barcode.invalid/code.png');
  if(['canvas','blob'].includes(mode)){assert.equal(clicks,0);assert.match(notice.textContent,/Tu referencia sigue disponible/);}
  else {assert.equal(clicks,1);assert.match(notice.textContent,/Ticket descargado/);assert.equal(drawings,mode==='safe'?1:0);}
  console.log('PASS no-network PNG; original fields intact:',mode);
}
(async()=>{for(const mode of ['safe','tainted','blocked','missing','canvas','blob'])await run(mode);})().catch(e=>{console.error(e);process.exit(1);});
