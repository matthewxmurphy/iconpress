<?php
/**
 * IconPress database layer.
 *
 * IconPress keeps the original Mafia Icons data model: one dedicated
 * table of fixed wall slots, mirroring the Joomla table
 * #__mafiaicons (jos_mafiaicons). A custom post type was considered
 * and rejected: slots are positional grid cells with an ordering
 * column, a hit counter, a price, and a start/end window, and the
 * wall always reads them in slot order. That is the original table's
 * shape, so the rebuild keeps it.
 *
 * @package IconPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IconPress_DB {

	const DB_VERSION     = '2';
	const VERSION_OPTION = 'iconpress_db_version';
	const SEEDED_OPTION  = 'iconpress_seeded';

	/**
	 * @return string Full slots table name.
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'iconpress_icons';
	}

	/**
	 * @return string Full orders table name (Pro store).
	 */
	public static function orders_table() {
		global $wpdb;
		return $wpdb->prefix . 'iconpress_orders';
	}

	/**
	 * Re-run the installer when the stored schema version is
	 * behind. dbDelta is idempotent, and install() only seeds
	 * an empty table, so this is safe on every load until the
	 * version matches.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or upgrade the table and seed the slot grid.
	 * Runs on activation.
	 */
	public static function install() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(250) NOT NULL DEFAULT '',
			url VARCHAR(500) NOT NULL DEFAULT '',
			icon_url VARCHAR(500) NOT NULL DEFAULT '',
			icon_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			alt VARCHAR(250) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			hits BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ordering INT(11) NOT NULL DEFAULT 0,
			published TINYINT(1) NOT NULL DEFAULT 1,
			start_date DATE NULL,
			end_date DATE NULL,
			price DECIMAL(10,2) NULL,
			notes TEXT NULL,
			rel VARCHAR(30) NOT NULL DEFAULT '',
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY ordering (ordering),
			KEY published (published)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$orders = self::orders_table();
		$sql_orders = "CREATE TABLE $orders (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			slot_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			wanted_slot BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			session_id VARCHAR(255) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			title VARCHAR(250) NOT NULL DEFAULT '',
			url VARCHAR(500) NOT NULL DEFAULT '',
			icon_url VARCHAR(500) NOT NULL DEFAULT '',
			amount INT(11) NOT NULL DEFAULT 0,
			currency VARCHAR(3) NOT NULL DEFAULT 'usd',
			duration_days INT(11) NOT NULL DEFAULT 30,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NULL,
			paid_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY status (status)
		) $charset;";
		dbDelta( $sql_orders );

		update_option( self::VERSION_OPTION, self::DB_VERSION );

		if ( ! get_option( IconPress_Settings::OPTION_KEY ) ) {
			add_option( IconPress_Settings::OPTION_KEY, IconPress_Settings::defaults() );
		}

		// Seed the fixed slot grid on first install, like the original
		// install SQL, which inserted width*height empty published rows.
		// IconPress then preloads those rows with the seed dataset
		// (includes/iconpress-seed-data.php): the owner's own entries
		// plus house ads carrying the bundled default icons, so no
		// slot ever renders blank.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $count ) {
			$settings = IconPress_Settings::get();
			self::sync_slots( (int) $settings['width'] * (int) $settings['height'] );
			self::seed_defaults();
		}
	}

	/**
	 * Fill every slot row in the grid with the seed dataset,
	 * cycling the entries. Each seeded row is marked is_default,
	 * the flag that says "house entry": the admin can edit,
	 * replace, or clear it like any other slot, and the store
	 * claims seeded rows first when a purchase comes in.
	 *
	 * Called on first install only (empty table). Returns the
	 * number of rows seeded.
	 *
	 * @return int
	 */
	public static function seed_defaults() {
		if ( ! function_exists( 'iconpress_seed_entries' ) ) {
			return 0;
		}
		$entries = iconpress_seed_entries();
		if ( ! $entries ) {
			return 0;
		}
		$rows   = self::get_slots();
		$seeded = 0;
		foreach ( $rows as $i => $row ) {
			$entry = $entries[ $i % count( $entries ) ];
			$ok    = self::save_slot(
				array(
					'title'      => $entry['title'],
					'url'        => $entry['url'],
					'icon_url'   => 'iconpress-default:' . $entry['icon'],
					'icon_id'    => 0,
					'alt'        => isset( $entry['alt'] ) ? $entry['alt'] : $entry['title'],
					'email'      => '',
					'hits'       => 0,
					'published'  => 1,
					'start_date' => null,
					'end_date'   => null,
					'price'      => null,
					'notes'      => 'Seeded house entry, placed by IconPress on activation. Edit, replace, or clear it any time.',
					'rel'        => isset( $entry['rel'] ) ? $entry['rel'] : '',
					'is_default' => 1,
				),
				(int) $row->id
			);
			if ( false !== $ok ) {
				$seeded++;
			}
		}
		update_option( self::SEEDED_OPTION, ICONPRESS_VERSION );
		return $seeded;
	}

	/**
	 * Resolve a stored icon reference to a real URL. Seeded rows
	 * store "iconpress-default:<file>" so their icons survive
	 * site moves; everything else is already a URL.
	 *
	 * @param string $url Stored icon_url value.
	 * @return string
	 */
	public static function resolve_icon_url( $url ) {
		$url = (string) $url;
		if ( 0 === strpos( $url, 'iconpress-default:' ) ) {
			return ICONPRESS_URL . 'assets/images/defaults/' . basename( substr( $url, strlen( 'iconpress-default:' ) ) );
		}
		return $url;
	}

	/**
	 * Can a store purchase take this slot? A seeded house entry,
	 * a truly empty row, or a placement whose window has fully
	 * expired (it is showing a house ad anyway).
	 *
	 * @param object $row Slot row.
	 * @return bool
	 */
	public static function is_claimable( $row ) {
		if ( ! empty( $row->is_default ) ) {
			return true;
		}
		if ( '' === trim( (string) $row->title ) && '' === trim( (string) $row->url ) ) {
			return true;
		}
		if ( ! empty( $row->end_date ) && '0000-00-00' !== $row->end_date && $row->end_date < current_time( 'Y-m-d' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Find the slot a purchase takes: the buyer's pick when it
	 * is still claimable, then the first seeded house entry,
	 * then the first truly empty row, then the first expired
	 * placement.
	 *
	 * @param int $wanted_id Buyer-picked slot id, 0 for any.
	 * @return object|null
	 */
	public static function find_claimable_slot( $wanted_id = 0 ) {
		if ( $wanted_id > 0 ) {
			$wanted = self::get_slot( $wanted_id );
			if ( $wanted && self::is_claimable( $wanted ) ) {
				return $wanted;
			}
		}
		$rows = self::get_slots();
		foreach ( $rows as $row ) {
			if ( ! empty( $row->is_default ) ) {
				return $row;
			}
		}
		foreach ( $rows as $row ) {
			if ( '' === trim( (string) $row->title ) && '' === trim( (string) $row->url ) ) {
				return $row;
			}
		}
		foreach ( $rows as $row ) {
			if ( self::is_claimable( $row ) ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * All slots in wall order.
	 *
	 * @param int $limit Optional cap.
	 * @return array Array of row objects.
	 */
	public static function get_slots( $limit = 0 ) {
		global $wpdb;
		$table = self::table();
		if ( $limit > 0 ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY ordering ASC, id ASC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY ordering ASC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * One slot by id.
	 *
	 * @param int $id Slot id.
	 * @return object|null
	 */
	public static function get_slot( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Insert or update a slot from a sanitized data array.
	 *
	 * @param array $data Column values (already sanitized by the caller).
	 * @param int   $id   0 to insert, otherwise the slot id.
	 * @return int|false Slot id or false on failure.
	 */
	public static function save_slot( $data, $id = 0 ) {
		global $wpdb;
		$table = self::table();
		if ( $id > 0 ) {
			$ok = $wpdb->update( $table, $data, array( 'id' => $id ) );
			return false === $ok ? false : $id;
		}
		$ok = $wpdb->insert( $table, $data );
		return false === $ok ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Empty a slot in place (the original slot stays in the grid).
	 *
	 * @param int $id Slot id.
	 * @return bool
	 */
	public static function clear_slot( $id ) {
		return false !== self::save_slot(
			array(
				'title'      => '',
				'url'        => '',
				'icon_url'   => '',
				'icon_id'    => 0,
				'alt'        => '',
				'email'      => '',
				'hits'       => 0,
				'published'  => 1,
				'start_date' => null,
				'end_date'   => null,
				'price'      => null,
				'notes'      => '',
				'rel'        => '',
				'is_default' => 0,
			),
			$id
		);
	}

	/**
	 * Delete a slot row entirely.
	 *
	 * @param int $id Slot id.
	 * @return bool
	 */
	public static function delete_slot( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/**
	 * Atomically add one hit, mirroring the original hit task.
	 *
	 * @param int $id Slot id.
	 */
	public static function add_hit( $id ) {
		global $wpdb;
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET hits = hits + 1 WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Keep the number of slot rows equal to the grid size, mirroring
	 * the original admin update() task. Pads with empty published
	 * slots; trims trailing slots, empty ones first, and never trims
	 * a filled slot that still falls inside a larger old grid without
	 * leaving the row in place for the list view.
	 *
	 * @param int $target Desired row count.
	 * @return array{added:int, removed:int}
	 */
	public static function sync_slots( $target ) {
		global $wpdb;
		$table   = self::table();
		$target  = max( 0, intval( $target ) );
		$current = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$added   = 0;
		$removed = 0;

		if ( $current < $target ) {
			$max_order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ordering),0) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			for ( $i = $current; $i < $target; $i++ ) {
				$max_order++;
				if ( false !== $wpdb->insert( $table, array( 'ordering' => $max_order, 'published' => 1 ) ) ) {
					$added++;
				}
			}
		} elseif ( $current > $target ) {
			// Remove trailing empty slots first.
			$empties = $wpdb->get_col( "SELECT id FROM $table WHERE title = '' AND url = '' ORDER BY ordering DESC, id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $empties as $empty_id ) {
				if ( $current - $removed <= $target ) {
					break;
				}
				if ( false !== $wpdb->delete( $table, array( 'id' => (int) $empty_id ) ) ) {
					$removed++;
				}
			}
		}

		self::renumber();
		return array( 'added' => $added, 'removed' => $removed );
	}

	/**
	 * Renumber ordering to a clean 1..N sequence in current order.
	 */
	public static function renumber() {
		global $wpdb;
		$table = self::table();
		$ids   = $wpdb->get_col( "SELECT id FROM $table ORDER BY ordering ASC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n     = 1;
		foreach ( $ids as $id ) {
			$wpdb->update( $table, array( 'ordering' => $n ), array( 'id' => (int) $id ) );
			$n++;
		}
	}

	/**
	 * Is a slot live on the wall right now? Mirrors the original
	 * helper logic: published, has a URL, and today is inside the
	 * start/end window (empty dates are open-ended).
	 *
	 * @param object $row Slot row.
	 * @return bool
	 */
	public static function is_filled( $row ) {
		if ( empty( $row->published ) || '' === trim( (string) $row->url ) ) {
			return false;
		}
		$today = current_time( 'Y-m-d' );
		if ( ! empty( $row->start_date ) && '0000-00-00' !== $row->start_date && $row->start_date > $today ) {
			return false;
		}
		if ( ! empty( $row->end_date ) && '0000-00-00' !== $row->end_date && $row->end_date < $today ) {
			return false;
		}
		return true;
	}

	/**
	 * Named slots with an end date, soonest expiry first.
	 * Powers the Expiring admin screen and dashboard widget, the
	 * rebuild of mod_mafiaicons_admin_notify.
	 *
	 * @return array Rows with a computed days column.
	 */
	public static function get_expiring() {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT *, DATEDIFF(end_date, CURDATE()) AS days FROM $table
			 WHERE title != '' AND end_date IS NOT NULL
			 ORDER BY days ASC"
		);
	}

	/* ------------------------------------------------------------------
	 * Orders (Pro store)
	 * ------------------------------------------------------------------ */

	/**
	 * Record a new store order.
	 *
	 * @param array $data Column values (sanitized by the caller).
	 * @return int|false Order id or false on failure.
	 */
	public static function create_order( $data ) {
		global $wpdb;
		$ok = $wpdb->insert( self::orders_table(), $data );
		return false === $ok ? false : (int) $wpdb->insert_id;
	}

	/**
	 * One order by id.
	 *
	 * @param int $id Order id.
	 * @return object|null
	 */
	public static function get_order( $id ) {
		global $wpdb;
		$table = self::orders_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * One order by its Stripe Checkout session id.
	 *
	 * @param string $session_id Session id (cs_...).
	 * @return object|null
	 */
	public static function get_order_by_session( $session_id ) {
		global $wpdb;
		$table = self::orders_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE session_id = %s", $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Update an order row.
	 *
	 * @param int   $id   Order id.
	 * @param array $data Column values.
	 * @return bool
	 */
	public static function update_order( $id, $data ) {
		global $wpdb;
		return false !== $wpdb->update( self::orders_table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Recent orders, newest first, for the Sales screen.
	 *
	 * @param int $limit Cap.
	 * @return array
	 */
	public static function get_orders( $limit = 100 ) {
		global $wpdb;
		$table = self::orders_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", max( 1, intval( $limit ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
