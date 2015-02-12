<?php

/**
 * PTS_Featured_Posts_Widget class.
 *
 * @extends WP_Widget
 */
class PTS_Featured_Posts_Widget extends WP_Widget {

	private $featured_post_types = array();

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->featured_post_types = (array) get_option( 'pts_featured_post_types_settings' );

		$this->WP_Widget( 'pts_featured_posts_widget', 'Featured Posts Widget', array( 'description' => 'Featured Posts Widget' ) );
	}

	/**
	 * widget function.
	 *
	 * @access public
	 * @param mixed $args
	 * @param mixed $instance
	 * @return void
	 */
	public function widget( $args, $instance ) {
		extract( $args );

		$widget_settings = wp_parse_args( $instance,
			array(
				'number' => 5,
				'title' => '',
				'post_type' => '',
			)
		);

		if ( empty( $widget_settings['post_type'] ) )
			return;

		$title = apply_filters( 'widget_title', $widget_settings['title'] );

		echo $before_widget;

		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;

		$args = array(
			'posts_per_page' => (int) $widget_settings['number'],
			'post_type' => $widget_settings['post_type'],
			'tax_query' => array(
				array(
					'taxonomy' => 'pts_feature_tax',
					'field' => 'slug',
					'terms' => array( 'featured' ),
				)
			),
		);

		$featured_posts = new WP_Query( $args );

		if ( $featured_posts->have_posts() ) : ?>
			<div class="pts-widget-post-container">
				<?php while ( $featured_posts->have_posts() ) : $featured_posts->the_post(); ?>
					<div <?php post_class( 'pts-featured-post' ); ?>>
						<h3 title="<?php the_title_attribute(); ?>"><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h3>
					</div><!-- close .pts-featured-post -->
				<?php endwhile; ?>
			</div><!-- close .pts-widget-post-container -->
		<?php endif; wp_reset_postdata();

		echo $after_widget;
	}

	/**
	 * update function.
	 *
	 * @access public
	 * @param mixed $new_instance
	 * @param mixed $old_instance
	 * @return void
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['number'] = (int) $new_instance['number'];

		if ( in_array( $new_instance['post_type'], $this->featured_post_types ) )
			$instance['post_type'] = $new_instance['post_type'];

		return $instance;
	}

	/**
	 * form function.
	 *
	 * @access public
	 * @param mixed $instance
	 * @return void
	 */
	public function form( $instance ) {
		extract( wp_parse_args( $instance,
			array(
				'number' => 5,
				'title' => '',
				'post_type' => '',
			)
		) );

		if ( empty( $this->featured_post_types ) ) { ?>
			<p>You need to select a featured post type on the <a href="<?php echo admin_url( 'options-writing.php' ); ?>">Settings->Writing screen</a> before you can use this widget.</p><?php

		} else { ?>

			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<p>
				<label for="<?php echo $this->get_field_id( 'post_type' ); ?>">Post type to feature:</label>
				<select id="<?php echo $this->get_field_id( 'post_type' ); ?>" name="<?php echo $this->get_field_name( 'post_type' ); ?>" class="widefat">
					<option value="">Select post type...</option>
					<?php
						foreach ( $this->featured_post_types as $pt ) {
							if ( $current_post_type = get_post_type_object( $pt ) ) : ?>
								<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $post_type, $pt ); ?>><?php echo $current_post_type->labels->name; ?></option>
							<?php endif;
						}
					?>
				</select>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id( 'number' ); ?>">Number of Posts:</label>
				<input size="2" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="text" value="<?php echo esc_attr( $number ); ?>" />
			</p>
			<?php
		}
	}
}