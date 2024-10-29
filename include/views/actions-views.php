<script>
    jQuery(document).ready(function($) {
        //check if product-price is available
        var ztitle = $(".product_title.entry-title");
        //get parent with class "post-22"
        var zparent = ztitle.closest(".<?php echo $post_class; ?>");
        //get element with class "price"
        var zprice = zparent.find(".price :first");
        zprice.parent().after(`
                <div style="font-size: 13px;clear: both;">
                    <p>
                        or 4 payments of <?php echo $price; ?> with <img src="<?php echo WC_HTTPS::force_https_url(plugins_url('assets/images/Zilla_logo.svg', WC_ZILLA_MAIN_FILE)); ?>" alt="Zilla Logo" style="    height: 14px;
        margin-left: 3px;"><img src="<?php echo WC_HTTPS::force_https_url(plugins_url('assets/images/learn-more.png', WC_ZILLA_MAIN_FILE)); ?>" alt="Learn More" style="    height: 16px;
    margin-left: 3px;
    cursor: pointer;" onclick="zillarLearnMore()">
                    </p>
                </div>
     `).slideDown();
    });

    let zillarLearnMore = () => {
        window.open(
            "https://usezilla.notion.site/usezilla/Paying-with-Zilla-How-it-works-8246d2ffa8b74708bfdae21c63d324fd", "_blank");
    }
</script>