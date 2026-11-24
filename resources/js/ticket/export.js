import JsBarcode from 'jsbarcode';
import html2canvas from 'html2canvas';
import { MultiFormatReader, DecodeHintType, BarcodeFormat, BinaryBitmap, HybridBinarizer, RGBLuminanceSource } from '@zxing/library';
import cardCss from '../../../assets/css/oxxo-confirmation.css';
import oxxoPayLogo from '../../../images/oxxopay.png';
import oxxoStoreLogo from '../../../images/oxxopay_b2c.svg';

// All dependencies, styles and official local logos are embedded at build time.
// No provider image, API, CDN, web font or network request is used for export.
function readBarcode(canvas) {
  const {width, height} = canvas;
  const pixels = canvas.getContext('2d').getImageData(0, 0, width, height).data;
  const luminance = new Uint8ClampedArray(width * height);
  for (let i = 0; i < luminance.length; i++) {
    const p = i * 4;
    luminance[i] = pixels[p + 3] < 128 ? 255 : (pixels[p] + 2 * pixels[p + 1] + pixels[p + 2]) / 4;
  }
  const reader = new MultiFormatReader();
  reader.setHints(new Map([[DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.CODE_128]], [DecodeHintType.TRY_HARDER, true]]));
  try {
    const result = reader.decodeWithState(new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(luminance, width, height))));
    if (result.getBarcodeFormat() !== BarcodeFormat.CODE_128) throw new Error('Unexpected barcode format');
    return result.getText();
  } finally { reader.reset(); }
}

async function exportTicket(button) {
  if (button.disabled) return;
  const card = button.closest('.tkila-oxxo');
  const notice = card.querySelector('.tkila-oxxo__download-notice');
  button.disabled = true;
  let frame;
  let downloadUrl;
  try {
    const reference = card.querySelector('.tkila-oxxo__reference').textContent.trim();
    if (!/^\d{1,32}$/.test(reference)) throw new Error('Invalid reference');
    const barcode = document.createElement('canvas');
    // CODE128 auto-selects the numeric subset where applicable, preserving zeros.
    // No FNC1, payment checksum, amount, prefix or extra digits are appended.
    JsBarcode(barcode, reference, {format:'CODE128', width:3, height:120, margin:30, displayValue:false, background:'#ffffff', lineColor:'#000000'});
    if (readBarcode(barcode) !== reference) throw new Error('Generated barcode mismatch');

    const ticket = card.cloneNode(true);
    ticket.querySelectorAll('button,a,.tkila-oxxo__copied,.tkila-oxxo__download-notice,.tkila-oxxo__countdown,script').forEach(el => el.remove());
    // Replace every image before attaching the copy: no external image can load.
    ticket.querySelectorAll('img').forEach(image => {
      image.removeAttribute('srcset');
      image.removeAttribute('crossorigin');
      if (image.matches('.tkila-oxxo__brand img')) image.src = oxxoPayLogo;
      else if (image.matches('.tkila-oxxo__where img')) image.src = oxxoStoreLogo;
      else if (image.matches('.tkila-oxxo__barcode')) image.src = barcode.toDataURL('image/png');
      else throw new Error('Unexpected image');
    });
    if (!ticket.querySelector('.tkila-oxxo__barcode')) {
      const image = document.createElement('img');
      image.className = 'tkila-oxxo__barcode'; image.src = barcode.toDataURL('image/png'); image.alt = 'Código de barras OXXO PAY';
      ticket.querySelector('.tkila-oxxo__reference').before(image);
    }
    ticket.style.cssText = 'width:1100px;max-width:none;margin:0;box-sizing:border-box;';
    frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden','true'); frame.tabIndex = -1;
    frame.style.cssText = 'position:fixed;left:-20000px;top:0;width:1200px;height:2400px;border:0;pointer-events:none;';
    document.body.appendChild(frame);
    const doc = frame.contentDocument;
    doc.open(); doc.write('<!doctype html><html><head><meta charset="utf-8"></head><body></body></html>'); doc.close();
    const style = doc.createElement('style');
    style.textContent = cardCss + '\nhtml,body{margin:0;background:#fffdfa}body{width:1148px;padding:24px;box-sizing:border-box}.tkila-oxxo *{box-sizing:border-box}';
    doc.head.appendChild(style);
    const wrapper = doc.createElement('div');
    wrapper.style.cssText = 'padding:24px;width:1148px;box-sizing:border-box;background:#fffdfa;';
    doc.body.style.padding = '0';
    wrapper.appendChild(doc.adoptNode(ticket));doc.body.appendChild(wrapper);
    await Promise.all([...ticket.querySelectorAll('img')].map(async image => {
      await image.decode();
      if (!image.naturalWidth || !image.naturalHeight) throw new Error('Local image unavailable');
    }));
    await doc.fonts.ready;
    const canvas = await html2canvas(wrapper, {scale:3, backgroundColor:'#fffdfa', allowTaint:false, useCORS:false, logging:false, windowWidth:1200, windowHeight:2400});
    const png = await new Promise((resolve,reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('PNG unavailable')), 'image/png'));
    // Decode the actual encoded PNG, not just the input barcode canvas.
    const bitmap = await createImageBitmap(png);
    try {
      const verified = document.createElement('canvas'); verified.width=bitmap.width;verified.height=bitmap.height;
      verified.getContext('2d').drawImage(bitmap,0,0);
      if (readBarcode(verified) !== reference) throw new Error('Final PNG barcode mismatch');
    } finally { bitmap.close(); }
    downloadUrl = URL.createObjectURL(png);
    const link = document.createElement('a');link.href=downloadUrl;link.download='cannaland-oxxo-ticket.png';
    document.body.appendChild(link);link.click();link.remove();
    notice.textContent='Ticket descargado';
  } catch (_) {
    notice.textContent='No se pudo generar y validar el código de barras. No se descargó el ticket; tu referencia sigue disponible.';
  } finally {
    frame?.remove();button.disabled=false;
    if (downloadUrl) window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
  }
}

document.querySelectorAll('.tkila-oxxo__download').forEach(button => button.addEventListener('click', () => exportTicket(button)));
