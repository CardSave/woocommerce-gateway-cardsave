<?php
/**
 * WooCommerce CardSave Redirect Gateway
 * By Alistair Richardson (CardSave Online) (ecomm@cardsave.net)
 * 
 * Uninstall - removes all CardSave options from DB when user deletes the plugin via WordPress backend.
 * @since 0.3
 **/
 
if ( !defined('WP_UNINSTALL_PLUGIN') ) {
    exit();
}
	delete_option( 'woocommerce_Cardsave_settings' );		
?>