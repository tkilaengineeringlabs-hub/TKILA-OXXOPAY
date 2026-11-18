(() => {
  document.querySelectorAll('.tkila-oxxo__copy').forEach((button) => {
    button.addEventListener('click', async () => {
      const output = button.parentElement.querySelector('.tkila-oxxo__copied');
      try {
        await navigator.clipboard.writeText(button.dataset.copyRef || '');
        output.textContent = 'Referencia copiada';
      } catch (_) {
        const field = document.createElement('textarea');
        field.value = button.dataset.copyRef || '';
        field.setAttribute('readonly', '');
        field.style.position = 'fixed'; field.style.opacity = '0';
        document.body.appendChild(field); field.select();
        const copied = document.execCommand('copy'); field.remove();
        output.textContent = copied ? 'Referencia copiada' : 'No se pudo copiar';
      }
    });
  });
  document.querySelectorAll('.tkila-oxxo__countdown').forEach((counter) => {
    const expiry = Number(counter.dataset.expires) * 1000;
    const update = () => {
      const left = Math.max(0, expiry - Date.now());
      const hours = Math.floor(left / 3600000);
      const minutes = Math.floor((left % 3600000) / 60000);
      const seconds = Math.floor((left % 60000) / 1000);
      counter.classList.toggle('is-urgent', left > 0 && left < 3600000);
      counter.classList.toggle('is-expired', left === 0);
      counter.textContent = left ? `${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}` : 'Referencia vencida';
    };
    if (Number.isFinite(expiry) && expiry > 0) { update(); window.setInterval(update, 1000); }
    else counter.textContent = '';
  });
})();
