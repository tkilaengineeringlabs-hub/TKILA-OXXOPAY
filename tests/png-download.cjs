const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/oxxo-ticket.js', 'utf8');
async function run(failure) {
  let callback, requests = 0, clicks = 0, drawings = 0;
  const reference = Object.freeze({textContent:'12345678901234567890'});
  const barcode = Object.freeze({src:'https://barcode.invalid/code.png'});
  const notice = {textContent:''};
  const fields = {'.tkila-oxxo__reference':reference, '.tkila-oxxo__barcode':barcode,
    '.tkila-oxxo__download-notice':notice};
  const card = Object.freeze({querySelector:s => fields[s] || Object.freeze({textContent:'sample'})});
  const button = {disabled:false, closest:()=>card, addEventListener:(event, cb)=>{assert.equal(event,'click');callback=cb;}};
  const ctx = {fillRect(){},fillText(){},drawImage(){drawings++;}};
  const canvas = {getContext:()=> failure === 'canvas' ? null : ctx,
    toBlob:cb=>cb(failure === 'blob' ? null : {})};
  const document = {querySelectorAll:()=>[button],body:{appendChild(){}},
    createElement:tag=>tag==='canvas'?canvas:{click(){clicks++;},remove(){}}};
  vm.runInNewContext(source, {document,window:{setTimeout:cb=>cb()},
    fetch:async()=>{requests++;if(failure === 'cors')throw Error('CORS');return {ok:failure!=='http',blob:async()=>({})};},
    URL:{createObjectURL:()=> 'blob:test',revokeObjectURL(){}},
    Image:class {naturalWidth=600;naturalHeight=120;async decode(){if(failure==='decode')throw Error('decode');}}});
  assert.equal(requests,0);assert.equal(clicks,0);assert.equal(drawings,0);
  await callback();
  assert.equal(button.disabled,false);
  assert.equal(reference.textContent,'12345678901234567890');
  assert.equal(barcode.src,'https://barcode.invalid/code.png');
  if(failure){assert.equal(clicks,0);assert.match(notice.textContent,/Tu referencia sigue disponible/);}
  else {assert.equal(clicks,1);assert.equal(drawings,1);assert.equal(notice.textContent,'Ticket descargado');}
  console.log('PASS click-only PNG, original fields intact:', failure || 'success');
}
(async()=>{for(const mode of [null,'canvas','cors','http','decode','blob'])await run(mode);})().catch(e=>{console.error(e);process.exit(1);});
