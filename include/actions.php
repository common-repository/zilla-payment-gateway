<?php
class ZillaActions
{
    public function init()
    {
        //check if is product page
        $this->zilla_add_text_data();
        //woocommerce_widget_shopping_cart_buttons
        add_action('wp_footer', array($this, 'zilla_add_text_data_checkout'), 20);
    }

    //zilla_add_text_data_checkout
    public function zilla_add_text_data_checkout()
    {
?>
        <script>
            jQuery(document).ready(function($) {
                setTimeout(() => {
                    //check if woocommerce-mini-cart__buttons.buttons exist
                    if ($('.woocommerce-mini-cart__buttons.buttons').length > 0) {
                        //get the height of the first button
                        var height = $('.woocommerce-mini-cart__buttons.buttons a:first').height()
                        //add a div after .product-price
                        $('.woocommerce-mini-cart__buttons.buttons').append(`
                        <a href="<?php echo site_url('checkout'); ?>" class="button wc-forward zilla_checkout_btn" style="    background: url(<?php echo WC_HTTPS::force_https_url(plugins_url('assets/images/payment_option.jpg', WC_ZILLA_MAIN_FILE)) ?>);
        background-repeat: no-repeat;
        background-size: contain;
        background-position: center;">
                        </a>
                    `).slideDown();
                    }
                }, 1000);
            });
        </script>
<?php
    }

    //zilla_add_text_data
    public function zilla_add_text_data()
    {
        // include view
        add_action("wp_footer", function () {
            if (is_product()) {
                global $product;
                $product_id = $product->get_id();
                $price = $product->get_sale_price();
                $price = $price ? $price : $product->get_regular_price();
                if (!$price) {
                    $price = $product->get_price();
                }
                //divide by 4 
                $price = $price / 4;
                $price = number_format($price, 0); //format price
                //if price is 0, then don't show
                if ($price == 0) {
                    return;
                }
                //get currency
                $currency = get_woocommerce_currency_symbol();
                $price = $currency . $price;
                $post_class = "post-" . $product_id;
                include_once __DIR__ . '/views/actions-views.php';
            }
        });
    }
}
//init
$zilla = new ZillaActions();
$zilla->init();
