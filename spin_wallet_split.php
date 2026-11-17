<?php
/**
 * Tkila OXXO PAY - isolated wallet remainder collection plan.
 *
 * This is a collection order for the unpaid remainder, NOT a replacement
 * invoice or a representation of the original WooCommerce product/tax lines.
 * Keep it behind staging flag until Digital FEMSA accepts this payload.
 */
if (!defined('ABSPATH')) { exit; }

final class Tkila_Oxxo_Wallet_Split
{
    public static function enabled(): bool
    {
        return defined('TKILA_OXXO_WALLET_SPLIT_STAGING') && TKILA_OXXO_WALLET_SPLIT_STAGING === true;
    }

    /**
     * Return null for non-wallet orders, otherwise a validated split plan.
     * No balance/session reads and no wallet ledger mutations.
     */
    public static function plan($order): ?array
    {
        $wallet_cents = 0;
        $wallet_lines = 0;
        foreach ($order->get_fees() as $fee) {
            $fee_cents = spin_amount_validation($fee->get_total());
            $tax_cents = spin_amount_validation($fee->get_total_tax());
            if ($fee_cents >= 0 && $tax_cents >= 0) {
                continue;
            }
            $marker = (string) $fee->get_meta('_legacy_fee_key', true);
            if ($marker === '') {
                $marker = (string) $fee->get_meta('legacy_fee_key', true);
            }
            if ($marker !== '_via_wallet_partial_payment' || ++$wallet_lines !== 1 ||
                $fee_cents > 0 || $tax_cents > 0) {
                throw new DomainException('unsupported_negative_fee');
            }
            $wallet_cents = -($fee_cents + $tax_cents);
        }

        if ($wallet_lines === 0) {
            return null;
        }
        if ($wallet_cents <= 0 || $wallet_cents > 2147483647) {
            throw new DomainException('invalid_wallet_amount');
        }

        $pending_cents = spin_amount_validation($order->get_total());
        if ($pending_cents <= 0 || $order->is_paid()) {
            throw new DomainException('no_pending_payment');
        }
        if ($pending_cents > 2147483647 || $order->get_currency() !== 'MXN') {
            throw new DomainException('unsupported_split_currency');
        }

        $gross_cents = $pending_cents + $wallet_cents;
        if ($gross_cents <= $pending_cents || $gross_cents > 2147483647) {
            throw new DomainException('invalid_wallet_reconciliation');
        }

        return [
            'pending_cents' => $pending_cents,
            'wallet_cents' => $wallet_cents,
            'gross_cents' => $gross_cents,
            'line_items' => [[
                'name' => 'Saldo pendiente pedido WooCommerce #' . (int) $order->get_id(),
                'unit_price' => $pending_cents,
                'quantity' => 1,
                'tags' => ['WooCommerce', 'Tkila OXXO PAY', 'wallet-remainder'],
                'metadata' => ['soft_validations' => true],
            ]],
            'metadata' => [
                'tkila_payment_mode' => 'wallet_remainder_collection',
                'tkila_wallet_amount_cents' => (string) $wallet_cents,
                'tkila_commercial_gross_cents' => (string) $gross_cents,
            ],
        ];
    }
}
