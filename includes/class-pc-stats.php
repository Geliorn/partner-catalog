<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Own DB table for click/view analytics — see docs/partner-catalog-plan.md
 * section 7.2. Deliberately simple: one row per (contractor, event type, day),
 * incremented via INSERT ... ON DUPLICATE KEY UPDATE. No fraud/bot protection
 * by design — this is a lightweight internal dashboard, not a CPA platform.
 */
class PC_Stats {

	const DB_VERSION_OPTION = 'pc_stats_db_version';
	const DB_VERSION        = '1.0';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ) ); // not plugins_loaded — see PC_Ratings::init()
		add_action( 'template_redirect', array( __CLASS__, 'maybe_track_view' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'pc_stats';
	}

	public static function create_table() {
		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contractor_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(10) NOT NULL,
			stat_date DATE NOT NULL,
			count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY contractor_event_date (contractor_id, event_type, stat_date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/** Runs the same dbDelta on every load if the schema version changed — cheap no-op otherwise. */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_table();
		}
	}

	public static function increment( $contractor_id, $event_type ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO " . self::table_name() . " (contractor_id, event_type, stat_date, count) VALUES (%d, %s, %s, 1)
				ON DUPLICATE KEY UPDATE count = count + 1",
				$contractor_id,
				$event_type,
				current_time( 'Y-m-d' )
			)
		);
	}

	/** Transient holding popular_ids(); short-lived so a new leader shows up the same day. */
	const POPULAR_TRANSIENT = 'pc_popular_ids';

	/**
	 * «Часто выбирают» (decided 2026-10-17: automatic, by clicks): the top 3
	 * contractors by outbound clicks over the last 30 days. A floor of 3 clicks
	 * keeps a single stray click on a quiet catalog from earning the pill.
	 *
	 * @return int[]
	 */
	public static function popular_ids() {
		$cached = get_transient( self::POPULAR_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT contractor_id FROM ' . self::table_name() . "
				WHERE event_type = 'click' AND stat_date >= %s
				GROUP BY contractor_id
				HAVING SUM(count) >= 3
				ORDER BY SUM(count) DESC
				LIMIT 3",
				gmdate( 'Y-m-d', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS )
			)
		);
		$ids = array_map( 'intval', (array) $rows );
		set_transient( self::POPULAR_TRANSIENT, $ids, 3 * HOUR_IN_SECONDS );
		return $ids;
	}

	/** Counts a `view` on every real front-end hit of a single contractor page. No dedup — intentionally simple. */
	public static function maybe_track_view() {
		if ( is_singular( 'contractor' ) ) {
			self::increment( get_queried_object_id(), 'view' );
		}
	}

	/**
	 * Resolves taxonomy/contractor filters from the dashboard's GET params into
	 * a list of contractor post IDs, or null when no such filter is active
	 * (meaning: don't restrict by ID at all).
	 */
	private static function filtered_contractor_ids( array $filters ) {
		if ( ! empty( $filters['contractor_id'] ) ) {
			return array( (int) $filters['contractor_id'] );
		}

		$tax_query = array();
		foreach ( array( 'contractor_type', 'contractor_specialization' ) as $taxonomy ) {
			if ( ! empty( $filters[ $taxonomy ] ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => (int) $filters[ $taxonomy ],
				);
			}
		}

		if ( empty( $tax_query ) ) {
			return null;
		}

		return get_posts(
			array(
				'post_type'      => 'contractor',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
	}

	private static function where_clause( array $filters ) {
		global $wpdb;
		$where  = array( 'stat_date BETWEEN %s AND %s' );
		$params = array( $filters['date_from'], $filters['date_to'] );

		$ids = self::filtered_contractor_ids( $filters );
		if ( is_array( $ids ) ) {
			if ( empty( $ids ) ) {
				// No contractor matches the filter — force an empty result set.
				$where[]  = '1 = 0';
			} else {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$where[]      = "contractor_id IN ({$placeholders})";
				$params       = array_merge( $params, $ids );
			}
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/** KPI row: totals for the filtered period. */
	public static function get_totals( array $filters ) {
		global $wpdb;
		list( $where, $params ) = self::where_clause( $filters );

		$sql  = "SELECT event_type, SUM(count) AS total FROM " . self::table_name() . " WHERE {$where} GROUP BY event_type";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$totals = array( 'view' => 0, 'click' => 0 );
		foreach ( (array) $rows as $row ) {
			$totals[ $row['event_type'] ] = (int) $row['total'];
		}
		return $totals;
	}

	/** Daily series for the chart — one row per day with both event types as columns. */
	public static function get_daily_series( array $filters ) {
		global $wpdb;
		list( $where, $params ) = self::where_clause( $filters );

		$sql  = "SELECT stat_date, event_type, SUM(count) AS total FROM " . self::table_name() . " WHERE {$where} GROUP BY stat_date, event_type ORDER BY stat_date ASC";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$by_date = array();
		foreach ( (array) $rows as $row ) {
			$by_date[ $row['stat_date'] ][ $row['event_type'] ] = (int) $row['total'];
		}

		$series = array();
		$cursor = strtotime( $filters['date_from'] );
		$end    = strtotime( $filters['date_to'] );
		while ( $cursor <= $end ) {
			$day      = gmdate( 'Y-m-d', $cursor );
			$series[] = array(
				'date'  => $day,
				'views' => $by_date[ $day ]['view'] ?? 0,
				'clicks' => $by_date[ $day ]['click'] ?? 0,
			);
			$cursor += DAY_IN_SECONDS;
		}
		return $series;
	}

	/** Per-contractor breakdown, sorted by clicks — for the dashboard table. */
	public static function get_top_contractors( array $filters, $limit = 50 ) {
		global $wpdb;
		list( $where, $params ) = self::where_clause( $filters );
		$params[] = (int) $limit;

		$sql = "SELECT contractor_id,
				SUM(CASE WHEN event_type = 'view' THEN count ELSE 0 END) AS views,
				SUM(CASE WHEN event_type = 'click' THEN count ELSE 0 END) AS clicks
			FROM " . self::table_name() . "
			WHERE {$where}
			GROUP BY contractor_id
			ORDER BY clicks DESC
			LIMIT %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}
}
