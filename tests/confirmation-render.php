<?php
define('ABSPATH', __DIR__);
function __($text, $domain = null) { return $text; }
function esc_attr__($text, $domain = null) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr_e($text, $domain = null) { echo htmlspecialchars($text, ENT_QUOTES); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_html_e($text, $domain = null) { echo htmlspecialchars($text, ENT_QUOTES); }
function esc_url($url) { return htmlspecialchars($url, ENT_QUOTES); }
function sanitize_file_name($name) { return preg_replace('/[^A-Za-z0-9._-]/', '', $name); }
function get_option($key) { return $key === 'date_format' ? 'Y-m-d' : 'H:i'; }
function wp_date($format, $timestamp) { return date($format, $timestamp); }
class Spin_Plugin { public static function plugin_url() { return 'https://shop.invalid/plugin'; } }

$data = array(
    'order_number' => '31172', 'reference' => '12345678901234567890',
    'barcode_url' => 'https://api.invalid/barcode.png', 'amount' => '500.00',
    'expires_at' => time() + 3600, 'status' => 'pending_payment', 'paid' => false,
);
ob_start();
include __DIR__ . '/../templates/confirmation.php';
$html = ob_get_clean();
foreach (array('PEDIDO', '#31172', '500.00', '12345678901234567890', 'https://api.invalid/barcode.png', 'VENCE EL', 'Copiar referencia', 'Encontrar un OXXO cercano', 'Descargar ticket', '/assets/js/oxxo-ticket.js', 'tkila-oxxo__download-notice') as $expected) {
    if (strpos($html, $expected) === false) { fwrite(STDERR, "FAIL pending confirmation includes $expected\n"); exit(1); }
}
if (strpos($html, 'Pagado') !== false) { fwrite(STDERR, "FAIL pending confirmation must not claim paid\n"); exit(1); }
echo "PASS pending OXXO order renders reference, barcode, amount, expiry and actions\n";

$data['paid'] = true;
ob_start();
include __DIR__ . '/../templates/confirmation.php';
$paid_html = ob_get_clean();
if (strpos($paid_html, '#31172') === false || strpos($paid_html, 'Pagado') !== false) { fwrite(STDERR, "FAIL paid order confirmation stays clean\n"); exit(1); }
echo "PASS paid OXXO order retains ticket without Pagado badge\n";
