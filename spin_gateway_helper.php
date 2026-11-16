<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Title   : Spin for WooCommerce
 * Author  : Pedro Perafan
 * Url     : https://developers.digitalfemsa.io/docs/woocommerce-1
 */


function spin_build_order_metadata($data): array
{
    $metadata = array(
        'reference_id' => $data['order_id'],
        'plugin_spin_version' => $data['plugin_spin_version'],
        'plugin' => 'woocommerce',
        'woocommerce_version' => $data['woocommerce_version'],
        'payment_method' => $data['payment_method'],
    );

    if (!empty($data['customer_message'])) {
        $metadata = array_merge(
            $metadata, array(
                'customer_message' => $data['customer_message'])
        );
    }

    return $metadata;
}

function spin_build_line_items($items, $version)
{
    $line_items = array();

    foreach ($items as $item) {

        $sub_total = spin_amount_validation($item['line_subtotal']);
        $quantity = filter_var($item['qty'], FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1 || $sub_total < 0) {
            throw new DomainException('invalid_product_amount');
        }
        $productmeta = new WC_Product($item['product_id']);
        $sku         = $productmeta->get_sku();
        $unit_price  = intdiv($sub_total, $quantity);
        $item_name   = spin_item_name_validation($item['name']);


        $line_item_params = array(
            'name'        => $item_name,
            'unit_price'  => $unit_price,
            'quantity'    => $quantity,
            'tags'        => ['WooCommerce', "Spin ".$version],
            'metadata'    => array('soft_validations' => true)
        );

        if (!empty($sku)) {
            $line_item_params = array_merge(
                $line_item_params,
                array(
                    'sku' => $sku
                )
            );
        }

        $remainder = $sub_total % $quantity;
        $line_item_params['quantity'] = $quantity - $remainder;
        $line_items[] = $line_item_params;
        if ($remainder > 0) {
            $line_item_params['unit_price']++;
            $line_item_params['quantity'] = $remainder;
            $line_items[] = $line_item_params;
        }
    }

    return $line_items;
}

function spin_build_tax_lines($taxes): array
{
    $tax_lines = array();

    foreach ($taxes as $tax) {


        $tax_amount = spin_amount_validation($tax['tax_amount']);
        $tax_name    = (string)$tax['label'];
        $tax_name    = esc_html($tax_name);


        $tax_lines  = array_merge($tax_lines, array(
            array(
                'description' => $tax_name,
                'amount'      => $tax_amount
            )
        ));

        if (isset($tax['shipping_tax_amount'])) {
            $amount = spin_amount_validation($tax['shipping_tax_amount']);
            $tax_lines  = array_merge(
                $tax_lines, array(
                    array(
                        'description' => 'Shipping tax',
                        'amount'      => $amount
                    )
                )
            );
        }
    }

    return $tax_lines;
}

function spin_build_shipping_lines($data)
{
    $shipping_lines = array();

    if(!empty($data['shipping_lines'])) {
        $shipping_lines = $data['shipping_lines'];
    }

    return $shipping_lines;
}

function spin_build_discount_lines($data): array
{
    $discount_lines = array();
    if (!empty($data['discount_lines'])) {
        $discounts = $data['discount_lines'];
        foreach ($discounts as $discount) {
            $discount_lines = array_merge(
                $discount_lines,
                    array(
                        array(
                            'code' => (string) $discount['code'],
                            'amount' => $discount['amount'] ,
                            'type'=> 'coupon'
                        )
                    )
                );
            }
        }

    return $discount_lines;
}

function spin_build_shipping_contact($data): array
{
    $shipping_contact = array();

    if (!empty($data['shipping_contact'])) {
        $shipping_contact = array_merge($data['shipping_contact'], array('metadata' => array('soft_validations' => true)));

    }

    return $shipping_contact;
}

function spin_build_customer_info($data)
{
    $customer_info = array_merge($data['customer_info'], array('metadata' => array('soft_validations' => true)));

    return $customer_info;
}

function spin_build_get_fees($fees, $version): array
{
    $fees_lines = array();
    if(empty($fees)) {
        return $fees_lines;
    }

    foreach ($fees as $fee) {
        if (!is_numeric($fee->get_total()) || (float) $fee->get_total() < 0) {
            throw new DomainException('unsupported_negative_fee');
        }
        $amount = spin_amount_validation($fee->get_total());
        if ($amount === 0) {
            continue;
        }
        $fees_lines[] = array(
            'name'        => 'Comisión por cargo extra',
            'unit_price'  => $amount,
            'quantity'    => 1,
            'tags'        => ['Fee', 'WooCommerce', "Spin ".$version],
            'metadata'    => array('soft_validations' => true)
        );
    }

    return $fees_lines;
}

