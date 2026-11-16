<?php
/**
 * Isolated ownership checks: no WordPress installation, API keys or remote calls.
 * Run: php tests/webhook-ownership.php
 */
define('ABSPATH', __DIR__);
class WC_Payment_Gateway {}
function wc_get_order($id) {
    return $GLOBALS['orders'][$id] ?? false;
}
class WebhookTestOrder {
    public function __construct(
        private int $id,
        private string $payment_method,
        private string $remote_id
    ) {}
    public function get_payment_method() { return $this->payment_method; }
    public function get_meta($key, $single = true) {
        return $key === 'spin-order-id' ? $this->remote_id : '';
    }
}
require __DIR__ . '/../spin_plugin.php';
function check_webhook($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS ", $message, PHP_EOL;
}
$GLOBALS['orders'] = [
    123 => new WebhookTestOrder(123, 'spin', 'ord_good'),
    124 => new WebhookTestOrder(124, 'cod', 'ord_other'),
    125 => new WebhookTestOrder(125, 'spin', ''),
];
$valid = ['id' => 'ord_good', 'metadata' => [
    'reference_id' => '123', 'payment_method' => 'Spin_Block_Gateway'
]];
check_webhook(Spin_Plugin::resolve_webhook_order($valid) === $GLOBALS['orders'][123], 'matching OXXO order');
$tests = [
    'missing remote id' => array_replace($valid, ['id' => '']),
    'mismatched remote id' => array_replace($valid, ['id' => 'ord_wrong']),
    'unrelated gateway' => array_replace($valid, ['metadata' => ['reference_id' => '124', 'payment_method' => 'Spin_Block_Gateway']]),
    'missing local reference' => array_replace($valid, ['metadata' => ['reference_id' => '125', 'payment_method' => 'Spin_Block_Gateway']]),
    'nonexistent order' => array_replace($valid, ['metadata' => ['reference_id' => '999', 'payment_method' => 'Spin_Block_Gateway']]),
    'wrong webhook method' => array_replace($valid, ['metadata' => ['reference_id' => '123', 'payment_method' => 'OtherGateway']]),
    'missing webhook metadata' => array_replace($valid, ['metadata' => []]),
    'non-numeric reference' => array_replace($valid, ['metadata' => ['reference_id' => '../123', 'payment_method' => 'Spin_Block_Gateway']]),
    'zero reference' => array_replace($valid, ['metadata' => ['reference_id' => '0', 'payment_method' => 'Spin_Block_Gateway']]),
    'overflow reference' => array_replace($valid, ['metadata' => ['reference_id' => '99999999999999999999999999999', 'payment_method' => 'Spin_Block_Gateway']]),
];
foreach ($tests as $name => $event) {
    check_webhook(Spin_Plugin::resolve_webhook_order($event) === false, $name . ' rejected');
}
