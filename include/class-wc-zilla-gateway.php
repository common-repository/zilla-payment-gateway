<?php

class WC_Gateway_Zilla extends WC_Payment_Gateway_CC
{
    /**
     * test mode
     *
     * @var bool
     */
    public $testmode;

    /**
     * live secret key
     *
     * @var string
     */
    public $secret_key;

    /**
     * live publishable key
     *
     * @var string
     */
    public $publishable_key;

    /**
     * test secret key
     *
     * @var string
     */
    public $test_secret_key;

    /**
     * test publishable key
     *
     * @var string
     */
    public $test_publishable_key;

    /**
     * live secret key
     *
     * @var string
     */
    public $live_secret_key;

    /**
     * live publishable key
     *
     * @var string
     */
    public $live_publishable_key;

    /**
     * enabled 
     */
    public $enabled;

    public $remove_cancel_order_button;

    public $msg;

    public $apiURL;

    public $merchantid;

    public $autocomplete_order;

    public $charge_customer;


    /**
     * Constructor
     */

    public function __construct()
    {
        $this->id                 = 'zilla';
        $this->method_title       = __('Zilla Payment Gateway', 'zilla-payment-gateway');
        $this->method_description = sprintf(__('Zilla allows customers to shop on merchant sites and pay later at %1$s interest. <a href="%2$s" target="_blank">Sign up</a> for a Zilla account, and <a href="%3$s" target="_blank">get your API keys</a>.', 'zilla-payment-gateway'), '0%', 'https://merchant.usezilla.com/', 'https://merchant.usezilla.com/settings');
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'tokenization',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
        );


        // Load the form fields
        $this->init_form_fields();

        // Load the settings
        $this->init_settings();
        //Other data
        $this->title   = $this->get_option('title');
        $this->icon = WC_HTTPS::force_https_url(plugins_url('assets/images/payment_option.jpg', WC_ZILLA_MAIN_FILE));
        $this->description = $this->get_option('description');
        $this->enabled     = $this->get_option('enabled');

        $this->test_publishable_key = $this->get_option('test_public_key');
        $this->test_secret_key = $this->get_option('test_secret_key');

        $this->live_publishable_key = $this->get_option('live_public_key');
        $this->live_secret_key = $this->get_option('live_secret_key');

        $this->merchantid = $this->get_option('merchantid');

        $this->testmode    = $this->get_option('testmode') === 'yes' ? true : false;

        $this->autocomplete_order = $this->get_option('autocomplete_order') === 'yes' ? true : false;

        $this->remove_cancel_order_button = $this->get_option('remove_cancel_order_button') === 'yes' ? true : false;

        $this->charge_customer = $this->get_option('charge_customer_zilla') === 'yes' ? "Yes" : "No";

        $this->apiURL = $this->testmode ? "https://bnpl-gateway-sandbox.zilla.africa" : "https://bnpl-gateway.usezilla.com";

