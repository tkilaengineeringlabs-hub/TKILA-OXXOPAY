// Independent test harness: decode raster bytes with ZXing, never encoder metadata.
const fs=require('node:fs');const sharp=require('sharp');const z=require('@zxing/library');
module.exports=async function decode(file, code128Only=false) {
 const {data,info}=await sharp(file).flatten({background:'white'}).extend({top:40,bottom:40,left:60,right:60,background:'white'}).removeAlpha().raw().toBuffer({resolveWithObject:true});
 const gray=Uint8ClampedArray.from({length:info.width*info.height},(_,i)=>(data[i*info.channels]+2*data[i*info.channels+1]+data[i*info.channels+2])/4);
 const reader=new z.MultiFormatReader();reader.setHints(new Map([[z.DecodeHintType.TRY_HARDER,true], ...(code128Only?[[z.DecodeHintType.POSSIBLE_FORMATS,[z.BarcodeFormat.CODE_128]]]:[])]));
 const result=reader.decodeWithState(new z.BinaryBitmap(new z.HybridBinarizer(new z.RGBLuminanceSource(gray,info.width,info.height))));
 return {text:result.getText(),format:z.BarcodeFormat[result.getBarcodeFormat()]};
};
if(require.main===module)module.exports(process.argv[2]).then(r=>console.log(JSON.stringify(r))).catch(e=>{console.error(e);process.exit(1);});
