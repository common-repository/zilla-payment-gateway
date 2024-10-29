<?php
add_action("wp_footer", "woo_zilla_gateway_footer");
wp_enqueue_script('zilla', plugins_url('assets/js/zilla_core.min.js', WC_ZILLA_MAIN_FILE), array('jquery'), time(), false);
function woo_zilla_gateway_footer()
{
    $chargecustomer = new WC_Gateway_Zilla();
?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            let ccharge = "<?php echo $chargecustomer->charge_customer; ?>";
            // #place_order
            setInterval(() => {
                var selected = $("#payment_method_zilla").is(":checked");
                if (selected) {
                    $("button[name='woocommerce_checkout_place_order']").removeAttr("id")
                    //remove event on button
                    $("button[name='woocommerce_checkout_place_order']").off("click");
                    //if input is checked
                    $("button[name='woocommerce_checkout_place_order']").click(function(e) {
                        e.preventDefault();
                        var form = $(this).parents("form");
                        //add value to form
                        if ($("#payment_method_zilla").is(":checked")) {
                            var data = form.serializeArray(); // convert form to array
                            data.push({
                                name: "wc-ajax",
                                value: "checkout"
                            });
                            $.ajax({
                                type: "POST",
                                url: "<?php echo site_url("?wc-ajax=checkout"); ?>",
                                data: $.param(data),
                                beforeSend: function() {
                                    $.blockUI({
                                        message: '<div class="blockui-spinner"></div>',
                                        overlayCSS: {
                                            background: '#fff',
                                            opacity: 0.6,
                                            cursor: 'wait'
                                        },
                                        css: {
                                            border: 0,
                                            padding: 0,
                                            backgroundColor: 'transparent'
                                        }
                                    });
                                },
                                success: function(response) {
                                    $.unblockUI();
                                    if (response.result == "failure") {
                                        $(".woocommerce-NoticeGroup").fadeOut().remove();
                                        form.prepend(`<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">${response.messages}
                                </div>`);
                                        //div scroll to div
                                        $('html, body').animate({
                                            scrollTop: $(".woocommerce-NoticeGroup")
                                                .offset().top
                                        }, 1000);
                                    } else if (response.result == "success") {
                                        //do more
                                        var converttofloat = parseFloat(response.amount);
                                        //check if charge customers is checked
                                        if (ccharge == "Yes") {
                                            //add 5% to amount
                                            converttofloat = converttofloat + (converttofloat *
                                                0.05);
                                        }
                                        //convert to string
                                        converttofloat = converttofloat.toString();
                                        //if is empty response.public_key
                                        if (response.public_key == "") {
                                            //show error
                                            $(".woocommerce-NoticeGroup").fadeOut().remove();
                                            form.prepend(`<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
                                <ul class="woocommerce-error">
                                            
                                            <li>
                                                <strong>Error:</strong>
                                                <span>Please contact the website administrator.</span>
                                            </li>
                                        </ul>
                                </div>`);
                                            //scroll to div
                                            $('html, body').animate({
                                                scrollTop: $(".woocommerce-NoticeGroup")
                                                    .offset().top
                                            }, 1000);

                                            return;
                                        }
                                        //initialise make payment
                                        var connect = new Connect();
                                        var config = {
                                            publicKey: response.public_key,
                                            onSuccess: (data) => {
                                                if (data.status == "SUCCESSFUL") {
                                                    $.blockUI({
                                                        message: '<div class="blockui-spinner"></div>',
                                                        overlayCSS: {
                                                            background: '#fff',
                                                            opacity: 0.6,
                                                            cursor: 'wait'
                                                        },
                                                        css: {
                                                            border: 0,
                                                            padding: 0,
                                                            backgroundColor: 'transparent'
                                                        }
                                                    });
                                                    //Implement what happens when transaction is completed.
                                                    window.location.href =
                                                        response
                                                        .api_verify_url +
                                                        "&zillaOrderCode=" +
                                                        data.zillaOrderCode;
                                                } else {
                                                    $(".woocommerce-NoticeGroup")
                                                        .fadeOut().remove();
                                                    form.prepend(`<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
                                            <ul class="woocommerce-error" role="alert">
			<li data-id="billing_city">
			<strong>Transaction Failed. Please try again.</strong>		</li>
	</ul>
                                </div>`);
                                                    //div scroll to div
                                                    $('html, body').animate({
                                                        scrollTop: $(
                                                                ".woocommerce-NoticeGroup"
                                                            )
                                                            .offset().top
                                                    }, 1000);
                                                }
                                            },
                                            onError: () => {
                                                $(".woocommerce-NoticeGroup")
                                                    .fadeOut().remove();
                                                form.prepend(`<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
                                         <ul class="woocommerce-error" role="alert">
			<li data-id="billing_city">
			<strong>Transaction Failed. Please try again.</strong>		</li>
	</ul>
                                </div>`);
                                                //div scroll to div
                                                $('html, body').animate({
                                                    scrollTop: $(
                                                            ".woocommerce-NoticeGroup"
                                                        )
                                                        .offset().top
                                                }, 1000);
                                            },
                                            onClose: () => {
                                                $(".woocommerce-NoticeGroup")
                                                    .fadeOut().remove();
                                                form.prepend(`<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
                                         <ul class="woocommerce-info" role="alert">
			<li data-id="billing_city">
			<strong>Checkout canceled</strong>		</li>
	</ul>
                                </div>`);
                                                //div scroll to div
                                                $('html, body').animate({
                                                    scrollTop: $(
                                                            ".woocommerce-NoticeGroup"
                                                        )
                                                        .offset().top
                                                }, 1000);
                                            },
                                            clientOrderReference: response
                                                .txnref,
                                            title: response.order_title,
                                            amount: converttofloat
                                        };

                                        connect.openNew(config);
                                    }
                                }
                            });
                        } else {
                            $(form).submit();
                        }
                    });
                } else {
                    $("button[name='woocommerce_checkout_place_order']").attr("id", "place_order");
                }
            }, 1000);

        });
    </script>
<?php
}
