<?php
namespace BEA\EP;

/**
 * The purpose of the main class is to init all the plugin base code like :
 *  - Taxonomies
 *  - Post types
 *  - Shortcodes
 *  - Posts to posts relations etc.
 *  - Loading the text domain
 *
 * Class Main
 * @package BEA\EP
 */
class Main {
	/**
	 * Use the trait
	 */
	use Singleton;

	protected function init() {
		add_action( 'init', array( $this, 'init_translations' ) );
		add_filter( 'get_pages', array( $this, 'exclude_pages' ), 10 );
		add_action( 'pre_get_posts', array( $this, 'hide_excluded_pages_on_searchresults' ) );
		add_action( 'wp_head', array( $this,'add_meta_robot_noindex' ) );
	}

	/**
	 * Load the plugin translation
	 */
	public static function init_translations() {
		load_plugin_textdomain( 'bea_exclude_page', false, BEA_EP_DIR . 'languages' );
	}

	/**
	 * Exclude pages
	 *
	 * @param array $pages List of pages to retrieve.
	 *
	 * @return mixed
	 */
	public function exclude_pages( $pages ) {
		// If is admin page return $page.
		if ( is_admin() ) {
			return $pages;
		}

		$exclude_pages_ids = self::get_all_excluded_pages();

		foreach ( $pages as $key => $page ) {
			if ( in_array( (int) $page->ID, (array) $exclude_pages_ids, true ) ) {
				unset( $pages[ $key ] );
			}
		}

		// Reindex the array.
		$pages = array_values( $pages );

		return $pages;
	}

	/**
	 * FRONT : Hide excluded pages from search results page
	 *
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return bool
	 */
	public function hide_excluded_pages_on_searchresults( \WP_Query $query ) {
		if ( is_admin() ) {
			return false;
		}

		$search = $query->get( 's' );
		if ( empty($search) ) {
			return false;
		}
		$excluded_pages = self::get_excluded_pages_option();
		if ( empty( $excluded_pages ) ) {
			return false;
		}

		$options = get_option( 'bea_ep_settings' );
		if ( $options == false ) {
			return false;
		}
		if ( ! isset( $options[BEA_EP_OPTION_SEARCH_EXCLUSION] ) ) {
			return false;
		}
		$option_seo_exclusion = $options[BEA_EP_OPTION_SEARCH_EXCLUSION];
		if ( $option_seo_exclusion != '1' ) {
			return false;
		}
		$query->set( 'post__not_in', $excluded_pages );
		return true;

	}

	/**
	 * Avoid search engine robots to index your excluded page
	 *
	 * @return string
	 */
	public function add_meta_robot_noindex() {
		if ( self::is_excluded_page(get_queried_object_id() ) == false ) {
			return;
		}

		$options = get_option( 'bea_ep_settings' );
		if ( $options == false ) {
			return;
		}
		if ( ! isset( $options[BEA_EP_OPTION_SEO_EXCLUSION] ) ) {
			return;
		}
		$option_seo_exclusion = $options[BEA_EP_OPTION_SEO_EXCLUSION];

		if ( $option_seo_exclusion == '1' ) {
			echo '<meta name="robots" content="noindex, follow">';
		}
	}

	/**
	 * Get list of exclude page
	 *
	 * @return array|mixed|void
	 */
	public static function get_excluded_pages_option() {
		$ep_option = get_option( BEA_EP_OPTION );

		if ( empty( $ep_option ) ) {
			return array();
		}

		return $ep_option;
	}


	/**
	 * Get all excluded pages including children
	 *
	 * @return array|mixed|void
	 */
	public static function get_all_excluded_pages() {
		$excluded_pages = new \WP_Query( array(
			'post_type' => 'page',
			'nopaging' => true,
			'meta_query' => array(
				array(
					'key' => BEA_EP_EXCLUDED_META,
					'value' => 1,
					'compare' => '=',
				),
			),
			'fields' => 'ids',
		));

		if ( ! $excluded_pages->have_posts() ) {
			return array( 0 );
		}

		return array_map( 'intval', $excluded_pages->posts );
	}

	/**
	 * Check if a page is excluded
	 *
	 * @param int $page_id
	 *
	 * @return bool
	 */
	public static function is_excluded_page( $page_id = 0 ) {
		$excluded_pages = \BEA\EP\Main::get_excluded_pages_option();
		if ( empty( $excluded_pages ) ) {
			return false;
		}

		if ( in_array( (int) $page_id, $excluded_pages ) ) {
			return true;
		}

		return false;
	}

}