<?php
/*
 Plugin Name: BEA - Exclude Pages
 Version: 1.3.0
 Version Boilerplate: 2.1.2
 Plugin URI: http://www.beapi.fr
 Description: Your plugin description
 Author: BE API Technical team
 Author URI: http://www.beapi.fr
 Domain Path: languages
 Text Domain: bea_exclude_page

 ----

 Copyright 2015 BE API Technical team (human@beapi.fr)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

// Plugin constants
define( 'BEA_EP_VERSION', '1.3.0' );
define( 'BEA_EP_MIN_PHP_VERSION', '5.4' );

// Plugin URL and PATH
define( 'BEA_EP_URL', plugin_dir_url( __FILE__ ) );
define( 'BEA_EP_DIR', plugin_dir_path( __FILE__ ) );

define( 'BEA_EP_OPTION', 'bea_ep_options' );
define( 'BEA_EP_OPTION_SEO_EXCLUSION', 'bea_ep_checkbox_seo_exclusion' );
define( 'BEA_EP_OPTION_SEARCH_EXCLUSION', 'bea_ep_checkbox_search_exclusion' );
define( 'BEA_EP_EXCLUDED_META', '_excluded_from_nav' );

// Check PHP min version
if ( version_compare( PHP_VERSION, BEA_EP_MIN_PHP_VERSION, '<' ) ) {
	require_once( BEA_EP_DIR . 'compat.php' );
	// possibly display a notice, trigger error
	add_action( 'admin_init', array( 'BEA\EP\Compatibility', 'admin_init' ) );
	// stop execution of this file
	return;
}
/**
 * Autoload all the things \o/
 */
require_once BEA_EP_DIR . 'autoload.php';

add_action( 'plugins_loaded', 'init_bea_exclude_pages_plugin' );
/**
 * Init the plugin
 */
function init_bea_exclude_pages_plugin() {
	// Client
	\BEA\EP\Main::get_instance();

	// Admin
	if ( is_admin() ) {
		\BEA\EP\Admin\Main::get_instance();
	}

}
