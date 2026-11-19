// Verify the provider-issued evidence independently from the local encoder.
const assert=require('node:assert/strict');const fs=require('node:fs');const crypto=require('node:crypto');const decode=require('./decode-png.cjs');
(async()=>{
 const file='tests/fixtures/barcode/femsa-84000045432316.png';
 const manifest=fs.readFileSync('tests/fixtures/barcode/README.md','utf8');
 assert(manifest.includes(crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex')));
 assert.deepEqual(await decode(file),{text:'84000045432316',format:'CODE_128'});
 console.log('PASS provider-issued reference + image: SHA-256 and independent CODE_128 identification');
})().catch(e=>{console.error(e);process.exit(1);});
