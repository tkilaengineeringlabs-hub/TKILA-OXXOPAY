(() => {
  'use strict';
  const download = async (button) => {
    const card = button.closest('.tkila-oxxo');
    const notice = card.querySelector('.tkila-oxxo__download-notice');
    button.disabled = true;
    let downloadUrl;
    try {
      const text = (selector) => card.querySelector(selector).textContent.trim();
      const canvas = document.createElement('canvas');
      canvas.width = 1200; canvas.height = 1000;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('Canvas unavailable');
      ctx.fillStyle = '#fffdfa'; ctx.fillRect(0, 0, 1200, 1000);
      ctx.fillStyle = '#28251f'; ctx.textAlign = 'center';
      ctx.font = 'bold 44px sans-serif'; ctx.fillText('TKILA OXXO PAY', 600, 90, 1100);
      ctx.font = '30px sans-serif'; ctx.fillText('Pedido ' + text('.tkila-oxxo__order strong'), 600, 155, 1100);
      ctx.font = 'bold 60px sans-serif'; ctx.fillText(text('.tkila-oxxo__amount'), 600, 260, 1100);
      // Reuse only the already-rendered image. Probe a separate canvas so an
      // external image without CORS permission can never taint the ticket.
      const barcode = card.querySelector('.tkila-oxxo__barcode');
      let barcodeIncluded = false;
      if (barcode && barcode.complete && barcode.naturalWidth && barcode.naturalHeight) {
        try {
          const safeBarcode = document.createElement('canvas');
          safeBarcode.width = barcode.naturalWidth;
          safeBarcode.height = barcode.naturalHeight;
          const probe = safeBarcode.getContext('2d');
          if (!probe) throw new Error('Canvas unavailable');
          probe.drawImage(barcode, 0, 0);
          probe.getImageData(0, 0, 1, 1); // Throws SecurityError for a tainted image.
          const scale = Math.min(1000 / barcode.naturalWidth, 220 / barcode.naturalHeight);
          const width = barcode.naturalWidth * scale, height = barcode.naturalHeight * scale;
          ctx.drawImage(safeBarcode, (1200 - width) / 2, 320 + (220 - height) / 2, width, height);
          barcodeIncluded = true;
        } catch (_) {
          // The numeric reference remains sufficient to present at the cashier.
        }
      }
      if (!barcodeIncluded) {
        ctx.font = '28px sans-serif';
        ctx.fillText('Dicta la referencia completa en caja:', 600, 450, 1100);
      }
      ctx.font = 'bold 38px monospace'; ctx.fillText(text('.tkila-oxxo__reference'), 600, 610, 1100);
      ctx.font = '28px sans-serif'; ctx.fillText('Vence: ' + text('.tkila-oxxo__expiry strong'), 600, 690, 1100);
      ctx.fillText('Presenta la referencia en caja de OXXO.', 600, 780, 1100);
      ctx.fillText(text('.tkila-oxxo__commission'), 600, 845, 1100);
      ctx.font = '22px sans-serif'; ctx.fillText('Instrucciones de pago. No es comprobante de pago.', 600, 930, 1100);
      const png = await new Promise((resolve, reject) => canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('PNG unavailable')), 'image/png'));
      downloadUrl = URL.createObjectURL(png);
      const link = document.createElement('a');
      link.href = downloadUrl; link.download = 'tkila-oxxo-ticket.png';
      document.body.appendChild(link); link.click(); link.remove();
      notice.textContent = barcodeIncluded ? 'Ticket descargado' : 'Ticket descargado con referencia numérica; el código de barras no se pudo incluir.';
    } catch (_) {
      notice.textContent = 'No se pudo descargar el ticket. Tu referencia sigue disponible; puedes copiarla.';
    } finally {
      button.disabled = false;
      if (downloadUrl) window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
    }
  };
  document.querySelectorAll('.tkila-oxxo__download').forEach((button) => {
    button.addEventListener('click', () => download(button));
  });
})();
