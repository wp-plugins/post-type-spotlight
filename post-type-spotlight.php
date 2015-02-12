<?php
/*
Plugin Name: Post Type Spotlight
Plugin URI: https://wordpress.org/plugins/post-type-spotlight/
Description: Allows admin chosen post types to have a featured post check box on the edit screen. Also adds appropriate classes to front end post display, and allows featured posts to be queried via a taxonomy query.
Version: 2.0.0
Author: Linchpin
Author URI: http://linchpin.agency/?utm_source=post-type-spotlight&utm_medium=plugin-admin-page&utm_campaign=wp-plugin
License: GPLv2
*/

if ( ! class_exists( 'Post_Type_Spotlight' ) ) {

	/**
	 * Post_Type_Spotlight class.
	 */
	class Post_Type_Spotlight {

		private $doing_upgrades;

		/**
		 * __construct function.
		 *
		 * @access public
		 * @return void
		 */
		function __construct() {
			add_action( 'init',            array( $this, 'init' ) );
			add_action( 'widgets_init',    array( $this, 'widgets_init' ) );
			add_action( 'admin_init',      array( $this, 'admin_init' ) );
			add_action( 'add_meta_boxes',  array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post',       array( $this, 'save_post' ) );
			add_action( 'edit_attachment', array( $this, 'save_post' ) );
			add_action( 'pre_get_posts',   array( $this, 'pre_get_posts' ), 999 );

			add_filter( 'post_class',      array( $this, 'post_class' ), 10, 3 );

			$this->doing_upgrades = false;
		}

		/**
		 * init function.
		 *
		 * @access public
		 * @return void
		 */
		function init() {
			$post_types = get_option( 'pts_featured_post_types_settings', array() );

			if ( ! empty( $post_types ) ) {
				register_taxonomy( 'pts_feature_tax', $post_types, array(
					'hierarchical' => false,
					'show_ui' => false,
					'query_var' => true,
					'rewrite' => false,
					'show_admin_column' => false,
				) );
			}
		}

		/**
		 * widgets_init function.
		 *
		 * @access public
		 * @return void
		 */
		function widgets_init() {
			include_once( plugin_dir_path( __FILE__ ) . '/pts-featured-posts-widget.php' );

			if ( class_exists( 'PTS_Featured_Posts_Widget' ) )
				register_widget( 'PTS_Featured_Posts_Widget' );
		}

		/**
		 * admin_init function.
		 *
		 * @access public
		 * @return void
		 */
		function admin_init() {
			$this->check_for_updates();

			register_setting( 'writing', 'pts_featured_post_types_settings', array( $this, 'sanitize_settings' ) );

			//add a section for the plugin's settings on the writing page
			add_settings_section( 'pts_featured_posts_settings_section', 'Featured Post Types', array( $this, 'settings_section_text' ), 'writing' );

			//For each post type add a settings field, excluding revisions and nav menu items
			if ( $post_types = get_post_types() ) {
				foreach ( $post_types as $post_type ) {
					$pt = get_post_type_object( $post_type );

					if ( in_array( $post_type, array( 'revision', 'nav_menu_item' ) ) || ! $pt->public )
						continue;

					add_settings_field( 'pts_featured_post_types' . $post_type, $pt->labels->name, array( $this,'featured_post_types_field' ), 'writing', 'pts_featured_posts_settings_section', array( 'slug' => $pt->name, 'name' => $pt->labels->name ) );
				}
			}

			if ( $featured_pts = get_option( 'pts_featured_post_types_settings' ) ) {
				foreach ( $featured_pts as $pt ) {
					if ( 'attachment' == $pt ) {
						add_filter( 'manage_media_columns', array( $this, 'manage_posts_columns' ), 999 );
						add_action( 'manage_media_custom_column' , array( $this, 'manage_posts_custom_column' ), 10, 2 );
					} else {
						add_filter( 'manage_' . $pt . '_posts_columns', array( $this, 'manage_posts_columns' ), 999 );
						add_action( 'manage_' . $pt . '_posts_custom_column' , array( $this, 'manage_posts_custom_column' ), 10, 2 );
						add_filter( 'views_edit-' . $pt, array( $this, 'views_addition' ) );
					}
				}
			}
		}

		/**
		 * Check if there are any updates to perform.
		 *
		 * @access public
		 * @return void
		 */
		function check_for_updates() {
			$version = get_option( 'pts_version' );

			//If there is no version, it is a version 2.0 upgrade
			if ( empty( $version ) && ! $this->doing_upgrades ) {

				$this->doing_upgrades = true;

				$args = array(
					'post_type' => get_post_types(),
					'posts_per_page' => 100,
					'offset' => 0,
					'post_status' => 'any',
					'meta_query' => array(
			            array(
			                'key' => '_pts_featured_post',
			            )
			        ),
			        'cache_results' => false,
				);
				$featured_posts = new WP_Query( $args );

				while ( $featured_posts->have_posts() ) {
					foreach ( $featured_posts->posts as $post ) {
						wp_set_object_terms( $post->ID, array( 'featured' ), 'pts_feature_tax', false );
						delete_post_meta( $post->ID, '_pts_featured_post' );
					}

					$args['offset'] = $args['offset'] + $args['posts_per_page'];
					$featured_posts = new WP_Query( $args );
				}

				update_option( 'pts_version', '2.0.0' );

				$this->doing_upgrades = false;
			}
		}

		/**
		 * settings_section_text function.
		 *
		 * @access public
		 * @return void
		 */
		function settings_section_text() {
			global $new_whitelist_options;

			echo "<p>Select which post types can be featured.</p>";
		}

		/**
		 * featured_post_types_field function.
		 *
		 * @access public
		 * @param mixed $args
		 * @return void
		 */
		function featured_post_types_field( $args ) {
			$settings = get_option( 'pts_featured_post_types_settings', array() );

			if ( $post_types = get_post_types() ) { ?>
				<input type="checkbox" name="pts_featured_post_types[]" id="pts_featured_post_types_<?php echo $args['slug']; ?>" value="<?php echo $args['slug']; ?>" <?php in_array( $args['slug'], $settings ) ? checked( true ) : checked( false ); ?>/>
				<?php
			}
		}

		/**
		 * sanitize_settings function.
		 *
		 * @access public
		 * @param mixed $input
		 * @return void
		 */
		function sanitize_settings( $input ) {
			$input = wp_parse_args( $_POST['pts_featured_post_types'], array() );

			$new_input = array();

			foreach ( $input as $pt ) {
				if ( post_type_exists( sanitize_text_field( $pt ) ) ) {
					$new_input[] = sanitize_text_field( $pt );
				}
			}

			return $new_input;
		}

		/**
		 * add_meta_boxes function.
		 *
		 * @access public
		 * @param mixed $post_type
		 * @return void
		 */
		function add_meta_boxes( $post_type ) {
			$settings = get_option( 'pts_featured_post_types_settings', array() );

			if ( empty( $settings ) )
				return;

			if ( in_array( $post_type, $settings ) ) {

				if ( $post_type == 'attachment' )
					add_action( 'attachment_submitbox_misc_actions', array( $this, 'post_submitbox_misc_actions' ) );
				else
					add_action( 'post_submitbox_misc_actions', array( $this, 'post_submitbox_misc_actions' ) );
			}
		}

		/**
		 * post_submitbox_misc_actions function.
		 *
		 * @access public
		 * @return void
		 */
		function post_submitbox_misc_actions() {
			global $post;
			$pt = get_post_type_object( $post->post_type );

			wp_nonce_field( '_pts_featured_post_nonce', '_pts_featured_post_noncename' );
			?>
			<div class="misc-pub-section lp-featured-post">
				<span><?php echo apply_filters( 'pts_featured_checkbox_text', 'Feature this ' . $pt->labels->singular_name . ':', $post ); ?></span>&nbsp;<input type="checkbox" name="_pts_featured_post" id="_pts_featured_post" <?php checked( has_term( 'featured', 'pts_feature_tax', $post->ID ) ); ?> />
			</div>
			<?php
		}

		function manage_posts_columns( $columns ) {

			unset( $columns['date'] );

			return array_merge( $columns, array(
				'lp-featured' => __( 'Featured' ),
				'date' => __( 'Date' ),
			) );
		}

		/**
		 * manage_posts_custom_column function.
		 *
		 * @access public
		 * @param mixed $column
		 * @param mixed $post_id
		 * @return void
		 */
		function manage_posts_custom_column( $column, $post_id ) {
			switch ( $column ) {
				case 'lp-featured':
					if ( has_term( 'featured', 'pts_feature_tax', $post_id ) )
						echo '<span class="dashicons dashicons-star-filled"></span>';
					break;
			}
		}

		/**
		 * pre_get_posts function.
		 *
		 * @access public
		 * @return void
		 */
		function pre_get_posts( $query ) {

			if ( $this->doing_upgrades ) {
				return;
			}

			$version = get_option( 'pts_version' );

			if ( empty( $version ) || version_compare( $version, '2.0.0' ) == -1 ) {
				return;
			}

			if ( isset( $query->query_vars['meta_query'] ) ) {
				foreach ( $query->query_vars['meta_query'] as $key => $meta_query ) {
					if ( '_pts_featured_post' == $meta_query['key'] ) {
						$query->query_vars['tax_query'][] = array(
							'taxonomy' => 'pts_feature_tax',
							'field' => 'slug',
							'terms' => array( 'featured' ),
						);

						unset( $query->query_vars['meta_query'][ $key ] );
						_deprecated_argument( 'WP_Query()', '2.0 of the Post Type Spotlight plugin', 'The _pts_featured_post post meta field has been removed. Please see https://wordpress.org/plugins/post-type-spotlight/faq/ for more info.' );
					}
				}
			}
		}

		/**
		 * views_addition function.
		 *
		 * @access public
		 * @param mixed $views
		 * @return void
		 */
		function views_addition( $views ) {
			$featured = new WP_Query( array(
				'post_type' => get_post_type(),
				'posts_per_page' => 1,
				'tax_query' => array(
					array(
						'taxonomy' => 'pts_feature_tax',
						'field' => 'slug',
						'terms' => array( 'featured' ),
					)
				),
			) );

			if ( $featured->have_posts() )
				$count = $featured->found_posts;
			else
				$count = 0;

			$link = '<a href="edit.php?post_type=' . get_post_type() . '&pts_feature_tax=featured"';

			if ( isset( $_GET['pts_feature_tax'] ) && $_GET['pts_feature_tax'] == 'featured' )
				$link .= ' class="current"';

			$link .= '>Featured</a> <span class="count">(' . $count . ')</span>';

			return array_merge( $views, array( 'featured' => $link ) );
		}

		/**
		 * save_post function.
		 *
		 * @access public
		 * @param mixed $post_id
		 * @return void
		 */
		function save_post( $post_id ) {
			//Skip revisions and autosaves
			if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) )
				return;

			//Users should have the ability to edit listings.
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;

			if ( isset( $_POST['_pts_featured_post_noncename'] ) && wp_verify_nonce( $_POST['_pts_featured_post_noncename'], '_pts_featured_post_nonce' ) ) {

				if ( isset( $_POST['_pts_featured_post'] ) && ! empty( $_POST['_pts_featured_post'] ) ) {
					delete_post_meta( $post_id, '_pts_featured_post' );
					wp_set_object_terms( $post_id, array( 'featured' ), 'pts_feature_tax', false );
				} else {
					delete_post_meta( $post_id, '_pts_featured_post' );
					wp_set_object_terms( $post_id, null, 'pts_feature_tax', false );
				}
			}
		}

		/**
		 * post_class function.
		 *
		 * @access public
		 * @param mixed $classes
		 * @param mixed $class
		 * @param mixed $post_id
		 * @return void
		 */
		function post_class( $classes, $class, $post_id ) {
			if ( has_term( 'featured', 'pts_feature_tax', $post_id ) ) {
				$classes[] = 'featured';
				$classes[] = 'featured-' . get_post_type( $post_id );
			}

			return $classes;
		}
	}
}

$pts_featured_posts = new Post_Type_Spotlight();