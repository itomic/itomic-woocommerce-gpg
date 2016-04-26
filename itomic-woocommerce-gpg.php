<?php
/*
Plugin Name: Itomic Woocommerce GPG - WooCommerce Gateway
Plugin URI: http://www.itomic.com.au/
Description: Extends WooCommerce by Adding a GPG email Gateway.
Version: 1.0
Author: Nigel Heap, Itomic
Author URI: http://www.itomic.com.au/
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', function () {
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing\
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    
    // If we made it this far, then include our Gateway Class
    include_once( 'ItomicGpgGateway.php' );

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', function( $methods ) {
        $methods[] = 'ItomicGpgGateway';
        return $methods;
    });

}, 0 );


// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),  function ( $links ) {
    
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'itomic-gpg-gateway' ) . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge( $plugin_links, $links );    
});