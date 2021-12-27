<?php



class Shipcach_pay_Gateway extends WC_Payment_Gateway
{
    use ShpcahePaymentAPI;
    private $BearerToken;
    public function __construct()
    {
        add_filter('wc_stripe_elements_styling', 'my_theme_modify_stripe_fields_styles');
        $this->id   = 'shipcach_payment';
        $this->icon = apply_filters('woocommerce_noob_icon', plugins_url('../assets/icon.png', __FILE__));
        $this->has_fields = false;
        $this->method_title = __('Shipcach Payment', 'shipcach-pay-woo');
        $this->method_description = __('Shipcach local content payment systems.', 'shipcach-pay-woo');
        $this->title = $this->get_option('Shipcach');
        $this->description = $this->get_option('Shipcach_payment_Gateway');
        $this->instructions = $this->get_option('instructions', $this->description);

        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        add_action('woocommerce_after_order_notes', array($this, 'my_custom_checkout_field'));

        $this->form_fields = apply_filters('woo_shipcach_pay_fields', array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'shipcach-pay-woo'),
                'type' => 'checkbox',
                'label' => __('Enable or Disable Noob Payments', 'shipcach-pay-woo'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Shipcash Payments Gateway', 'shipcach-pay-woo'),
                'type' => 'text',
                'default' => __('Shipcash Payments Gateway', 'shipcach-pay-woo'),
                'desc_tip' => true,
                'description' => __('Add a new title for the Noob Payments Gateway that customers will see when they are in the checkout page.', 'shipcach-pay-woo')
            ),
            'description' => array(
                'title' => __('Shipcash Payments Gateway Description', 'shipcach-pay-woo'),
                'type' => 'textarea',
                'default' => __('Please remit your payment to the shop to allow for the delivery to be made', 'shipcach-pay-woo'),
                'desc_tip' => true,
                'description' => __('Add a new title for the Noob Payments Gateway that customers will see when they are in the checkout page.', 'shipcach-pay-woo')
            ),
            'instructions' => array(
                'title' => __('Instructions', 'shipcach-pay-woo'),
                'type' => 'textarea',
                'default' => __('Default instructions', 'shipcach-pay-woo'),
                'desc_tip' => true,
                'description' => __('Instructions that will be added to the thank you page and odrer email', 'shipcach-pay-woo')
            ),
            'enable_for_virtual' => array(
                'title'   => __('Accept for virtual orders', 'woocommerce'),
                'label'   => __('Accept COD if the order is virtual', 'woocommerce'),
                'type'    => 'checkbox',
                'default' => 'yes',
            ),
            'ecpay_payment_methods' => array(
                'title'     => __('Payment Method', 'ecpay'),
                'type'      => 'multiselect',
                'description'   => __('Press CTRL and the right button on the mouse to select multi payments.', 'ecpay'),
            ),
            'bre_token' => array(
                'title'     => __('Bearer Token', 'ecpay'),
                'type' => 'textarea',
                'description'   => __('Add Bearer Token ', 'ecpay'),
            ),
        ));

        $this->BearerToken = $this->get_option('bre_token');
    }

    public function process_payment($order_id)
    {

        global $woocommerce;

        $data_card = [
            "card_number" => $_POST['card_number'] ?? null,
            "exp_month" => $_POST['exp_month'] ?? null,
            "exp_year" => $_POST['exp_year'] ?? null,
            "cvc" => $_POST['cvc'] ?? null,
        ];


        $order = new WC_Order($order_id);
        $total = $order->data['total'];

        $token_id  =   $this->payement_card_stripe($data_card);

        if ($token_id['error'])
            return array(
                'result' => 'error ' . $token_id['message'],
                'redirect' => $this->get_return_url($order)
            );

        $payment_with_api =  $this->payment_with_api($total, $token_id['data'], $this->BearerToken);
        if ($payment_with_api['error'])
            return array(
                'result' => 'error ' . $payment_with_api['message'],
                'redirect' => $this->get_return_url($order)
            );

        // Mark as on-hold (we're awaiting the cheque)
        $order->update_status('on-hold', __('Awaiting cheque payment', 'woocommerce'));
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    public function my_custom_checkout_field($checkout)
    {

        echo '<div id="my_custom_checkout_field ">
                <h3 style="display: inline-flex;">' . __('CREDIT CARD') . '    
                   <img src="' . plugins_url('../assets/card.png', __FILE__) . '" style="width: 30%;height: 32%; margin: auto;">
                </h3>';

        woocommerce_form_field('card_number', array(
            'type'             => 'text',
            'class'         => array('my-field-class orm-row-wide'),
            'label'         => __('Card Number'),
            'required'        => true,
            'placeholder'     => __('Required..'),
        ), $checkout->get_value('card_number'));
        woocommerce_form_field('exp_month', array(
            'type'             => 'text',
            'class'         => array('my-field-class orm-row-wide'),
            'label'         => __('Expiry Month'),
            'required'        => true,
            'placeholder'     => __('Required..'),

        ), $checkout->get_value('exp_month'));
        woocommerce_form_field('exp_year', array(
            'type'             => 'text',
            'class'         => array('my-field-class orm-row-wide'),
            'label'         => __('Expiry Year'),
            'required'        => true,
            'placeholder'     => __('Required..'),

        ), $checkout->get_value('exp_year'));
        woocommerce_form_field('cvc', array(
            'type'             => 'text',
            'class'         => array('my-field-class orm-row-wide'),
            'label'         => __('ccv'),
            'required'        => true,
            'placeholder'     => __('Required..'),
        ), $checkout->get_value('ccv'));
        echo '</div>';
    }
    public function thank_you_page()
    {
        if ($this->instructions)
            echo wpautop($this->instructions);
    }
}
