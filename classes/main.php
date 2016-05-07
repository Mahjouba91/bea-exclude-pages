<?php

/**
 * Class BEA_EP_Main
 */
class BEA_EP_Main {
	public static $exclude_meta_name = '_excluded_from_nav';

	/**
	 * BEA_EP_Main constructor.
	 */
	function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
		add_action( 'pre_get_posts', array( $this, 'hide_excluded_pages_on_navmenu' ) );
		add_action( 'pre_get_posts', array( $this, 'hide_excluded_pages_on_searchresults' ) );

		add_filter( 'get_pages', array( $this, 'exclude_pages' ), 10 );
		add_filter( 'wp_list_pages_excludes', array( $this, 'filter_wp_list_pages_excludes' ), 10 );
		add_action( 'before_delete_post', array( $this, 'delete_exclude_page' ) );
		add_action( 'wp_head', array( $this,'add_meta_robot_noindex' ) );
	}

	/**
	 * Add meta box on post type page
	 *
	 * @param string $post_type Post type.
	 *
	 * @return bool
	 * @author Zainoudine Soulé
	 */
	public function add_meta_box( $post_type ) {
		if ( 'page' !== $post_type ) {
			return false;
		}

		add_meta_box(
			'bea_exclude_page_metabox',
			__( 'Exclude page', 'bea_exclude_page' ),
			array( __CLASS__, 'render_meta_box_content' ),
			$post_type,
			'side',
			'low'
		);

		return true;
	}

	/**
	 * Display the meta box
	 *
	 * @param mixed $post post object.
	 *
	 * @author Zainoudine Soulé
	 */
	public static function render_meta_box_content( $post ) {
		$pages   = self::get_excluded_pages_option();

		// If one of the ancestors has been defined as hidden, show a message and hide the checkbox
		if ( self::has_ancestors_exclude( $post->ID , $pages ) ) {
			echo '<p>';
			_e( 'This page will be hidden from navigation because one of its parent pages has been defined as hidden', 'bea_exclude_page' );
			echo '</p>';
			return;
		}


		echo '<input type="checkbox" id="bea_exclude_page" name="bea_exclude_page"  value="1" ' . checked( in_array( $post->ID, $pages ), true, false ) . '>';
		echo '<label for="bea_exclude_page">';
		echo esc_html__( 'Exclude this page and its children from lists of pages', 'bea_exclude_page' );
		echo '</label> ';

		wp_nonce_field( 'bea_exclude_page', 'bea_ep_nonce' );
	}

	/**
	 * On save meta update the list of exclude page ID
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return mixed
	 */
	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['post_type'] ) || 'page' !== $_POST['post_type'] ) {
			return $post_id;
		}

		if ( ! wp_verify_nonce( $_POST['bea_ep_nonce'], 'bea_exclude_page' ) ) {
			return $post_id;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return $post_id;
		}

		$options = self::get_excluded_pages_option();

		// Remove value from option.
		if ( isset( $_POST['bea_exclude_page'] ) ) {
			// Add value to option.
			$options[] = $post_id;
			$options   = array_unique( $options );
			self::exclude_page_and_children( $post_id );
		} else {
			$key = array_search( $post_id, $options );
			if ( false !== $key ) {
				unset( $options[ $key ] );
			}
			$options = array_values( $options );
			self::unexclude_page_and_children( $post_id );

		}

		self::set_exclude_page( $options );

		return $post_id;
	}

	/**
	 * Update page and its children post_meta to exclude them from page navigation
	 *
	 * @param int $page_id
	 * @return bool
	 */
	public static function exclude_page_and_children( $page_id = 0 ) {
		if ( 0 >= (int) $page_id ) {
			return false;
		}

		// First update value of parent page
		update_post_meta( $page_id, self::$exclude_meta_name, 1 );

		// Check if parent page has children
		$children = get_children( array( 'post_type' => 'page', 'post_parent' => $page_id ) );
		if ( empty( $children ) || ! is_array( $children ) ) {
			return true;
		}

		foreach ( $children as $child ) {
			self::exclude_page_and_children( (int) $child->ID );
		}

		return true;
	}

	/**
	 * Update page and its children post_meta to include them back inside page navigation
	 *
	 * @param int $page_id
	 * @return bool
	 */
	public static function unexclude_page_and_children( $page_id = 0 ) {
		if ( 0 >= (int) $page_id ) {
			return false;
		}

		// First update value of parent page
		delete_post_meta( $page_id, self::$exclude_meta_name );

		// Check if parent page has children
		$children = get_children( array( 'post_type' => 'page', 'post_parent' => $page_id ) );
		if ( empty( $children ) || ! is_array( $children ) ) {
			return true;
		}

		foreach ( $children as $child ) {
			self::unexclude_page_and_children( (int) $child->ID );
		}

		return true;
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
		$excluded_pages = new WP_Query( array(
			'post_type' => 'page',
			'nopaging' => true,
			'meta_query' => array(
				array(
					'key' => self::$exclude_meta_name,
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
	 * Update list of exclude page
	 *
	 * @param array $options Option value.
	 */
	public function set_exclude_page( $options = array() ) {
		update_option( BEA_EP_OPTION, $options );
	}

	/**
	 * Filter the array of pages to exclude from the page list
	 *
	 * @param array $exclude_array An array of page IDs to exclude.
	 *
	 * @return array
	 */
	public function filter_wp_list_pages_excludes( $exclude_array ) {
		// Don't need to use the get_all_excluded_pages method because by default child pages will not appear if parent page has been hidden
		$exclude_pages_id = self::get_excluded_pages_option();

		foreach ( $exclude_pages_id as $page_id ) {
			$exclude_array[] = $page_id;
		}

		return array_unique( $exclude_array );
	}

	/**
	 * ADMIN : Hide excluded pages from navmenu
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return bool
	 */
	public function hide_excluded_pages_on_navmenu( WP_Query $query ) {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		// Check if we are on page nav-menus.
		$screen = get_current_screen();
		if ( null !== $screen && 'nav-menus' !== $screen->base ) {
			return false;
		}

		if ( 'page' !== $query->get( 'post_type' ) ) {
			return false;
		}
		$options = self::get_excluded_pages_option();
		if ( empty( $options ) ) {
			return false;
		}

		$query->set( 'post__not_in', $options );

		return true;
	}

	/**
	 * FRONT : Hide excluded pages from search results page
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return bool
	 */
	public function hide_excluded_pages_on_searchresults( WP_Query $query ) {
		if ( is_admin() ) {
			return false;
		}

		if ( empty( $query->get( 's' ) ) ) {
			return false;
		}
		$options = self::get_excluded_pages_option();
		if ( empty( $options ) ) {
			return false;
		}

		$query->set( 'post__not_in', $options );

		return true;
	}


	/**
	 * Remove exclude page from database before delete post
	 *
	 * @param int $postid Post ID.
	 *
	 * @return bool
	 */
	public function delete_exclude_page( $postid ) {
		global $post_type;
		if ( 'page' !== $post_type ) {
			return false;
		}

		$options = self::get_excluded_pages_option();
		if ( empty( $options ) ) {
			return false;
		}

		$key = array_search( $postid, $options );
		if ( false !== $key ) {
			unset( $options[ $key ] );
		}
		$options = array_values( $options );

		$this->set_exclude_page( $options );
		
		return true;
	}

	/**
	 * Check if one of the ancestor pages is excluded
	 *
	 * @param int $page_id page ID.
	 * @param array $exclude_page list of excluded page.
	 *
	 * @return bool
	 */
	public function has_ancestors_exclude( $page_id, $exclude_page = array() ) {
		$ancestors = get_ancestors( $page_id, 'page' );

		/**
		 * Don't exclude page without parent and home page
		 */
		if ( empty( $ancestors ) || 0 === $page_id ) {
			return false;
		}

		foreach ( $ancestors as $ancestor ) {
			if ( in_array( $ancestor, $exclude_page ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a page is excluded
	 *
	 * @param int $page_id
	 *
	 * @return bool
	 */
	public static function is_excluded_page( $page_id = 0 ) {
		$excluded_pages = self::get_excluded_pages_option();
		if ( empty( $excluded_pages ) ) {
			return false;
		}

		if ( in_array( (int) $page_id, $excluded_pages ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Avoid search engine robots to index your excluded page
	 *
	 * @return string
	 */
	public function add_meta_robot_noindex() {
		if ( self::is_excluded_page(get_queried_object_id() ) == true ) {
			echo '<meta name="robots" content="noindex, follow">';
		}
	}

}