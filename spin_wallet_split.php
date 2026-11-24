<?php
/**
 * Cannaland OXXO PAY - isolated wallet remainder collection plan.
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
        $discount_cents = 0;
        $discount_lines = 0;
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
            if ($fee_cents > 0 || $tax_cents > 0) {
                throw new DomainException('unsupported_negative_fee');
            }
            if ($marker === '_via_wallet_partial_payment' && ++$wallet_lines === 1) {
                $wallet_cents = -($fee_cents + $tax_cents);
            } elseif ($marker === '_wallet_pay_discount' && ++$discount_lines === 1) {
                // Pro discount is a price reduction, NOT a wallet ledger payment.
                $discount_cents = -($fee_cents + $tax_cents);
            } else {
                throw new DomainException('unsupported_negative_fee');
            }
        }

        if ($wallet_lines === 0) {
            if ($discount_lines !== 0) {
                throw new DomainException('pro_discount_without_wallet_unsupported');
            }
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
        if ($gross_cents <= $pending_cents || $gross_cents + $discount_cents > 2147483647) {
            throw new DomainException('invalid_wallet_reconciliation');
        }

        return [
            'pending_cents' => $pending_cents,
            'wallet_cents' => $wallet_cents,
            'gross_cents' => $gross_cents,
            'pro_discount_cents' => $discount_cents,
            'before_pro_discount_cents' => $gross_cents + $discount_cents,
            'line_items' => [[
                'name' => 'Saldo pendiente pedido WooCommerce #' . (int) $order->get_id(),
                'unit_price' => $pending_cents,
                'quantity' => 1,
                'tags' => ['WooCommerce', 'Cannaland OXXO PAY', 'wallet-remainder'],
                'metadata' => ['soft_validations' => true],
            ]],
            'metadata' => [
                'tkila_payment_mode' => 'wallet_remainder_collection',
                'tkila_wallet_amount_cents' => (string) $wallet_cents,
                'tkila_commercial_gross_cents' => (string) $gross_cents,
                'tkila_pro_discount_cents' => (string) $discount_cents,
            ],
        ];
    }
}
