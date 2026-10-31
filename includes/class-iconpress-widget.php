<?php
/**
 * IconPress widget.
 *
 * The sidebar/module surface, the counterpart of the original
 * mod_mafiaicons module. Renders the same wall as the shortcode
 * and the block.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconPress_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'iconpress_widget',
			__( 'IconPress Wall', 'iconpress' ),
			array(
				'description' => __( 'Displays the IconPress icon wall.', 'iconpress' ),
			)
		);
	}

	/**
	 * @param array $args     Widget args.
	 * @param array $instance Saved values.
	 */
	public function widget( $args, $instance ) {
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided widget wrappers.
		if ( '' !== $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided title wrappers; the title itself is escaped.
		}
		echo IconPress_Render::wall( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes all values.
			array(
				'width'  => isset( $instance['width'] ) ? intval( $instance['width'] ) : 0,
				'height' => isset( $instance['height'] ) ? intval( $instance['height'] ) : 0,
				'size'   => isset( $instance['size'] ) ? intval( $instance['size'] ) : 0,
			)
		);
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-provided widget wrappers.
	}

	/**
	 * @param array $instance Saved values.
	 * @return void
	 */
	public function form( $instance ) {
		$title  = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		$width  = isset( $instance['width'] ) ? intval( $instance['width'] ) : 0;
		$height = isset( $instance['height'] ) ? intval( $instance['height'] ) : 0;
		$size   = isset( $instance['size'] ) ? intval( $instance['size'] ) : 0;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'iconpress' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'width' ) ); ?>"><?php esc_html_e( 'Columns (0 = use settings):', 'iconpress' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'width' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'width' ) ); ?>" type="number" min="0" max="24" value="<?php echo esc_attr( $width ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'height' ) ); ?>"><?php esc_html_e( 'Rows (0 = use settings):', 'iconpress' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'height' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'height' ) ); ?>" type="number" min="0" max="24" value="<?php echo esc_attr( $height ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'size' ) ); ?>"><?php esc_html_e( 'Icon size in px (0 = use settings; 16 or 32 on the free tier, Pro adds 64 and 128):', 'iconpress' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'size' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'size' ) ); ?>" type="number" min="0" max="128" step="16" value="<?php echo esc_attr( $size ); ?>">
		</p>
		<?php
	}

	/**
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'  => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'width'  => isset( $new_instance['width'] ) ? max( 0, min( 24, intval( $new_instance['width'] ) ) ) : 0,
			'height' => isset( $new_instance['height'] ) ? max( 0, min( 24, intval( $new_instance['height'] ) ) ) : 0,
			'size'   => isset( $new_instance['size'] ) ? intval( $new_instance['size'] ) : 0,
		);
	}
}
