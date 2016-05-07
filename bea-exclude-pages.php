<?php
/*
 Plugin Name: BEA Exclude pages
 Version: 1.3
 Description: exclude page from page navigation
 Author: BE API
 Author URI: http://www.beapi.fr
 Domain Path: /languages
 Text Domain: bea_exclude_page
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

// Plugin constants.
define( 'BEA_EP_VERSION', '1.2' );
define( 'BEA_EP_OPTION', 'bea_ep_options' );

// Plugin URL and PATH.
define( 'BEA_EP_URL', plugin_dir_url( __FILE__ ) );
define( 'BEA_EP_DIR', plugin_dir_path( __FILE__ ) );

/**
 *  Function for easy load files.
 *
 * @param string $dir Path directory from root plugin dir.
 * @param array  $files list of fil to include.
 * @param string $prefix prefix.
 */
function _bea_exclude_page_load_files( $dir, $files, $prefix = '' ) {
	foreach ( $files as $file ) {
		if ( is_file( $dir . $prefix . $file . '.php' ) ) {
			require_once( $dir . $prefix . $file . '.php' );
		}
	}
}

_bea_exclude_page_load_files( BEA_EP_DIR . 'classes/', array( 'main' ) );

add_action( 'plugins_loaded', 'init_bea_exlude_page_plugin' );
/**
 * Plugin loaded
 */
function init_bea_exlude_page_plugin() {
	load_plugin_textdomain( 'bea_exclude_page', false, basename( dirname( __FILE__ ), '/' ) . '/languages' );
	new BEA_EP_Main();
}