        $this->publishable_key = $this->testmode ? $this->test_publishable_key : $this->live_publishable_key;
        $this->secret_key = $this->testmode ? $this->test_secret_key : $this->live_secret_key;
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_available_payment_gateways', array($this, 'add_gateway_to_checkout'));
        add_action('woocommerce_api_wc_gateway_zilla', array($this, 'verify_zilla_transaction'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('admin_notices', array($this, 'is_valid_for_use'));
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array(
                $this,
                'process_admin_options',
            )
        );

        // Check if the gateway can be used.
        if (!$this->is_valid_for_use()) {
            $this->enabled = false;
        }
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {

        if ($this->description) {
            echo wp_kses_post($this->description);
        }

        if (!is_ssl()) {
            return;
        }

        if ($this->supports('tokenization') && is_checkout() && $this->saved_cards && is_user_logged_in()) {
            $this->tokenization_script();
            $this->saved_payment_methods();
            $this->save_payment_method_checkbox();
        }
    }

    /**
     * Outputs scripts used for zilla payment.
     */
    public function payment_scripts()
    {

        if (!is_checkout_pay_page()) {
            return;
        }

        if ($this->enabled === 'no') {
            return;
        }

        $order_key = sanitize_text_field(urldecode($_GET['key']));
        $order_id  = absint(get_query_var('order-pay'));

        $order = wc_get_order($order_id);
        $api_verify_url = WC()->api_request_url('WC_Gateway_Zilla') . '?zilla_id=' . $order_id;

        $payment_method = method_exists($order, 'get_payment_method') ? $order->get_payment_method() : $order->payment_method;

        if ($this->id !== $payment_method) {
            return;
        }

        wp_enqueue_script('jquery');

        wp_enqueue_script('zilla', plugins_url('assets/js/zilla_core.min.js', WC_ZILLA_MAIN_FILE), array('jquery'), time(), false);

        wp_enqueue_script('wc_zilla', plugins_url('assets/js/zilla.js', WC_ZILLA_MAIN_FILE), array('jquery', 'zilla'), time(), false);

        $zilla_params = array(
            'public_key' => $this->publishable_key,
            'order_id' => $order_id,
            'order_title' => "Make payment of " . $order->get_total(),
            "amount" => $order->get_total(),
            'api_verify_url' => $api_verify_url
        );

        if (is_checkout_pay_page() && get_query_var('order-pay')) {

            $email         = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
            $amount        = $order->get_total();
            $txnref        = "wc-" . $order_id . '_' . time();
            $the_order_id  = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
            $the_order_key = method_exists($order, 'get_order_key') ? $order->get_order_key() : $order->order_key;
            $currency      = method_exists($order, 'get_currency') ? $order->get_currency() : $order->order_currency;

            if ($the_order_id == $order_id && $the_order_key == $order_key) {

                $zilla_params['email']        = $email;
                $zilla_params['amount']       = $amount;
                $zilla_params['txnref']       = $txnref;
                $zilla_params['pay_page']     = $this->payment_page;
                $zilla_params['currency']     = $currency;
                $zilla_params['bank_channel'] = 'true';
                $zilla_params['card_channel'] = 'true';
                $zilla_params['first_name'] = $order->get_billing_first_name();
                $zilla_params['last_name'] = $order->get_billing_last_name();
                $zilla_params['phone'] = $order->get_billing_phone();
                $zilla_params['card_channel'] = 'true';
            }
            update_post_meta($order_id, '_zilla_txn_ref', $txnref);
        }

        wp_localize_script('wc_zilla', 'wc_zilla_params', $zilla_params);
    }

    /**
     * Verify Zilla payment.
     */
    public function verify_zilla_transaction()
    {
        //If transactions_refrence is not set
        if (isset($_GET["zillaOrderCode"]) && $_GET["zillaOrderCode"] != "undefined" && $_GET["zillaOrderCode"] != "") {
            //DO More
            if (isset($_GET['zilla_id']) && urldecode($_GET['zilla_id'])) {
                $order_id = sanitize_text_field(urldecode($_GET['zilla_id']));
                //Get Order
                $order = wc_get_order($order_id);
                //if order not found
                if (!$order) {
                    wp_redirect(wc_get_page_permalink('cart'));
                }

                if (in_array($order->get_status(), array('processing', 'completed', 'on-hold'))) {
                    wp_redirect($this->get_return_url($order));
                    exit;
                }
                //Then make http request for transaction verification
                $zilla_url = $this->apiURL . '/bnpl/auth/sa';

                $headers = array(
                    // 'Authorization' => 'Bearer ' . $this->secret_key,
                    'Content-Type' => 'application/json',
                );

                $args = array(
                    'headers' => $headers,
                    'timeout' => 60,
                    'body' => json_encode(array(
                        "publicKey" =>  $this->publishable_key,
                        "secretKey" =>  $this->secret_key,
                    )),
                );

                $request = wp_remote_post($zilla_url, $args);

                if (!is_wp_error($request) && 200 === wp_remote_retrieve_response_code($request)) {
                    $body = wp_remote_retrieve_body($request);
                    $result = json_decode($body);

                    if ($result->message == "Success") {
                        $token = $result->data->token;
                        $zilla_order_code = sanitize_text_field(urldecode($_GET["zillaOrderCode"]));
                        $zilla_url_verify = $this->apiURL . '/bnpl/purchase-order/' . $zilla_order_code . '/merchant-full-info';
                        //wp remote get
                        $response2 = wp_remote_get($zilla_url_verify, array(
                            'headers' => array(
                                'Authorization' => 'Bearer ' . $token,
                            ),
                        ));

                        $body2 = wp_remote_retrieve_body($response2);

                        $newr = json_decode($body2);

                        if ($newr->message == "Success") {
                            $transactions_refrence = $newr->data->clientOrderReference;
                            function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();
                            if ($newr->data->status == "SUCCESSFUL") {
                                //CLear Order
                                $order->payment_complete($transactions_refrence);
                                if ($this->autocomplete_order) {
                                    $order->update_status('completed');
                                } else {
                                    $order->update_status('processing');
                                }
                                $order->add_order_note('Payment was successful on Zilla');
                                $order->add_order_note(sprintf(__('Payment via Zilla successful (Transaction Reference: %s)', 'zilla-payment-gateway'), $transactions_refrence));
                                //Customer Note
                                $customer_note  = 'Thank you for your order.<br>';
                                $customer_note .= 'Your payment was successful, we are now <strong>processing</strong> your order.';

                                $order->add_order_note($customer_note, 1);

                                WC()->cart->empty_cart();
                                //CLear Cart
                                wc_add_notice($customer_note, 'notice');
                                //redirect to order page
                                wp_redirect($this->get_return_url($order));
                                exit;
                            } else {
                                $order->update_status('Failed');

                                update_post_meta($order_id, '_transaction_id', $transactions_refrence);

                                $notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment is currently having issues with verification and .%1$sYour order is currently on-hold.%2$sKindly contact us for more information regarding your order and payment status.', 'zilla-payment-gateway'), '<br />', '<br />');
                                $notice_type = 'notice';

                                // Add Customer Order Note
                                $order->add_order_note($notice, 1);

                                // Add Admin Order Note
                                $admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Payment can not be verified.%3$swhile the <strong>Zilla Transaction Reference:</strong> %4$s', 'zilla-payment-gateway'), '<br />', '<br />', '<br />', $transactions_refrence);
                                $order->add_order_note($admin_order_note);

                                wc_add_notice($notice, $notice_type);

                                //CLear Cart
                                WC()->cart->empty_cart();
                            }
                            //redirect to order page
                            wp_redirect($this->get_return_url($order));
                        } else {
                            //Quit and redirect
                            wp_redirect(wc_get_page_permalink('cart'));
                            exit;
                        }
                    } else {
                        wc_add_notice("Unable to verify payment", "notice");
                        wp_redirect(wc_get_page_permalink('cart'));
                        exit;
                    }
                } else {
                    wc_add_notice("Unable to verify payment", "notice");
                    wp_redirect(wc_get_page_permalink('cart'));
                    exit;
                }
            }
        }

        wp_redirect(wc_get_page_permalink('cart'));
        exit;
    }

    /**
     * Process the payment.
     *
     * @param int $order_id
     *
     * @return array|void
     */
    public function process_payment($order_id)
    {

        if (is_user_logged_in() && isset($_POST['wc-' . $this->id . '-new-payment-method']) && true === (bool)
        $_POST['wc-' . $this->id . '-new-payment-method'] && $this->saved_cards) {

            update_post_meta($order_id, '_wc_zilla_save_card', true);
        }

        $order = wc_get_order($order_id);
        $api_verify_url = WC()->api_request_url('WC_Gateway_Zilla') . '?zilla_id=' . $order_id;
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
            'public_key' => $this->publishable_key,
            'order_id' => $order_id,
            'order_title' => "Make payment of " . $order->get_total(),
            "amount" => $order->get_total(),
            'api_verify_url' => $api_verify_url,
            'txnref' => "wc-" . $order_id . '_' . time()
        );
    }

