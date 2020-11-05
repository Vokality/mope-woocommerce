<?php

/*
Plugin Name: Payment Gateway for Mopé on WooCommerce
Plugin URI: https://github.com/Vokality/mope-php
Description: A Mopé Gateway Plugin for WooCommerce
Version: 1.0.4
Author: Vokality LLC
Author URI: https://github.com/Vokality
Requires at least: 5.1
Requires PHP: 7.2
License: GPLv2 or later
*/

add_filter('woocommerce_payment_gateways', 'mope_add_gateway_class');
function mope_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Mope_Gateway';
    return $gateways;
}


add_action('plugins_loaded', 'mope_init_gateway_class');
function mope_init_gateway_class()
{

    class WC_Mope_Gateway extends WC_Payment_Gateway
    {
        private $test_mode;
        private $mope_api_key;
        private $mope_api_base_url;
        private $transaction_description;
        private $custom_wc_request_config;

        public function __construct()
        {
            $this->id = 'mope';
            $this->mope_api_base_url = "https://api.mope.sr/api/";
            $this->icon = esc_url(plugins_url('assets/mope_logo.png', __FILE__));
            $this->has_fields = true;
            $this->method_title = 'Mopé Payment Gateway';
            $this->method_description = 'Pay quickly and securely with Mopé Mobile wallets.';

            $this->supports = array(
                'products'
            );

            $this->init_form_fields();

            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->test_mode = 'yes' === $this->get_option('test_mode');
            $this->mope_api_key = $this->test_mode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->transaction_description = $this->get_option('transaction_description');
            $this->custom_wc_request_config = array(
                'timeout' => 5,
                'headers' => array(
                    'User-Agent' => 'Mopé Php Client',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->mope_api_key
                )
            );

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_Gateway_Mope', home_url('/')));
            add_action('woocommerce_api_mope', array($this, 'mope_callback'));

        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable',
                    'label' => 'Enable/disable Mopé Payment Gateway',
                    'type' => 'checkbox',
                    'description' => 'Make this payment method available to your users.',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'Title for this payment method displayed at checkout.',
                    'default' => 'Mopé Mobile Wallet',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'A description of this payment gateway',
                    'default' => 'Pay quickly and securely with your Mopé Mobile wallet.',
                ),
                'test_mode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'test_private_key' => array(
                    'title' => 'Test Mopé Key',
                    'type' => 'password',
                ),
                'private_key' => array(
                    'title' => 'Live Mopé Key',
                    'type' => 'password'
                ),
                'transaction_description' => array(
                    'title' => 'Transaction Description',
                    'type' => 'text',
                    'default' => site_url(),
                    'description' => 'Text that will be used to describe the transaction in a buyers Mopé Wallet',
                )
            );
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order_data = $order->get_data();
            $order_id = $order_data['id'];
            $order_total1 = preg_replace("/(?<!\d)([,.])(?!\d)/", "", $order->get_total());
            $order_total_formatted = number_format($order_total1, 2, '', '');

            # use ?wc-api=callback because it works with all permalink setups
            # https://github.com/woocommerce/woocommerce/issues/23142#issuecomment-476604300
            $returnURL = site_url() . '?wc-api=mope&order_id=' . $order_id;

            $data = array(
                'description' => $this->transaction_description,
                'amount' => $order_total_formatted,
                'order_id' => $order_id,
                'currency' => 'SRD',
                'redirect_url' => $returnURL
            );

            $data_string = json_encode($data);
            $post_data = array_merge(array('body' => $data_string), $this->custom_wc_request_config);
            $response = wp_remote_post($this->mope_api_base_url . 'shop/payment_request', $post_data);
            if (is_wp_error($response)) {
                wc_add_notice("An unexpected error has occurred.", 'error');
                return array(
                    'result' => 'error',
                    'redirect' => wc_get_checkout_url(),
                );
            }

            $response_status = wp_remote_retrieve_response_code($response);
            if (intval($response_status) != 201) {
                wc_add_notice("An error occurred communicating with Mopé. Please try again later.", 'error');
                return array(
                    'result' => 'error',
                    'redirect' => wc_get_checkout_url(),
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            $order->update_meta_data('mope_payment_id', $data->id);
            $order->save();
            return array(
                'result' => 'success',
                'redirect' => $data->url
            );

        }

        function redirect_to_cart()
        {
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        function mope_callback()
        {
            $order_id = isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : null;
            if (!$order_id || !is_numeric($order_id)) {
                wc_add_notice("Invalid order ID", 'error');
                $this->redirect_to_cart();
            }

            $order = wc_get_order($order_id);
            $payment_id = $order->get_meta('mope_payment_id');
            if (!$payment_id) {
                wc_add_notice('An unexpected error has occurred.', 'error');
                $order->update_status('failed', 'Mopé payment ID was set on order.');
                $this->redirect_to_cart();
            }

            $response = wp_remote_get($this->mope_api_base_url . 'shop/payment_request/' . $payment_id, $this->custom_wc_request_config);
            $response_status = wp_remote_retrieve_response_code($response);

            if (is_wp_error($response)) {
                wc_add_notice("An unexpected error has occurred.", 'error');
                $this->redirect_to_cart();
            }

            if ($response_status == 401 || $response_status == 403) {
                wc_add_notice("We're having trouble processing your request, please try again later.", "error");
                $order->update_status('failed', "Response status " . $response_status . " returned from Mopé gateway.");
                $this->redirect_to_cart();
            }

            if ($response_status == 404) {
                wc_add_notice("We're having trouble processing your request, please try again later.", "error");
                $order->update_status("failed", "Unable to find Mopé payment ID associated with this order.");
                $this->redirect_to_cart();
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            if ($data->status == 'paid') {
                $order->update_status('completed');
                wc_reduce_stock_levels($order_id);
                wp_safe_redirect($order->get_checkout_order_received_url());
                exit;
            }

            wc_add_notice('Unable to process your payment. Please try again.', 'error');
            $this->redirect_to_cart();
        }
    }
}
