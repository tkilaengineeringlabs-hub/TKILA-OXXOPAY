<?php
if (!defined('ABSPATH')) exit;
$tkila_status_label = $data['status'] === 'expired' ? __('Vencido', 'Spin') : __('Pendiente de pago', 'Spin');
$tkila_expiration = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $data['expires_at']);
?>
<section class="tkila-oxxo" aria-label="<?php esc_attr_e('Instrucciones de pago OXXO PAY', 'Spin'); ?>">
  <header class="tkila-oxxo__header">
    <div class="tkila-oxxo__brand"><img src="<?php echo esc_url(Spin_Plugin::plugin_url() . '/images/oxxopay.png'); ?>" alt="OXXO PAY"><span>TKILA OXXO PAY</span><h1><?php esc_html_e('Completa tu pago en OXXO', 'Spin'); ?></h1></div>
    <div class="tkila-oxxo__order"><span><?php esc_html_e('PEDIDO', 'Spin'); ?></span><strong>#<?php echo esc_html($data['order_number']); ?></strong><?php if (!$data['paid']): ?><span class="tkila-oxxo__badge"><?php echo esc_html($tkila_status_label); ?></span><?php endif; ?></div>
  </header>
  <div class="tkila-oxxo__grid">
    <main class="tkila-oxxo__payment">
      <div class="tkila-oxxo__eyebrow">OXXO PAY <i></i> MXN</div>
      <p class="tkila-oxxo__label"><?php esc_html_e('Importe a pagar', 'Spin'); ?></p>
      <p class="tkila-oxxo__amount"><span>$</span><?php echo esc_html($data['amount']); ?><small> MXN</small></p>
      <p class="tkila-oxxo__commission"><?php esc_html_e('El establecimiento podría cobrar una comisión adicional al realizar el pago.', 'Spin'); ?></p>
      <div class="tkila-oxxo__cash-title"><span></span><?php esc_html_e('PRESENTA EN CAJA', 'Spin'); ?><span></span></div>
      <?php if ($data['barcode_url'] !== ''): ?><img class="tkila-oxxo__barcode" src="<?php echo esc_url($data['barcode_url']); ?>" alt="<?php esc_attr_e('Código de barras OXXO PAY', 'Spin'); ?>"><?php endif; ?>
      <p class="tkila-oxxo__reference"><?php echo esc_html($data['reference']); ?></p>
      <button class="tkila-oxxo__copy" type="button" data-copy-ref="<?php echo esc_attr($data['reference']); ?>"><?php esc_html_e('Copiar referencia', 'Spin'); ?></button>
      <span class="tkila-oxxo__copied" aria-live="polite"></span>
      <button class="tkila-oxxo__download" type="button"><?php esc_html_e('Descargar ticket', 'Spin'); ?></button>
      <span class="tkila-oxxo__download-notice" role="status" aria-live="polite"></span>
      <div class="tkila-oxxo__expiry"><div><span><?php esc_html_e('VENCE EL', 'Spin'); ?></span><strong><?php echo esc_html($tkila_expiration); ?></strong></div><div class="tkila-oxxo__countdown" data-expires="<?php echo esc_attr($data['expires_at']); ?>" aria-live="off">--:--:--</div></div>
      <p class="tkila-oxxo__automatic"><?php esc_html_e('Confirmación automática. Tu pedido se actualizará cuando OXXO PAY confirme el pago.', 'Spin'); ?></p>
    </main>
    <aside class="tkila-oxxo__instructions">
      <section class="tkila-oxxo__where"><div><h2><?php esc_html_e('¿Dónde puedo pagar?', 'Spin'); ?></h2><p><?php esc_html_e('Puedes pagar en tiendas OXXO participantes en México.', 'Spin'); ?></p><a href="https://www.google.com/maps/search/?api=1&amp;query=OXXO" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e('Encontrar un OXXO cercano', 'Spin'); ?></span><b aria-hidden="true">↗</b></a></div><img src="<?php echo esc_url(Spin_Plugin::plugin_url() . '/images/oxxopay_b2c.svg'); ?>" alt="OXXO"></section>
      <h3 class="tkila-oxxo__steps-title"><?php esc_html_e('EN TRES PASOS', 'Spin'); ?></h3>
      <ol class="tkila-oxxo__steps"><li><b>01</b><div><strong><?php esc_html_e('Conserva tu referencia.', 'Spin'); ?></strong><p><?php esc_html_e('Copia o guarda el código para presentarlo en caja.', 'Spin'); ?></p></div></li><li><b>02</b><div><strong><?php esc_html_e('Acude a una tienda OXXO.', 'Spin'); ?></strong><p><?php esc_html_e('Presenta el código de barras o dicta la referencia completa.', 'Spin'); ?></p></div></li><li><b>03</b><div><strong><?php esc_html_e('Realiza tu pago.', 'Spin'); ?></strong><p><?php esc_html_e('Entrega el importe indicado, confirma cualquier comisión adicional y conserva el comprobante.', 'Spin'); ?></p></div></li></ol>
      <p class="tkila-oxxo__note"><?php esc_html_e('Las comisiones las determina cada establecimiento y se pagan directamente en caja.', 'Spin'); ?></p>
    </aside>
  </div>
</section>

<script defer src="<?php echo esc_url(Spin_Plugin::plugin_url() . '/assets/js/oxxo-ticket.js?ver=1.0.20'); ?>"></script>