    /**
     * Add Gateway to checkout page.
     *
     * @param $available_gateways
     *
     * @return mixed
     */
    public function add_gateway_to_checkout($available_gateways)
    {

        if ('no' == $this->enabled) {
            unset($available_gateways[$this->id]);
        }

        return $available_gateways;
    }

    /**
     * Displays the payment page.
     *
     * @param $order_id
     */
    public function receipt_page($order_id)
    {

        $order = wc_get_order($order_id);

        echo '<div id="yes-add">' . __('Thank you for your order, please click the button below to pay with Zilla.', 'zilla-payment-gateway') . '</div>';

        echo '<div id="zilla_form"><form id="order_review" method="post" action="' . WC()->api_request_url('WC_Gateway_Zilla') . '"></form><button class="button alt" id="zilla-payment-gateway-button">' . __('Pay Now', 'zilla-payment-gateway') . '</button>';

        if (!$this->remove_cancel_order_button) {
            echo '  <a class="button cancel" id="cancel-btn" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'zilla-payment-gateway') . '</a>';
        }

        echo '</div>';
    }

    /**
     * Check if this gateway is enabled and available in the user's country.
     */
    public function is_valid_for_use()
    {

        if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_zilla_supported_currencies', array('NGN')))) {
            add_action('admin_notices', [$this, 'admin_notices2']);
            return false;
        }

