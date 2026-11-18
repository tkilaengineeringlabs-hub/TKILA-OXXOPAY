// Build only the independent exporter; never rebuild payment/Blocks code.
const path=require('node:path');const fs=require('node:fs');const assert=require('node:assert/strict');
const manifest=require('../resources/js/ticket/package.json');
for(const [name,version] of Object.entries(manifest.dependencies))assert.equal(require(name+'/package.json').version,version,'Unexpected build dependency: '+name);
const check=process.argv.includes('--check');
const result=require('esbuild').buildSync({entryPoints:[path.resolve(__dirname,'../resources/js/ticket/export.js')],
 outfile:path.resolve(__dirname,'../assets/js/oxxo-ticket.js'),bundle:true,nodePaths:(process.env.NODE_PATH||'').split(path.delimiter).filter(Boolean),minify:true,legalComments:'eof',
 loader:{'.css':'text','.png':'dataurl','.svg':'dataurl'},target:['es2020'],format:'iife',write:false});
const output=result.outputFiles[0];
const content=Buffer.from(Buffer.from(output.contents).toString('utf8').replace(/[ \t]+$/gm,''));
if(check){assert(fs.readFileSync(output.path).equals(content),'Exporter bundle is stale');console.log('PASS reproducible exporter bundle with pinned local libraries, CSS and logos');}
else fs.writeFileSync(output.path,content);
