<?php
namespace BEA\EP\Admin;
use BEA\EP\Singleton;

/**
 * Basic class for Admin
 *
 * Class Main
 * @package BEA\EP\Admin
 */
class Main {
	/**
	 * Use the trait
	 */
	use Singleton;

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_options_page' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box_on_pages' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
		add_action( 'pre_get_posts', array( $this, 'hide_excluded_pages_on_navmenu' ) );
		add_filter( 'wp_list_pages_excludes', array( $this, 'filter_wp_list_pages_excludes' ), 10 );
		add_action( 'before_delete_post', array( $this, 'delete_exclude_page' ) );
	}

	public function add_admin_options_page(  ) {
		add_options_page( 'BEA Exclude Pages', 'BEA Exclude Pages', 'manage_options', 'bea_exclude_page_options', array( $this, 'bea_ep_options_page' ) );
	}

	public function settings_init(  ) {

		register_setting( 'general_settings', 'bea_ep_settings' );

		add_settings_section(
			'bea_ep_general_settings_section',
			__( 'General Options', 'bea_exclude_page' ),
			array( $this, 'bea_ep_settings_section_callback' ),
			'general_settings'
		);

		add_settings_field(
			BEA_EP_OPTION_SEO_EXCLUSION,
			__( 'Do you want to hide excluded pages from Search Engine results ?', 'bea_exclude_page' ),
			array( $this, 'bea_ep_checkbox_seo_exclusion_render' ),
			'general_settings',
			'bea_ep_general_settings_section',
			array( "label_for" => BEA_EP_OPTION_SEO_EXCLUSION )
		);

		add_settings_field(
			BEA_EP_OPTION_SEARCH_EXCLUSION,
			__( 'Do you want to hide excluded pages from search results of your website (frontend) ?', 'bea_exclude_page' ),
			array( $this, 'bea_ep_checkbox_search_exclusion_render' ),
			'general_settings',
			'bea_ep_general_settings_section',
			array( "label_for" => BEA_EP_OPTION_SEARCH_EXCLUSION )
		);
	}

	public function bea_ep_checkbox_seo_exclusion_render(  ) {
		$options = get_option( 'bea_ep_settings' ); ?>
		<input type='checkbox' id=<?php echo BEA_EP_OPTION_SEO_EXCLUSION ?> name='bea_ep_settings[<?php echo BEA_EP_OPTION_SEO_EXCLUSION ?>]' <?php checked( $options[BEA_EP_OPTION_SEO_EXCLUSION], 1 ); ?> value='1'>
	<?php
	}

	public function bea_ep_checkbox_search_exclusion_render(  ) {
		$options = get_option( 'bea_ep_settings' ); ?>
		<input type='checkbox' id="<?php echo BEA_EP_OPTION_SEARCH_EXCLUSION ?>" name='bea_ep_settings[<?php echo BEA_EP_OPTION_SEARCH_EXCLUSION ?>]' <?php checked( $options[BEA_EP_OPTION_SEARCH_EXCLUSION], 1 ); ?> value='1'>
	<?php
	}

	public function bea_ep_settings_section_callback(  ) {
		echo __( 'This section description', 'bea_exclude_page' );
	}

	public function bea_ep_options_page(  ) {
		?>
		<form action='options.php' method='post'>

			<h2>BEA Exclude Pages</h2>

			<?php
			settings_fields( 'general_settings' );
			do_settings_sections( 'general_settings' );
			submit_button();
			?>
		</form>
	<?php
	}

	/**
	 * Add meta box on post type page
	 *
	 * @param string $post_type Post type.
	 *
	 * @return bool
	 * @author Zainoudine Soulé
	 */
	public function add_meta_box_on_pages( $post_type ) {
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
		$pages   = \BEA\EP\Main::get_excluded_pages_option();

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

		$options = \BEA\EP\Main::get_excluded_pages_option();

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
		update_post_meta( $page_id, BEA_EP_EXCLUDED_META, 1 );

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
		delete_post_meta( $page_id, BEA_EP_EXCLUDED_META );

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
		$exclude_pages_id = \BEA\EP\Main::get_excluded_pages_option();

		foreach ( $exclude_pages_id as $page_id ) {
			$exclude_array[] = $page_id;
		}

		return array_unique( $exclude_array );
	}

	/**
	 * ADMIN : Hide excluded pages from navmenu
	 *
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return bool
	 */
	public function hide_excluded_pages_on_navmenu( \WP_Query $query ) {
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
		$options = \BEA\EP\Main::get_excluded_pages_option();
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

		$options = \BEA\EP\Main::get_excluded_pages_option();
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
}