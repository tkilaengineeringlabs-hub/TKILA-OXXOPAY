<?php
// Reuse only the existing template-test bootstrap (no order/provider execution).
// Define the same minimal WordPress formatting functions without running its assertions.
$bootstrap = file_get_contents(__DIR__ . '/confirmation-render.php');
$bootstrap = substr($bootstrap, 5, strpos($bootstrap, 'class Spin_Plugin') - 5);
eval($bootstrap);
class Spin_Plugin { public static function plugin_url() { return $GLOBALS['argv'][1]; } }
$data = array('order_number'=>'31172','reference'=>($argv[3] ?? '12345678901234567890'),'barcode_url'=>$argv[2],
'amount'=>'500.00','expires_at'=>1800000000,'status'=>'pending_payment','paid'=>false);
echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="' . esc_url($argv[1]) . '/assets/css/oxxo-confirmation.css"></head><body>';
include __DIR__ . '/../templates/confirmation.php';
echo '</body></html>';