/**
* Bundle and format the order information
* Send as much information about the order as possible to DigitalFemsa
*/
function spin_get_request_data($order)
{
    if ($order AND $order != null)
    {
        // Discount Lines
        $order_coupons  = $order->get_items('coupon');
        $discount_lines = array();

        foreach($order_coupons as $index => $coupon) {
            $discount_lines = array_merge($discount_lines,
                array(
                    array(
                        'code'   => $coupon->get_code(),
                        'type'   => 'coupon',
                        'amount' => spin_amount_validation($coupon->get_discount())
                    )
                )
            );
        }

        //PARAMS VALIDATION
        $amountShipping = spin_amount_validation($order->get_shipping_total());

        // Shipping Lines
        $shipping_method = $order->get_shipping_method();
        if (!empty($shipping_method)) {
            $shipping_lines  = array(
                array(
                    'amount'  => $amountShipping,
                    'carrier' => $shipping_method,
                    'method'  => $shipping_method
                )
            );

            //PARAM VALIDATION
            $name      = spin_string_validation($order->get_shipping_first_name());
            $last      = spin_string_validation($order->get_shipping_last_name());
            $address1  = spin_string_validation($order->get_shipping_address_1());
            $address2  = spin_string_validation($order->get_shipping_address_2());
            $city      = spin_string_validation($order->get_shipping_city());
            $state     = spin_string_validation($order->get_shipping_state());
            $country   = spin_string_validation($order->get_shipping_country());
            $postal    = spin_post_code_validation($order->get_shipping_postcode());


            $shipping_contact = array(
                'phone'    => $order->get_billing_phone(),
                'receiver' => sprintf('%s %s', $name, $last),
                'address' => array(
                    'street1'     => $address1,
                    'street2'     => $address2,
                    'city'        => $city,
                    'state'       => $state,
                    'country'     => $country,
                    'postal_code' => $postal
                ),
            );
        } else {
            $name      = spin_string_validation($order->get_billing_first_name());
            $last      = spin_string_validation($order->get_billing_last_name());
            $address1  = spin_string_validation($order->get_billing_address_1());
            $address2  = spin_string_validation($order->get_billing_address_2());
            $city      = spin_string_validation($order->get_billing_city());
            $state     = spin_string_validation($order->get_billing_state());
            $country   = spin_string_validation($order->get_billing_country());
            $postal    = spin_post_code_validation($order->get_billing_postcode());
            $shipping_lines  = array(
                array(
                    'amount'   => 0,
                    'carrier'  => 'carrier',
                    'method'   => 'pickup'
                )
            );
            $shipping_contact = array(
                'phone'    => $order->get_billing_phone(),
                'receiver' => sprintf('%s %s', $name, $last),
                'address' => array(
                    'street1'     => $address1,
                    'street2'     => $address2,
                    'city'        => $city,
                    'state'       => $state,
                    'country'     => $country,
                    'postal_code' => $postal
                ),
            );
        }

         //PARAM VALIDATION
        $customer_name = sprintf('%s %s', $order->get_billing_first_name(), $order->get_billing_last_name());
        $phone         = sanitize_text_field($order->get_billing_phone());

        // Customer Info
        $customer_info = array(
            'name'  => $customer_name,
            'phone' => $phone,
            'email' => $order->get_billing_email()
        );
       

        $amount               = spin_validate_total($order->get_total());
        $currency             = $order->get_currency();

        $data = array(
            'order_id'             => $order->get_id(),
            'amount'               => $amount,
            'currency'             => $currency,
            'description'          => sprintf('Charge for %s', $order->get_billing_email()),
            'customer_info'        => $customer_info,
            'shipping_lines'       => $shipping_lines
        );
        if (!empty($address1) && !empty($postal)) {
            $data = array_merge($data, array('shipping_contact' => $shipping_contact));
        }
        $customer_note = $order->get_customer_note();
        if (!empty($customer_note)) {
            $data = array_merge($data, array('customer_message' => $order->get_customer_note()));
        }

        if(!empty($discount_lines)) {
            $data = array_merge($data, array('discount_lines' => $discount_lines));
        }

        return $data;
    }

    return false;
}

function spin_amount_validation($amount) : int
{
    if (!is_numeric($amount) || !is_finite((float) $amount)) {
        throw new DomainException('invalid_money');
    }
    $cents = round((float) $amount * 100, 0, PHP_ROUND_HALF_UP);
    if (!is_finite($cents) || abs($cents) > 2147483647) {
        throw new DomainException('money_out_of_range');
    }
    return (int) $cents;
}

function spin_validate_breakdown($order, array $line_items, array $discount_lines, array $tax_lines, array $shipping_lines): void
{
    $expected = spin_amount_validation($order->get_total());
    if ($expected <= 0 || $order->is_paid()) {
        throw new DomainException('no_pending_payment');
    }
    if (!in_array($order->get_currency(), array('MXN', 'USD'), true) || empty($line_items)) {
        throw new DomainException('unsupported_order');
    }
    $total = 0;
    foreach ($line_items as $line) {
        if (!is_int($line['unit_price']) || $line['unit_price'] < 0 ||
            !is_int($line['quantity']) || $line['quantity'] < 1) {
            throw new DomainException('invalid_product_amount');
        }
        $total += $line['unit_price'] * $line['quantity'];
    }
    foreach (array($tax_lines, $shipping_lines, $discount_lines) as $index => $lines) {
        foreach ($lines as $line) {
            if (!is_int($line['amount']) || $line['amount'] < 0) {
                throw new DomainException('invalid_adjustment_amount');
            }
            $total += ($index === 2 ? -1 : 1) * $line['amount'];
        }
    }
    if ($total !== $expected) {
        throw new DomainException('amount_mismatch');
    }
}

function spin_item_name_validation($item='')
{
    if((string)$item){
      return sanitize_text_field($item);
    }

    return $item;
}

function spin_string_validation($string='')
{
    return $string;
}

function spin_post_code_validation($post_code='')
{
    if(strlen($post_code) > 5){
        return substr($post_code,0, 5);
    }

    return $post_code;
}

function spin_validate_total($total='')
{
    return spin_amount_validation($total);
}

/**
 * @param int $daysToAdd
 * @return int
 * @throws Exception
 */
function spin_get_expired_at(int $daysToAdd): int
{
    $timeZone = new DateTimeZone('America/Mexico_City');
    $currentDate = new DateTime('now', $timeZone);
    $currentDate->add(new DateInterval("P{$daysToAdd}D"));
    return $currentDate->getTimestamp();
}