        return true;
    }

    /**
     * Display zilla payment icon.
     */
    // public function get_icon() {

    //     $icon = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/logo.jpg', WC_ZILLA_MAIN_FILE ) ) . '" alt="Zilla Payment Options" style="height: 75px;" />';

    //     return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

    // }

    /**
     * Check if Zilla merchant details is filled.
     */
    public function admin_notices()
    {

        if ($this->enabled == 'no') {
            return;
        }

        // Check required fields.
        if ('yes' != $this->test_mode) {
            if (!($this->live_secret_key && $this->live_publishable_key)) {
                echo '<div class="error"><p>' . sprintf(__('Please enter your live Zilla merchant details <a href="%s">here</a> to be able to use the Zilla WooCommerce plugin.', 'zilla-payment-gateway'), admin_url('admin.php?page=wc-settings&tab=checkout&section=zilla')) . '</p></div>';
                return;
            }
        }
    }

    public function admin_notices2()
    {

        // Check required fields.
        echo '<div class="error"><p>' . sprintf(__('Zilla does not support your store currency. Kindly set it to NGN (&#8358) <a href="%s">here</a>', 'zilla-payment-gateway'), admin_url('admin.php?page=wc-settings&tab=general')) . '</p></div>';
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {

        $form_fields = array(
            'enabled'                          => array(
                'title'       => __('Enable/Disable', 'zilla-payment-gateway'),
                'label'       => __('Enable Zilla', 'zilla-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __('Enable Zilla as a payment option on the checkout page.', 'zilla-payment-gateway'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'title'                            => array(
                'title'       => __('Title', 'zilla-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the payment method title which the user sees during checkout.', 'zilla-payment-gateway'),
                'default'     => __('Debit/Credit Cards', 'zilla-payment-gateway'),
                'desc_tip'    => true,
            ),
            'description'                      => array(
                'title'       => __('Description', 'zilla-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the payment method description which the user sees during checkout.', 'zilla-payment-gateway'),
                'default'     => __('Buy anything, anytime and pay later at 0% interest.', 'zilla-payment-gateway'),
                'desc_tip'    => true,
            ),
            'testmode'                         => array(
                'title'       => __('Test mode', 'zilla-payment-gateway'),
                'label'       => __('Enable Test Mode', 'zilla-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __('Test mode enables you to test payments before going live. <br />Once the LIVE MODE is enabled on your Zilla account uncheck this.', 'zilla-payment-gateway'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'merchantid'                  => array(
                'title'       => __('Merchant ID', 'zilla-payment-gateway'),
                'type'        => 'number',
                'description' => __('Enter your Merchant ID here', 'zilla-payment-gateway'),
                'default'     => '',
            ),
            'test_secret_key'                  => array(
                'title'       => __('Test Secret Key', 'zilla-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter your Test Secret Key here', 'zilla-payment-gateway'),
                'default'     => '',
            ),
            'test_public_key'                  => array(
                'title'       => __('Test Public Key', 'zilla-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter your Test Public Key here.', 'zilla-payment-gateway'),
                'default'     => '',
            ),
            'live_secret_key'                  => array(
                'title'       => __('Live Secret Key', 'zilla-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter your Live Secret Key here.', 'zilla-payment-gateway'),
                'default'     => '',
            ),
            'live_public_key'                  => array(
                'title'       => __('Live Public Key', 'zilla-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter your Live Public Key here.', 'zilla-payment-gateway'),
                'default'     => '',
            ),
            'autocomplete_order'                         => array(
                'title'       => __('Auto Complete Order', 'zilla-payment-gateway'),
                'label'       => __('Automatically complete an order on successful payment', 'zilla-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __('Automatically complete an order on successful payment', 'zilla-payment-gateway'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'remove_cancel_order_button' => array(
                'title'       => __('Remove cancel order button', 'zilla-payment-gateway'),
                'label'       => __('Remove cancel order button', 'zilla-payment-gateway'),
                'type'        => 'checkbox',
                'description' => __('Remove cancel order button', 'zilla-payment-gateway'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
        );
        //apply filters
        $form = apply_filters("zillar_extral_fields", $form_fields);
        $this->form_fields = $form;
    }

    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }
}
