<?php
/**
 * Simulate the full SDK request for wallet remainder collection.
 * Runs all existing amount tests first and then enables the staging flag.
 * No real Digital FEMSA API calls or wallet ledger writes.
 */
require __DIR__ . '/amounts.php';
define('TKILA_OXXO_WALLET_SPLIT_STAGING', true);

$wallet_fee = new TestFee('-300.00', '0', true);
WC_Order::$fixture = ['fees' => [$wallet_fee], 'total' => '700.00'];
WC_Order::$mutations = [];
$GLOBALS['order_meta'] = [123 => ['_partial_pay_through_wallet_compleate' => 'wallet_txn_123']];
$GLOBALS['logs'] = [];

$gateway = new CapturingGateway();
$answer = $gateway->process_payment(123);
verify($answer['result'] === 'success', 'split: provider request simulated');
verify(count($gateway->history) === 1, 'split: exactly one provider request');
$payload = json_decode((string) $gateway->history[0]['request']->getBody(), true);
verify(count($payload['line_items']) === 1, 'split: one collection-only line');
verify($payload['line_items'][0]['unit_price'] === 70000, 'split: OXXO collects 70000 cents');
verify($payload['line_items'][0]['quantity'] === 1, 'split: collection quantity one');
verify($payload['discount_lines'] === [], 'split: wallet not mislabeled as coupon');
verify($payload['tax_lines'] === [], 'split: no fictitious provider taxes');
verify($payload['shipping_lines'] === [], 'split: no fictitious provider shipping');
verify($payload['metadata']['tkila_wallet_amount_cents'] === '30000', 'split: wallet linked in metadata');
verify($payload['metadata']['tkila_commercial_gross_cents'] === '100000', 'split: commercial gross recorded');
verify($GLOBALS['order_meta'][123]['spin-split-pending-cents'] === 70000, 'split: OXXO cents stored in order');
verify($GLOBALS['order_meta'][123]['spin-split-wallet-cents'] === 30000, 'split: wallet cents stored in order');
verify($GLOBALS['order_meta'][123]['spin-payment-mode'] === 'wallet_remainder_collection', 'split: collection mode stored');
verify(WC_Order::$mutations[0][0] === 'pending', 'split: remains pending');
echo "PASS split full request: 1000 commercial, 300 wallet, 700 OXXO\n";

$retry = new TestGateway();
verify($retry->process_payment(123)['result'] === 'success', 'split: retry reuses reference');
verify($retry->api_calls === 0, 'split: retry never creates second provider charge');
echo "PASS split retry without remote creation\n";

WC_Order::$mutations = [];
$GLOBALS['order_meta'] = [];
$no_debit = new TestGateway();
verify($no_debit->process_payment(123)['result'] === 'failure', 'split: missing wallet debit fails');
verify($no_debit->api_calls === 0, 'split: missing debit never creates reference');
verify(WC_Order::$mutations === [], 'split: missing debit leaves state unchanged');
echo "PASS split missing debit rejects provider call\n";
