<?php
/*
 * Project: IconPress development candidate.
 * Development version: 2026.10.31.001-dev
 * Copyright (C) 2026 Matthew C. Murphy.
 * Author: https://MatthewxMurphy.com; product/support: https://builtnotbegged.com.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
/**
 * IconPress admin.
 *
 * Rebuilds the original admin surface:
 * - The Icons Manager: the coordinate grid of slots (the v1.1
 *   manager drew the wall with column letters and row numbers and
 *   the same stylesheet as the front end), plus a full list table.
 * - The slot edit form: Ad Text, URL, Email, Icon (media library
 *   picker or image URL, replacing the original filename dropdown
 *   plus upload form), Alt, Rel, Published, Start Date, End Date,
 *   Price, Notes, Order.
 * - The Configuration screen: the original params as Settings.
 * - The Expiring screen and a dashboard widget: the rebuild of
 *   mod_mafiaicons_admin_notify.
 *
 * All actions are capability checked (manage_options) and nonce
 * protected. Everything printed is escaped.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconPress_Admin {

	const PAGE_MANAGER  = 'iconpress';
	const PAGE_SETTINGS = 'iconpress-settings';
	const PAGE_EXPIRING = 'iconpress-expiring';
	const PAGE_SALES    = 'iconpress-sales';

	/**
	 * Wire the admin hooks.
	 */
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
	}

	/**
	 * Menus.
	 */
	public static function menu() {
		add_menu_page(
			__( 'IconPress', 'iconpress' ),
			__( 'IconPress', 'iconpress' ),
			'manage_options',
			self::PAGE_MANAGER,
			array( __CLASS__, 'render_manager' ),
			'dashicons-grid-view',
			58
		);
		add_submenu_page(
			self::PAGE_MANAGER,
			__( 'Icons Manager', 'iconpress' ),
			__( 'Icons Manager', 'iconpress' ),
			'manage_options',
			self::PAGE_MANAGER,
			array( __CLASS__, 'render_manager' )
		);
		add_submenu_page(
			self::PAGE_MANAGER,
			__( 'Expiring Placements', 'iconpress' ),
			__( 'Expiring', 'iconpress' ),
			'manage_options',
			self::PAGE_EXPIRING,
			array( __CLASS__, 'render_expiring' )
		);
		add_submenu_page(
			self::PAGE_MANAGER,
			__( 'Store Sales', 'iconpress' ),
			__( 'Store Sales', 'iconpress' ),
			'manage_options',
			self::PAGE_SALES,
			array( __CLASS__, 'render_sales' )
		);
		add_submenu_page(
			self::PAGE_MANAGER,
			__( 'IconPress Settings', 'iconpress' ),
			__( 'Settings', 'iconpress' ),
			'manage_options',
			self::PAGE_SETTINGS,
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Register the settings and handle plugin actions.
	 */
	public static function admin_init() {
		register_setting(
			'iconpress_settings_group',
			IconPress_Settings::OPTION_KEY,
			array( 'sanitize_callback' => array( 'IconPress_Settings', 'sanitize' ) )
		);

		if ( ! self::is_our_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::maybe_sync_slots();
		self::handle_actions();
	}

	/**
	 * Keep the slot count in step with the grid settings, the job
	 * of the original admin update() task. Pads empty slots and
	 * removes trailing empty slots only; filled slots are kept.
	 */
	private static function maybe_sync_slots() {
		$settings = IconPress_Settings::get();
		$target   = (int) $settings['width'] * (int) $settings['height'];
		$rows     = IconPress_DB::get_slots();
		if ( count( $rows ) !== $target ) {
			IconPress_DB::sync_slots( $target );
		}
	}

	/**
	 * POST and GET action handling for the manager and edit views.
	 */
	private static function handle_actions() {
		$action = isset( $_REQUEST['iconpress_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['iconpress_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Each branch below verifies its own nonce before acting.
		if ( '' === $action ) {
			return;
		}

		if ( 'save_slot' === $action ) {
			check_admin_referer( 'iconpress_save_slot' );
			$id = isset( $_POST['slot_id'] ) ? intval( $_POST['slot_id'] ) : 0;
			if ( $id <= 0 ) {
				return;
			}
			$result = self::save_slot_from_post( $id );
			$msg    = is_wp_error( $result ) ? $result->get_error_message() : 'saved';
			self::redirect_manager( 'edit', $id, $msg );
		}

		if ( 'toggle_publish' === $action && isset( $_GET['id'] ) ) {
			$id = intval( $_GET['id'] );
			check_admin_referer( 'iconpress_toggle_' . $id );
			$row = IconPress_DB::get_slot( $id );
			if ( $row ) {
				IconPress_DB::save_slot( array( 'published' => $row->published ? 0 : 1 ), $id );
			}
			self::redirect_manager( 'list', 0, 'toggled' );
		}

		if ( 'clear_slot' === $action && isset( $_GET['id'] ) ) {
			$id = intval( $_GET['id'] );
			check_admin_referer( 'iconpress_clear_' . $id );
			IconPress_DB::clear_slot( $id );
			self::redirect_manager( 'list', 0, 'cleared' );
		}

		if ( 'delete_slot' === $action && isset( $_GET['id'] ) ) {
			$id = intval( $_GET['id'] );
			check_admin_referer( 'iconpress_delete_' . $id );
			IconPress_DB::delete_slot( $id );
			self::maybe_sync_slots();
			self::redirect_manager( 'list', 0, 'deleted' );
		}

		if ( 'sync_slots' === $action ) {
			check_admin_referer( 'iconpress_sync_slots' );
			$settings = IconPress_Settings::get();
			$result   = IconPress_DB::sync_slots( (int) $settings['width'] * (int) $settings['height'] );
			self::redirect_manager( 'list', 0, 'synced:' . $result['added'] . ':' . $result['removed'] );
		}

		if ( 'load_sample' === $action ) {
			check_admin_referer( 'iconpress_load_sample' );
			self::load_sample_data();
			self::redirect_manager( 'list', 0, 'sample' );
		}
	}

	/**
	 * Validate and save one slot from the edit form.
	 * Mirrors the original table check(): a title is required,
	 * a missing URL scheme is prepended, duplicate titles are
	 * rejected. Saving with title and URL both empty clears the slot.
	 *
	 * @param int $id Slot id.
	 * @return true|WP_Error
	 */
	private static function save_slot_from_post( $id ) {
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$url   = isset( $_POST['url'] ) ? trim( (string) wp_unslash( $_POST['url'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below with scheme handling, then esc_url_raw.

		if ( '' === $title && '' === $url ) {
			IconPress_DB::clear_slot( $id );
			return true;
		}
		if ( '' === $title ) {
			return new WP_Error( 'no_title', __( 'The icon must have an ad text (title).', 'iconpress' ) );
		}
		if ( '' === $url ) {
			return new WP_Error( 'no_url', __( 'The icon must have a URL.', 'iconpress' ) );
		}
		if ( ! preg_match( '#^(https?|ftp)://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return new WP_Error( 'bad_url', __( 'That URL is not valid.', 'iconpress' ) );
		}

		$is_default = ! empty( $_POST['is_default'] ) ? 1 : 0;

		global $wpdb;
		$table = IconPress_DB::table();
		// Duplicate ad text is rejected between real placements.
		// Seeded house rows all share the house ad text on
		// purpose (the original seeded 81 identical rows), so
		// they are exempt on both sides of the comparison.
		$dupe = null;
		if ( ! $is_default ) {
			$dupe = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE title = %s AND id != %d AND is_default = 0", $title, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $dupe ) {
			return new WP_Error( 'dupe_title', __( 'An icon with that ad text already exists.', 'iconpress' ) );
		}

		$start = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$price = isset( $_POST['price'] ) && '' !== $_POST['price'] ? floatval( wp_unslash( $_POST['price'] ) ) : null;
		$alt   = isset( $_POST['alt'] ) ? sanitize_text_field( wp_unslash( $_POST['alt'] ) ) : '';
		if ( '' === $alt ) {
			$alt = $title;
		}
		$rel = isset( $_POST['rel'] ) ? sanitize_text_field( wp_unslash( $_POST['rel'] ) ) : '';
		if ( ! in_array( $rel, array( '', 'nofollow', 'sponsored', 'nofollow sponsored', 'follow', 'external' ), true ) ) {
			$rel = '';
		}

		$data = array(
			'title'      => $title,
			'url'        => $url,
			'icon_url'   => isset( $_POST['icon_url'] ) ? esc_url_raw( wp_unslash( $_POST['icon_url'] ) ) : '',
			'icon_id'    => isset( $_POST['icon_id'] ) ? intval( $_POST['icon_id'] ) : 0,
			'alt'        => $alt,
			'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'published'  => ! empty( $_POST['published'] ) ? 1 : 0,
			'start_date' => self::valid_date_or_null( $start ),
			'end_date'   => self::valid_date_or_null( $end ),
			'price'      => $price,
			'notes'      => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'rel'        => $rel,
			'is_default' => $is_default,
			'ordering'   => isset( $_POST['ordering'] ) ? intval( $_POST['ordering'] ) : 0,
		);

		$saved = IconPress_DB::save_slot( $data, $id );
		if ( false === $saved ) {
			return new WP_Error( 'save_failed', __( 'Error while saving icon details.', 'iconpress' ) );
		}
		IconPress_DB::renumber();
		return true;
	}

	/**
	 * @param string $value Raw date string.
	 * @return string|null Y-m-d date or null.
	 */
	private static function valid_date_or_null( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		$dt = DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $dt && $dt->format( 'Y-m-d' ) === $value ) ? $value : null;
	}

	/**
	 * Fill the first five slots with the original v1.1 sample data,
	 * the same five advertisers the 2010 installer offered.
	 */
	private static function load_sample_data() {
		$sample = array(
			array( 'Cheap Joomla Hosting', 'http://www.maiahost.com/cms/Joomla_Hosting.html', 'https://www.maiahost.com/favicon.ico', 'Cheap Joomla Hosting', 'sales@maiahost.com' ),
			array( 'Joomla Experts, Printed Media, IT Services', 'http://www.object6.com/', 'https://www.object6.com/favicon.ico', 'Joomla Experts, Printed Media, IT Services', '' ),
			array( 'Joomla Training', 'http://www.joomlafordesigners.com/', 'https://www.joomlafordesigners.com/favicon.ico', 'Joomla Training', 'scott@joomlafordesigners.com' ),
			array( 'Dan Weik', 'http://www.danweik.net/', 'https://www.danweik.net/favicon.ico', 'Dan Weik', 'connect@danweik.com' ),
			array( 'Sydney Web Design', 'http://www.faithcomputing.com/', 'https://www.faithcomputing.com/favicon.ico', 'Sydney Web Design', 'scott@faithcomputing.com' ),
		);
		$rows = IconPress_DB::get_slots();
		foreach ( $sample as $i => $s ) {
			if ( ! isset( $rows[ $i ] ) ) {
				break;
			}
			IconPress_DB::save_slot(
				array(
					'title'     => $s[0],
					'url'       => $s[1],
					'icon_url'  => $s[2],
					'icon_id'   => 0,
					'alt'       => $s[3],
					'email'     => $s[4],
					'published' => 1,
					'rel'       => 'nofollow',
					'is_default' => 0,
					'notes'     => 'Sample data from the original Mafia Icons installer (2010).',
				),
				(int) $rows[ $i ]->id
			);
		}
	}

	/**
	 * Redirect back to the manager after an action.
	 *
	 * @param string $view list or edit.
	 * @param int    $id   Slot id for the edit view.
	 * @param string $msg  Notice code or message.
	 */
	private static function redirect_manager( $view, $id, $msg ) {
		$args = array( 'page' => self::PAGE_MANAGER, 'iconpress_msg' => $msg );
		if ( 'edit' === $view ) {
			$args['iconpress_view'] = 'edit';
			$args['id']             = $id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Is the current request one of the IconPress admin pages?
	 *
	 * @return bool
	 */
	private static function is_our_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing check only.
		return in_array( $page, array( self::PAGE_MANAGER, self::PAGE_SETTINGS, self::PAGE_EXPIRING, self::PAGE_SALES ), true );
	}

	/**
	 * Enqueue admin assets on IconPress screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, 'iconpress' ) ) {
			return;
		}
		wp_enqueue_style( 'iconpress-admin', ICONPRESS_URL . 'assets/css/admin.css', array(), ICONPRESS_VERSION );
		if ( isset( $_GET['iconpress_view'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['iconpress_view'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
			wp_enqueue_media();
			wp_enqueue_script( 'iconpress-admin', ICONPRESS_URL . 'assets/js/admin.js', array(), ICONPRESS_VERSION, true );
		}
	}

	/* ------------------------------------------------------------------
	 * Manager screen
	 * ------------------------------------------------------------------ */

	/**
	 * Manager: coordinate grid plus the full slot list, or the edit
	 * form when iconpress_view=edit.
	 */
	public static function render_manager() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage IconPress.', 'iconpress' ) );
		}
		$view = isset( $_GET['iconpress_view'] ) ? sanitize_key( wp_unslash( $_GET['iconpress_view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
		if ( 'edit' === $view ) {
			self::render_edit();
			return;
		}
		self::render_notice();
		$settings = IconPress_Settings::get();
		$rows     = IconPress_DB::get_slots();
		$width    = (int) $settings['width'];
		$height   = (int) $settings['height'];
		$size     = (int) $settings['size'];
		?>
		<div class="wrap iconpress-admin">
			<h1><?php esc_html_e( 'Mafia Icons Manager', 'iconpress' ); ?></h1>
			<p class="description"><?php esc_html_e( 'IconPress is the WordPress rebuild of Mafia Icons. The wall ships preloaded: the owner entries and the house entries below carry the bundled default icons, so no slot ever renders blank. Click any slot to edit, replace, or clear it. Unpublished and expired slots fall back to your house ad on the site.', 'iconpress' ); ?></p>
			<h2 class="iconpress-grid-title">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: grid columns, 2: grid rows. */
						__( 'Grid Size - %1$s x %2$s', 'iconpress' ),
						$width,
						$height
					)
				);
				?>
			</h2>

			<div class="iconpress-grid" style="--ip-cell:<?php echo intval( $size ); ?>px;--ip-cols:<?php echo intval( $width ); ?>">
				<div class="iconpress-grid-corner"></div>
				<?php for ( $c = 0; $c < $width; $c++ ) : ?>
					<div class="iconpress-grid-head"><?php echo esc_html( self::column_letter( $c ) ); ?></div>
				<?php endfor; ?>
				<?php
				for ( $r = 0; $r < $height; $r++ ) :
					?>
					<div class="iconpress-grid-head"><?php echo intval( $r + 1 ); ?></div>
					<?php
					for ( $c = 0; $c < $width; $c++ ) :
						$idx   = ( $r * $width ) + $c;
						$row   = isset( $rows[ $idx ] ) ? $rows[ $idx ] : null;
						$coord = self::column_letter( $c ) . ( $r + 1 );
						if ( ! $row ) :
							?>
							<div class="iconpress-grid-cell iconpress-grid-missing" title="<?php echo esc_attr( $coord ); ?>"></div>
							<?php
							continue;
						endif;
						$filled = IconPress_DB::is_filled( $row );
						$has    = '' !== trim( (string) $row->title ) || '' !== trim( (string) $row->url );
						$label  = $has ? (string) $row->title : __( 'Empty (click to edit)', 'iconpress' );
						$edit   = add_query_arg(
							array(
								'page'           => self::PAGE_MANAGER,
								'iconpress_view' => 'edit',
								'id'             => (int) $row->id,
							),
							admin_url( 'admin.php' )
						);
						?>
						<a class="iconpress-grid-cell<?php echo $has ? '' : ' iconpress-grid-empty'; ?><?php echo ( $has && ! $filled ) ? ' iconpress-grid-inactive' : ''; ?>"
							href="<?php echo esc_url( $edit ); ?>"
							title="<?php echo esc_attr( '[' . $coord . '] ' . $label ); ?>">
							<?php if ( $has ) : ?>
								<img src="<?php echo esc_url( self::admin_icon_src( $row ) ); ?>" width="<?php echo intval( $size ); ?>" height="<?php echo intval( $size ); ?>" alt="">
							<?php else : ?>
								<span class="iconpress-grid-coord"><?php echo esc_html( $coord ); ?></span>
							<?php endif; ?>
						</a>
					<?php endfor; ?>
				<?php endfor; ?>
			</div>

			<p class="iconpress-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_MANAGER ) ); ?>" class="iconpress-inline-form">
				<?php wp_nonce_field( 'iconpress_sync_slots' ); ?>
				<input type="hidden" name="iconpress_action" value="sync_slots">
				<?php submit_button( __( 'Sync slots to grid size', 'iconpress' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_MANAGER ) ); ?>" class="iconpress-inline-form">
				<?php wp_nonce_field( 'iconpress_load_sample' ); ?>
				<input type="hidden" name="iconpress_action" value="load_sample">
				<?php submit_button( __( 'Load the original sample data', 'iconpress' ), 'secondary', 'submit', false ); ?>
			</form>
			</p>

			<table class="wp-list-table widefat fixed striped iconpress-list">
				<thead>
					<tr>
						<th class="iconpress-col-coord"><?php esc_html_e( 'Slot', 'iconpress' ); ?></th>
						<th class="iconpress-col-thumb"><?php esc_html_e( 'Icon', 'iconpress' ); ?></th>
						<th><?php esc_html_e( 'Ad Text', 'iconpress' ); ?></th>
						<th><?php esc_html_e( 'Alt', 'iconpress' ); ?></th>
						<th><?php esc_html_e( 'Start Date', 'iconpress' ); ?></th>
						<th><?php esc_html_e( 'End Date', 'iconpress' ); ?></th>
						<th><?php esc_html_e( 'Price', 'iconpress' ); ?></th>
						<th><?php esc_html_e( 'Hits', 'iconpress' ); ?></th>
						<th><?php esc_html_e( 'Status', 'iconpress' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $rows as $i => $row ) :
					$coord = self::column_letter( $i % $width ) . ( intdiv( $i, $width ) + 1 );
					$edit  = add_query_arg(
						array(
							'page'           => self::PAGE_MANAGER,
							'iconpress_view' => 'edit',
							'id'             => (int) $row->id,
						),
						admin_url( 'admin.php' )
					);
					$toggle = wp_nonce_url(
						add_query_arg(
							array(
								'page'             => self::PAGE_MANAGER,
								'iconpress_action' => 'toggle_publish',
								'id'               => (int) $row->id,
							),
							admin_url( 'admin.php' )
						),
						'iconpress_toggle_' . (int) $row->id
					);
					$clear = wp_nonce_url(
						add_query_arg(
							array(
								'page'             => self::PAGE_MANAGER,
								'iconpress_action' => 'clear_slot',
								'id'               => (int) $row->id,
							),
							admin_url( 'admin.php' )
						),
						'iconpress_clear_' . (int) $row->id
					);
					$delete = wp_nonce_url(
						add_query_arg(
							array(
								'page'             => self::PAGE_MANAGER,
								'iconpress_action' => 'delete_slot',
								'id'               => (int) $row->id,
							),
							admin_url( 'admin.php' )
						),
						'iconpress_delete_' . (int) $row->id
					);
					?>
					<tr>
						<td><?php echo esc_html( $coord ); ?></td>
						<td>
							<?php if ( '' !== trim( (string) $row->title ) || '' !== trim( (string) $row->url ) ) : ?>
								<img src="<?php echo esc_url( self::admin_icon_src( $row ) ); ?>" width="32" height="32" alt="">
							<?php endif; ?>
						</td>
						<td>
							<strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( '' !== (string) $row->title ? $row->title : __( '(empty slot)', 'iconpress' ) ); ?></a></strong>
							<?php if ( '' !== (string) $row->url ) : ?>
								<br><span class="description"><?php echo esc_html( $row->url ); ?></span>
							<?php endif; ?>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'iconpress' ); ?></a> | </span>
								<span class="publish"><a href="<?php echo esc_url( $toggle ); ?>"><?php echo esc_html( $row->published ? __( 'Unpublish', 'iconpress' ) : __( 'Publish', 'iconpress' ) ); ?></a> | </span>
								<span class="clear"><a href="<?php echo esc_url( $clear ); ?>"><?php esc_html_e( 'Clear', 'iconpress' ); ?></a> | </span>
								<span class="delete"><a href="<?php echo esc_url( $delete ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this slot row? The grid will be re-padded with an empty slot.', 'iconpress' ) ); ?>');"><?php esc_html_e( 'Delete', 'iconpress' ); ?></a></span>
							</div>
						</td>
						<td><?php echo esc_html( $row->alt ); ?></td>
						<td><?php echo esc_html( $row->start_date ? $row->start_date : '0000-00-00' ); ?></td>
						<td><?php echo esc_html( $row->end_date ? $row->end_date : '0000-00-00' ); ?></td>
						<td><?php echo esc_html( null !== $row->price ? number_format( (float) $row->price, 2 ) : '' ); ?></td>
						<td><?php echo intval( $row->hits ); ?></td>
						<td><?php echo esc_html( self::status_text( $row ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Put the wall on any page with the [iconpress_wall] shortcode, the IconPress Wall block, or the IconPress Wall widget.', 'iconpress' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Column label for a 0-based index: A..Z then a..z, then A1 style
	 * fallbacks, as in the original grid header.
	 *
	 * @param int $index 0-based column.
	 * @return string
	 */
	private static function column_letter( $index ) {
		if ( $index < 26 ) {
			return chr( 65 + $index );
		}
		if ( $index < 52 ) {
			return chr( 97 + ( $index - 26 ) );
		}
		return 'C' . ( $index + 1 );
	}

	/**
	 * Human status for a slot.
	 *
	 * @param object $row Slot row.
	 * @return string
	 */
	private static function status_text( $row ) {
		if ( '' === trim( (string) $row->title ) && '' === trim( (string) $row->url ) ) {
			return __( 'Empty', 'iconpress' );
		}
		if ( ! empty( $row->is_default ) ) {
			return __( 'House (seeded)', 'iconpress' );
		}
		if ( empty( $row->published ) ) {
			return __( 'Unpublished', 'iconpress' );
		}
		$today = current_time( 'Y-m-d' );
		if ( ! empty( $row->start_date ) && $row->start_date > $today ) {
			return __( 'Scheduled', 'iconpress' );
		}
		if ( ! empty( $row->end_date ) && $row->end_date < $today ) {
			return __( 'Expired', 'iconpress' );
		}
		return __( 'Live', 'iconpress' );
	}

	/**
	 * Icon image URL for admin previews.
	 *
	 * @param object $row Slot row.
	 * @return string
	 */
	private static function admin_icon_src( $row ) {
		if ( ! empty( $row->icon_id ) ) {
			$url = wp_get_attachment_image_url( (int) $row->icon_id, 'thumbnail' );
			if ( $url ) {
				return $url;
			}
		}
		if ( ! empty( $row->icon_url ) ) {
			return IconPress_DB::resolve_icon_url( (string) $row->icon_url );
		}
		return ICONPRESS_URL . 'assets/images/clear.gif';
	}

	/* ------------------------------------------------------------------
	 * Edit screen
	 * ------------------------------------------------------------------ */

	/**
	 * The slot edit form.
	 */
	private static function render_edit() {
		$id  = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only form display.
		$row = $id > 0 ? IconPress_DB::get_slot( $id ) : null;
		if ( ! $row ) {
			wp_die( esc_html__( 'That icon slot does not exist.', 'iconpress' ) );
		}
		self::render_notice();
		$settings = IconPress_Settings::get();
		$back     = add_query_arg( array( 'page' => self::PAGE_MANAGER ), admin_url( 'admin.php' ) );
		?>
		<div class="wrap iconpress-admin">
			<h1><?php esc_html_e( 'Icon Details', 'iconpress' ); ?></h1>
			<p><a href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Back to the Icons Manager', 'iconpress' ); ?></a></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_MANAGER ) ); ?>">
				<?php wp_nonce_field( 'iconpress_save_slot' ); ?>
				<input type="hidden" name="iconpress_action" value="save_slot">
				<input type="hidden" name="slot_id" value="<?php echo intval( $row->id ); ?>">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ip_title"><?php esc_html_e( 'Ad Text', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="title" id="ip_title" maxlength="250" value="<?php echo esc_attr( $row->title ); ?>">
						<p class="description"><?php esc_html_e( 'Required. Shown in the hover tooltip and used as the link title.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_url"><?php esc_html_e( 'URL', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="url" id="ip_url" maxlength="500" value="<?php echo esc_attr( $row->url ); ?>">
						<p class="description"><?php esc_html_e( 'The outbound link. A missing scheme is added for you.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_email"><?php esc_html_e( 'Email', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="email" name="email" id="ip_email" maxlength="190" value="<?php echo esc_attr( $row->email ); ?>">
						<p class="description"><?php esc_html_e( 'Advertiser contact. Admin only, never shown on the site. Used by the Expiring screen.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Icon', 'iconpress' ); ?></th>
						<td>
							<img id="ip_icon_preview" src="<?php echo esc_url( self::admin_icon_src( $row ) ); ?>" width="<?php echo intval( $settings['size'] ); ?>" height="<?php echo intval( $settings['size'] ); ?>" alt="">
							<input type="hidden" name="icon_id" id="ip_icon_id" value="<?php echo intval( $row->icon_id ); ?>">
							<button type="button" class="button" id="ip_icon_pick"><?php esc_html_e( 'Select or upload icon', 'iconpress' ); ?></button>
							<button type="button" class="button" id="ip_icon_clear"><?php esc_html_e( 'Clear', 'iconpress' ); ?></button>
							<p>
								<label for="ip_icon_url"><?php esc_html_e( 'Or an image URL (for example a favicon):', 'iconpress' ); ?></label><br>
								<input class="regular-text" type="text" name="icon_url" id="ip_icon_url" maxlength="500" value="<?php echo esc_attr( $row->icon_url ); ?>">
							</p>
							<p class="description"><?php esc_html_e( 'A media library image wins over the URL when both are set.', 'iconpress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_alt"><?php esc_html_e( 'Alternative Text', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="alt" id="ip_alt" maxlength="250" value="<?php echo esc_attr( $row->alt ); ?>">
						<p class="description"><?php esc_html_e( 'Left empty, the ad text is used.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_rel"><?php esc_html_e( 'Rel', 'iconpress' ); ?></label></th>
						<td>
							<select name="rel" id="ip_rel">
								<option value="" <?php selected( $row->rel, '' ); ?>><?php esc_html_e( 'Use the default from Settings', 'iconpress' ); ?></option>
								<option value="nofollow" <?php selected( $row->rel, 'nofollow' ); ?>>nofollow</option>
								<option value="sponsored" <?php selected( $row->rel, 'sponsored' ); ?>>sponsored</option>
								<option value="nofollow sponsored" <?php selected( $row->rel, 'nofollow sponsored' ); ?>>nofollow sponsored</option>
								<option value="follow" <?php selected( $row->rel, 'follow' ); ?>><?php esc_html_e( 'follow (no rel attribute)', 'iconpress' ); ?></option>
								<option value="external" <?php selected( $row->rel, 'external' ); ?>>external</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Published', 'iconpress' ); ?></th>
						<td><label><input type="checkbox" name="published" value="1" <?php checked( $row->published, 1 ); ?>> <?php esc_html_e( 'Published', 'iconpress' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'House entry', 'iconpress' ); ?></th>
						<td><label><input type="checkbox" name="is_default" value="1" <?php checked( $row->is_default, 1 ); ?>> <?php esc_html_e( 'Seeded default (house) entry', 'iconpress' ); ?></label>
						<p class="description"><?php esc_html_e( 'House entries ship with the plugin and fill the wall until a real placement takes the slot. A store purchase claims house entries first. Uncheck this when an advertiser takes over the slot for good.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_start"><?php esc_html_e( 'Start Date', 'iconpress' ); ?></label></th>
						<td><input type="date" name="start_date" id="ip_start" value="<?php echo esc_attr( $row->start_date ? $row->start_date : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Empty means the placement starts right away.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_end"><?php esc_html_e( 'End Date', 'iconpress' ); ?></label></th>
						<td><input type="date" name="end_date" id="ip_end" value="<?php echo esc_attr( $row->end_date ? $row->end_date : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Empty means the placement does not expire. After this date the slot goes back to a house ad.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_price"><?php esc_html_e( 'Price', 'iconpress' ); ?></label></th>
						<td><input class="small-text" type="number" name="price" id="ip_price" step="0.01" min="0" value="<?php echo esc_attr( null !== $row->price ? $row->price : '' ); ?>">
						<p class="description"><?php esc_html_e( 'What this placement sold for. Admin only, your bookkeeping.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_ordering"><?php esc_html_e( 'Order', 'iconpress' ); ?></label></th>
						<td><input class="small-text" type="number" name="ordering" id="ip_ordering" value="<?php echo intval( $row->ordering ); ?>">
						<p class="description"><?php esc_html_e( 'Position in the grid, left to right, top to bottom.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_notes"><?php esc_html_e( 'Notes', 'iconpress' ); ?></label></th>
						<td><textarea class="large-text" rows="4" name="notes" id="ip_notes"><?php echo esc_textarea( $row->notes ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Admin only. Payment terms, renewal notes, anything you need.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hits', 'iconpress' ); ?></th>
						<td><?php echo intval( $row->hits ); ?>
						<p class="description"><?php esc_html_e( 'Outbound clicks counted through the tracking redirect. Saving an empty Ad Text and URL clears the slot and resets this.', 'iconpress' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Icon', 'iconpress' ) ); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Expiring screen (the notify module, rebuilt as a page + widget)
	 * ------------------------------------------------------------------ */

	/**
	 * Expiring placements screen.
	 */
	public static function render_expiring() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage IconPress.', 'iconpress' ) );
		}
		?>
		<div class="wrap iconpress-admin">
			<h1><?php esc_html_e( 'Expiring Placements', 'iconpress' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Every dated placement, soonest expiry first, with a one-click email to the advertiser. This is the rebuild of the original Mafia Icons admin notify module.', 'iconpress' ); ?></p>
			<?php self::expiring_table( 0 ); ?>
		</div>
		<?php
	}

	/**
	 * The expiry table markup, shared by the screen and the widget.
	 *
	 * @param int $limit 0 for all rows.
	 */
	private static function expiring_table( $limit ) {
		$rows = IconPress_DB::get_expiring();
		if ( $limit > 0 ) {
			$rows = array_slice( $rows, 0, $limit );
		}
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No dated placements.', 'iconpress' ) . '</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'iconpress' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'iconpress' ); ?></th>
					<th><?php esc_html_e( 'Email', 'iconpress' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->title ); ?></td>
					<td><?php echo esc_html( self::expiry_text( $row ) ); ?></td>
					<td>
						<?php if ( '' !== (string) $row->email ) : ?>
							<a href="<?php echo esc_url( 'mailto:' . $row->email . '?subject=' . rawurlencode( __( 'IconPress Notification', 'iconpress' ) ) ); ?>">
								<span class="dashicons dashicons-email-alt"></span> <?php echo esc_html( $row->email ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * "Expired / Expires today / N days (date)" label, the wording
	 * pattern of the original notify module.
	 *
	 * @param object $row Row with days and end_date.
	 * @return string
	 */
	private static function expiry_text( $row ) {
		$days = intval( $row->days );
		if ( $days < 0 ) {
			$status = __( 'Expired', 'iconpress' );
		} elseif ( 0 === $days ) {
			$status = __( 'Expires today', 'iconpress' );
		} elseif ( 1 === $days ) {
			$status = __( '1 day', 'iconpress' );
		} else {
			/* translators: %d: number of days. */
			$status = sprintf( __( '%d days', 'iconpress' ), $days );
		}
		return $status . ' (' . $row->end_date . ')';
	}

	/**
	 * Dashboard widget: the notify module's second home.
	 */
	public static function dashboard_widget() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget(
				'iconpress_expiring',
				__( 'IconPress: Expiring Placements', 'iconpress' ),
				array( __CLASS__, 'render_dashboard_widget' )
			);
		}
	}

	/**
	 * Dashboard widget body.
	 */
	public static function render_dashboard_widget() {
		self::expiring_table( 10 );
		$url = add_query_arg( array( 'page' => self::PAGE_EXPIRING ), admin_url( 'admin.php' ) );
		echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the full Expiring list', 'iconpress' ) . '</a></p>';
	}

	/* ------------------------------------------------------------------
	 * Sales screen (Pro store orders)
	 * ------------------------------------------------------------------ */

	/**
	 * Store orders list: what sold, to whom, and where each
	 * order stands. Placed orders link through to their slot.
	 */
	public static function render_sales() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage IconPress.', 'iconpress' ) );
		}
		?>
		<div class="wrap iconpress-admin">
			<h1><?php esc_html_e( 'Store Sales', 'iconpress' ); ?></h1>
			<?php if ( ! IconPress_Tier::is_pro() ) : ?>
				<p class="description"><?php esc_html_e( 'Automatic selling is an IconPress Pro feature. Activate Pro (Settings tab) and the orders placed through the [iconpress_buy] form will be listed here.', 'iconpress' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Orders placed through the [iconpress_buy] form and paid through Stripe. A completed order is live on the wall; a held order is placed but unpublished until you publish it from the Icons Manager; an unplaced order was paid while the wall had no claimable slot, so place it by hand.', 'iconpress' ); ?></p>
				<?php
				$orders = IconPress_DB::get_orders( 100 );
				if ( ! $orders ) :
					?>
					<p><?php esc_html_e( 'No orders yet.', 'iconpress' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Order', 'iconpress' ); ?></th>
								<th><?php esc_html_e( 'Buyer', 'iconpress' ); ?></th>
								<th><?php esc_html_e( 'Ad Text', 'iconpress' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'iconpress' ); ?></th>
								<th><?php esc_html_e( 'Term', 'iconpress' ); ?></th>
								<th><?php esc_html_e( 'Status', 'iconpress' ); ?></th>
								<th><?php esc_html_e( 'Slot', 'iconpress' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $orders as $order ) : ?>
							<tr>
								<td><?php echo intval( $order->id ); ?><br><span class="description"><?php echo esc_html( (string) $order->created_at ); ?></span></td>
								<td><?php echo esc_html( $order->email ); ?></td>
								<td>
									<?php echo esc_html( $order->title ); ?>
									<?php if ( '' !== (string) $order->url ) : ?>
										<br><span class="description"><?php echo esc_html( $order->url ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format( (int) $order->amount / 100, 2 ) . ' ' . strtoupper( (string) $order->currency ) ); ?></td>
								<td>
									<?php
									/* translators: %d: number of days. */
									echo esc_html( sprintf( __( '%d days', 'iconpress' ), (int) $order->duration_days ) );
									?>
								</td>
								<td><?php echo esc_html( self::order_status_text( $order->status ) ); ?></td>
								<td>
									<?php if ( (int) $order->slot_id > 0 ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_MANAGER, 'iconpress_view' => 'edit', 'id' => (int) $order->slot_id ), admin_url( 'admin.php' ) ) ); ?>">
											<?php esc_html_e( 'Open slot', 'iconpress' ); ?>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Human label for an order status.
	 *
	 * @param string $status Stored status.
	 * @return string
	 */
	private static function order_status_text( $status ) {
		$labels = array(
			'pending'  => __( 'Pending payment', 'iconpress' ),
			'complete' => __( 'Complete (live)', 'iconpress' ),
			'hold'     => __( 'Paid, held for approval', 'iconpress' ),
			'unplaced' => __( 'Paid, needs manual placement', 'iconpress' ),
			'failed'   => __( 'Checkout failed to open', 'iconpress' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/* ------------------------------------------------------------------
	 * Settings screen
	 * ------------------------------------------------------------------ */

	/**
	 * Settings form (the original Configuration screen).
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage IconPress.', 'iconpress' ) );
		}
		$s = IconPress_Settings::get();
		?>
		<div class="wrap iconpress-admin">
			<h1><?php esc_html_e( 'IconPress Settings', 'iconpress' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'iconpress_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ip_size"><?php esc_html_e( 'Icon size', 'iconpress' ); ?></label></th>
						<td>
							<?php foreach ( array( 16, 32, 64, 128 ) as $size_option ) : ?>
								<?php $size_allowed = in_array( $size_option, IconPress_Tier::sizes(), true ); ?>
								<label>
									<input type="radio" name="iconpress_settings[size]" value="<?php echo intval( $size_option ); ?>" <?php checked( $s['size'], $size_option ); ?> <?php disabled( ! $size_allowed ); ?>>
									<?php echo esc_html( IconPress_Tier::size_label( $size_option ) ); ?>
									<?php if ( ! $size_allowed ) : ?>
										<em><?php esc_html_e( '(Pro)', 'iconpress' ); ?></em>
									<?php endif; ?>
								</label><br>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'The size of the icons in pixels. The free tier renders 16 and 32. IconPress Pro adds 64 and 128.', 'iconpress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_width"><?php esc_html_e( 'Width', 'iconpress' ); ?></label></th>
						<td><input class="small-text" type="number" min="1" max="24" name="iconpress_settings[width]" id="ip_width" value="<?php echo intval( $s['width'] ); ?>">
						<p class="description"><?php esc_html_e( 'The number of icons in each row. Changing the grid re-pads the slot list with empty slots (filled slots are never deleted).', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_height"><?php esc_html_e( 'Height', 'iconpress' ); ?></label></th>
						<td><input class="small-text" type="number" min="1" max="24" name="iconpress_settings[height]" id="ip_height" value="<?php echo intval( $s['height'] ); ?>">
						<p class="description"><?php esc_html_e( 'The number of icons in each column.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_default_title"><?php esc_html_e( 'Default ad text', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="iconpress_settings[default_title]" id="ip_default_title" value="<?php echo esc_attr( $s['default_title'] ); ?>">
						<p class="description"><?php esc_html_e( 'Default ad text for empty slots. This is your house ad: it sells the empty slot.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_default_url"><?php esc_html_e( 'Default URL', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="iconpress_settings[default_url]" id="ip_default_url" value="<?php echo esc_attr( $s['default_url'] ); ?>">
						<p class="description"><?php esc_html_e( 'Default URL for empty slots, for example your advertise-with-us page. Left empty, empty slots link to the page the visitor is on.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_default_alt"><?php esc_html_e( 'Default Alt', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="iconpress_settings[default_alt]" id="ip_default_alt" value="<?php echo esc_attr( $s['default_alt'] ); ?>">
						<p class="description"><?php esc_html_e( 'Default alternate text for empty slots.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_target"><?php esc_html_e( 'Target', 'iconpress' ); ?></label></th>
						<td>
							<select name="iconpress_settings[link_target]" id="ip_target">
								<option value="_blank" <?php selected( $s['link_target'], '_blank' ); ?>>_blank</option>
								<option value="_parent" <?php selected( $s['link_target'], '_parent' ); ?>>_parent</option>
								<option value="_self" <?php selected( $s['link_target'], '_self' ); ?>>_self</option>
								<option value="_top" <?php selected( $s['link_target'], '_top' ); ?>>_top</option>
							</select>
							<p class="description"><?php esc_html_e( 'Where outbound links open.', 'iconpress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_default_rel"><?php esc_html_e( 'Default Rel', 'iconpress' ); ?></label></th>
						<td>
							<select name="iconpress_settings[default_rel]" id="ip_default_rel">
								<option value="nofollow" <?php selected( $s['default_rel'], 'nofollow' ); ?>>nofollow</option>
								<option value="sponsored" <?php selected( $s['default_rel'], 'sponsored' ); ?>>sponsored</option>
								<option value="nofollow sponsored" <?php selected( $s['default_rel'], 'nofollow sponsored' ); ?>>nofollow sponsored</option>
								<option value="follow" <?php selected( $s['default_rel'], 'follow' ); ?>><?php esc_html_e( 'follow (no rel attribute)', 'iconpress' ); ?></option>
								<option value="external" <?php selected( $s['default_rel'], 'external' ); ?>>external</option>
							</select>
							<p class="description"><?php esc_html_e( 'The rel used unless a slot sets its own. Paid placements are usually marked sponsored.', 'iconpress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_div_bg"><?php esc_html_e( 'Div Background', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="iconpress_settings[div_bg]" id="ip_div_bg" value="<?php echo esc_attr( $s['div_bg'] ); ?>">
						<p class="description"><?php esc_html_e( 'Background color of each icon cell, for example #dddddd.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_icon_bg"><?php esc_html_e( 'Icon Background', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="iconpress_settings[icon_bg]" id="ip_icon_bg" value="<?php echo esc_attr( $s['icon_bg'] ); ?>">
						<p class="description"><?php esc_html_e( 'Background color behind the icon image itself. Empty means transparent, like the original clear.gif.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_hover_border"><?php esc_html_e( 'Hover Border', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="iconpress_settings[hover_border]" id="ip_hover_border" value="<?php echo esc_attr( $s['hover_border'] ); ?>">
						<p class="description"><?php esc_html_e( 'Border color of the hover tooltip.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_hover_bg"><?php esc_html_e( 'Hover Background', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="iconpress_settings[hover_background]" id="ip_hover_bg" value="<?php echo esc_attr( $s['hover_background'] ); ?>">
						<p class="description"><?php esc_html_e( 'Background color of the hover tooltip.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_hover_weight"><?php esc_html_e( 'Hover Weight', 'iconpress' ); ?></label></th>
						<td><input class="small-text" type="text" name="iconpress_settings[hover_weight]" id="ip_hover_weight" value="<?php echo esc_attr( $s['hover_weight'] ); ?>">
						<p class="description"><?php esc_html_e( 'Font weight of the text in the tooltip, for example 900.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_hover_opacity"><?php esc_html_e( 'Hover Opacity', 'iconpress' ); ?></label></th>
						<td><input class="small-text" type="text" name="iconpress_settings[hover_opacity]" id="ip_hover_opacity" value="<?php echo esc_attr( $s['hover_opacity'] ); ?>">
						<p class="description"><?php esc_html_e( 'Opacity of the tooltip, from 0 to 1, for example 0.80.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Click tracking', 'iconpress' ); ?></th>
						<td><label><input type="checkbox" name="iconpress_settings[track_clicks]" value="1" <?php checked( $s['track_clicks'], 1 ); ?>> <?php esc_html_e( 'Count outbound clicks (links route through a short redirect that adds one hit, like the original). Turn off to link straight to the advertiser.', 'iconpress' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Credit line', 'iconpress' ); ?></th>
						<td><label><input type="checkbox" name="iconpress_settings[show_powered]" value="1" <?php checked( $s['show_powered'], 1 ); ?>> <?php esc_html_e( 'Show the subtle "Powered by IconPress" line under the wall. It is on by default, like the original "Created with Mafia Icons" line, and you can turn it off here.', 'iconpress' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'IconPress Pro', 'iconpress' ); ?></th>
						<td>
							<?php if ( IconPress_Tier::is_pro() ) : ?>
								<p><strong><?php esc_html_e( 'Pro is active on this site.', 'iconpress' ); ?></strong> <?php esc_html_e( 'The 64x64 and 128x128 sizes and the Stripe store below are unlocked.', 'iconpress' ); ?></p>
							<?php else : ?>
								<p><?php esc_html_e( 'This site runs the free tier. Pro adds the 64x64 and 128x128 slot sizes and the Stripe store (automatic slot selling). Enter a license key to unlock it here, or activate the Pro build.', 'iconpress' ); ?></p>
							<?php endif; ?>
							<label for="ip_license_key"><?php esc_html_e( 'License key', 'iconpress' ); ?></label><br>
							<input class="regular-text" type="text" name="iconpress_settings[license_key]" id="ip_license_key" value="<?php echo esc_attr( $s['license_key'] ); ?>" autocomplete="off">
						</td>
					</tr>
					<?php if ( IconPress_Tier::is_pro() ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Store (Stripe)', 'iconpress' ); ?></th>
						<td><label><input type="checkbox" name="iconpress_settings[sales_enabled]" value="1" <?php checked( $s['sales_enabled'], 1 ); ?>> <?php esc_html_e( 'Open the store: show the [iconpress_buy] form and accept paid slot orders.', 'iconpress' ); ?></label>
						<p class="description"><?php esc_html_e( 'Put the [iconpress_buy] shortcode on any page to sell slots. Buyers pay on Stripe hosted checkout; the webhook below places their slot on the wall.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_slot_price"><?php esc_html_e( 'Slot price', 'iconpress' ); ?></label></th>
						<td>
							<input class="small-text" type="number" step="0.01" min="0" name="iconpress_settings[slot_price]" id="ip_slot_price" value="<?php echo esc_attr( $s['slot_price'] ); ?>">
							<input class="small-text" type="text" name="iconpress_settings[slot_currency]" id="ip_slot_currency" maxlength="3" value="<?php echo esc_attr( $s['slot_currency'] ); ?>" style="text-transform:lowercase">
							<label for="ip_slot_duration"><?php esc_html_e( 'for', 'iconpress' ); ?></label>
							<input class="small-text" type="number" min="1" name="iconpress_settings[slot_duration_days]" id="ip_slot_duration" value="<?php echo intval( $s['slot_duration_days'] ); ?>">
							<?php esc_html_e( 'days', 'iconpress' ); ?>
							<p class="description"><?php esc_html_e( 'What one slot costs, in the given 3 letter currency code, and how long a purchased placement runs before it expires back to a house ad.', 'iconpress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_moderation"><?php esc_html_e( 'New slot moderation', 'iconpress' ); ?></label></th>
						<td>
							<select name="iconpress_settings[sales_moderation]" id="ip_moderation">
								<option value="auto" <?php selected( $s['sales_moderation'], 'auto' ); ?>><?php esc_html_e( 'Go live as soon as payment clears', 'iconpress' ); ?></option>
								<option value="hold" <?php selected( $s['sales_moderation'], 'hold' ); ?>><?php esc_html_e( 'Hold for approval (saved unpublished until you publish it)', 'iconpress' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_stripe_pk"><?php esc_html_e( 'Stripe publishable key', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="text" name="iconpress_settings[stripe_publishable_key]" id="ip_stripe_pk" value="<?php echo esc_attr( $s['stripe_publishable_key'] ); ?>" autocomplete="off">
						<p class="description"><?php esc_html_e( 'From your own Stripe dashboard. Your keys live only in this site settings; they are never bundled with the plugin.', 'iconpress' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_stripe_sk"><?php esc_html_e( 'Stripe secret key', 'iconpress' ); ?></label></th>
						<td><input class="regular-text" type="password" name="iconpress_settings[stripe_secret_key]" id="ip_stripe_sk" value="<?php echo esc_attr( $s['stripe_secret_key'] ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_stripe_wh"><?php esc_html_e( 'Stripe webhook secret', 'iconpress' ); ?></label></th>
						<td>
							<input class="regular-text" type="password" name="iconpress_settings[stripe_webhook_secret]" id="ip_stripe_wh" value="<?php echo esc_attr( $s['stripe_webhook_secret'] ); ?>" autocomplete="off">
							<p class="description">
								<?php esc_html_e( 'In Stripe, add a webhook endpoint for the checkout.session.completed event pointing at:', 'iconpress' ); ?><br>
								<code><?php echo esc_url( rest_url( 'iconpress/v1/stripe-webhook' ) ); ?></code><br>
								<?php esc_html_e( 'Then paste that endpoint signing secret (whsec_...) here. The plugin verifies every call against it.', 'iconpress' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ip_buy_success"><?php esc_html_e( 'After payment URLs', 'iconpress' ); ?></label></th>
						<td>
							<input class="regular-text" type="text" name="iconpress_settings[buy_success_url]" id="ip_buy_success" value="<?php echo esc_attr( $s['buy_success_url'] ); ?>" placeholder="<?php esc_attr_e( 'Success URL (optional)', 'iconpress' ); ?>"><br>
							<input class="regular-text" type="text" name="iconpress_settings[buy_cancel_url]" id="ip_buy_cancel" value="<?php echo esc_attr( $s['buy_cancel_url'] ); ?>" placeholder="<?php esc_attr_e( 'Cancel URL (optional)', 'iconpress' ); ?>">
							<p class="description"><?php esc_html_e( 'Where Stripe sends the buyer afterwards. Left empty, buyers return to the page with the buy form.', 'iconpress' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'About IconPress', 'iconpress' ); ?></h2>
			<p><?php esc_html_e( 'IconPress is free, and it is the modern WordPress rebuild of Mafia Icons, the Joomla 1.5 icon wall component from JoomlaMafia (2010). If it earns its keep on your site, you can say thanks with a $2.50 donation:', 'iconpress' ); ?>
			<a href="https://matthewxmurphy.com/donate" target="_blank" rel="noopener">matthewxmurphy.com/donate</a></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Notices and footer
	 * ------------------------------------------------------------------ */

	/**
	 * One-shot notice after an action redirect.
	 */
	private static function render_notice() {
		if ( ! isset( $_GET['iconpress_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice code from our own redirect.
			return;
		}
		$msg = sanitize_text_field( wp_unslash( $_GET['iconpress_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$texts = array(
			'saved'   => __( 'Icon details saved.', 'iconpress' ),
			'toggled' => __( 'Publish state changed.', 'iconpress' ),
			'cleared' => __( 'Slot cleared. It now shows your house ad.', 'iconpress' ),
			'deleted' => __( 'Slot deleted and the grid re-padded.', 'iconpress' ),
			'sample'  => __( 'Sample data loaded into the first five slots.', 'iconpress' ),
		);
		if ( 0 === strpos( $msg, 'synced:' ) ) {
			$parts = explode( ':', $msg );
			/* translators: 1: slots added, 2: slots removed. */
			$text = sprintf( __( 'Slots synced. Added %1$d, removed %2$d.', 'iconpress' ), intval( $parts[1] ?? 0 ), intval( $parts[2] ?? 0 ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
			return;
		}
		if ( isset( $texts[ $msg ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $texts[ $msg ] ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	/**
	 * Donation line in the footer of IconPress admin screens only.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		if ( self::is_our_screen() ) {
			return sprintf(
				/* translators: %s: donation link. */
				__( 'IconPress is free. If it earns its keep, say thanks with a $2.50 donation: %s', 'iconpress' ),
				'<a href="https://matthewxmurphy.com/donate" target="_blank" rel="noopener">matthewxmurphy.com/donate</a>'
			);
		}
		return $text;
	}
}
