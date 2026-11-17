<?php
/**
 * Isolated partial-wallet amount validation; no provider credentials.
 * Run: php tests/split-wallet.php
 */
define('ABSPATH', __DIR__);
require __DIR__ . '/../spin_gateway_helper.php';
require __DIR__ . '/../spin_wallet_split.php';

function spin_split_check($condition, $name) {
    if (!$condition) { throw new RuntimeException('FAIL ' . $name); }
    echo 'PASS ', $name, PHP_EOL;
}
class SplitFee {
    public function __construct(private string $amount, private string $tax = '0', private string $marker = '') {}
    public function get_total() { return $this->amount; }
    public function get_total_tax() { return $this->tax; }
    public function get_meta($key, $single = true) {
        return $key === '_legacy_fee_key' ? $this->marker : '';
    }
}
class SplitOrder {
    public function __construct(private string $total, private array $fees, private string $currency = 'MXN', private bool $paid = false) {}
    public function get_fees() { return $this->fees; }
    public function get_total() { return $this->total; }
    public function get_id() { return 123; }
    public function get_currency() { return $this->currency; }
    public function is_paid() { return $this->paid; }
}
$wallet = '_via_wallet_partial_payment';
$plan = Tkila_Oxxo_Wallet_Split::plan(new SplitOrder('700.00', [new SplitFee('-300.00', '0', $wallet)]));
spin_split_check($plan['pending_cents'] === 70000, 'OXXO remainder is 700 MXN');
spin_split_check($plan['wallet_cents'] === 30000, 'TeraWallet portion is 300 MXN');
spin_split_check($plan['gross_cents'] === 100000, 'commercial gross is 1000 MXN');
$pro = Tkila_Oxxo_Wallet_Split::plan(new SplitOrder('650.00', [
    new SplitFee('-300.00', '0', $wallet),
    new SplitFee('-50.00', '0', '_wallet_pay_discount'),
]));
spin_split_check($pro['pending_cents'] === 65000, 'Pro discount leaves OXXO remainder at 650 MXN');
spin_split_check($pro['wallet_cents'] === 30000, 'Pro discount never counts as wallet debit');
spin_split_check($pro['pro_discount_cents'] === 5000, 'Pro discount tracked separately');
spin_split_check($pro['before_pro_discount_cents'] === 100000, 'original pre-discount commerce amount tracked');
spin_split_check($plan['line_items'][0]['unit_price'] === 70000, 'provider collection item only for remainder');
spin_split_check($plan['metadata']['tkila_payment_mode'] === 'wallet_remainder_collection', 'linked collection metadata');

$taxed = Tkila_Oxxo_Wallet_Split::plan(new SplitOrder('700.00', [new SplitFee('-270.00', '-30.00', $wallet)]));
spin_split_check($taxed['wallet_cents'] === 30000, 'wallet tax component included exactly once');
spin_split_check(Tkila_Oxxo_Wallet_Split::plan(new SplitOrder('1000', [new SplitFee('5')])) === null, 'nonwallet order unaffected');

function split_reject($name, $order) {
    try { Tkila_Oxxo_Wallet_Split::plan($order); }
    catch (DomainException $error) { echo 'PASS ', $name, ': ', $error->getMessage(), PHP_EOL; return; }
    throw new RuntimeException('FAIL ' . $name);
}
split_reject('unknown negative fee', new SplitOrder('700', [new SplitFee('-300', '0', 'other')]));
split_reject('two wallet fees', new SplitOrder('700', [new SplitFee('-100', '0', $wallet), new SplitFee('-200', '0', $wallet)]));
split_reject('zero remainder', new SplitOrder('0', [new SplitFee('-300', '0', $wallet)]));
split_reject('already paid', new SplitOrder('700', [new SplitFee('-300', '0', $wallet)], 'MXN', true));
split_reject('unsupported split currency', new SplitOrder('700', [new SplitFee('-300', '0', $wallet)], 'USD'));

split_reject('Pro discount without wallet', new SplitOrder('950', [new SplitFee('-50', '0', '_wallet_pay_discount')]));
