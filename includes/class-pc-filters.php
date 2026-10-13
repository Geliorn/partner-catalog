<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Own archive sidebar filter (type/specialization + usage flags) and the
 * archive sort order — replaces Filter Everything as a dependency (decided
 * 2026-09-29, identical shape to LAGR_Filters — see that class's docblock in
 * llm-agent-github-repos for the full reasoning, duplicated rather than shared
 * per this plugin's "independently portable" convention).
 *
 * The region facet was dropped in 0.4.0 together with the taxonomy itself (see
 * PC_CPT). What replaced it is the thing the reference catalog actually filters
 * by and this one couldn't: the usage flags ("бесплатно", "есть бесплатный
 * тариф", "есть пробный период", "есть промокод") — meta, not terms, so they
 * filter without polluting the term vocabulary.
 */
class PC_Filters {

	/** taxonomy => GET param name. Kept short/ASCII, and none of these
	 * collide with a WP_Query legacy query var (unlike `tag` — see
	 * LAGR_Filters's docblock for what that broke there). */
	const TAXONOMY_PARAMS = array(
		'contractor_type'           => 'type',
		'contractor_specialization' => 'spec',
	);

	/** Multi-value GET param carrying the usage flags: ?flag[]=free&flag[]=trial */
	const FLAG_PARAM = 'flag';

	/** Single-value GET param carrying the sort order. */
	const SORT_PARAM = 'sort';

	/**
	 * Flag slug (what appears in the URL) => label. `promo` is derived from the
	 * promo-code meta rather than being a stored flag of its own — a code that
	 * has expired must stop matching on its own, without anyone editing the
	 * record (see flag_meta_clause()).
	 */
	public static function flags() {
		$flags = array();
		foreach ( PC_Meta_Fields::flags() as $meta_key => $label ) {
			$flags[ str_replace( array( '_pc_is_', '_pc_has_' ), '', $meta_key ) ] = $label;
		}
		$flags['promo'] = 'Есть промокод';
		return $flags;
	}

	/** Sort slug => label. 'default' is the archive's own order (newest first). */
	public static function sort_options() {
		return array(
			'default' => 'По умолчанию',
			'rating'  => 'По рейтингу',
			'reviews' => 'По количеству оценок',
			'alpha'   => 'По алфавиту',
		);
	}

	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_to_query' ) );
	}

	public static function apply_to_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->is_post_type_archive( 'contractor' ) && ! $query->is_tax( array_keys( self::TAXONOMY_PARAMS ) ) ) {
			return;
		}

		$tax_query = (array) $query->get( 'tax_query' );
		foreach ( self::TAXONOMY_PARAMS as $taxonomy => $param ) {
			$values = self::selected( $param );
			if ( $values ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $values,
				);
			}
		}
		if ( $tax_query ) {
			if ( count( $tax_query ) > 1 && ! isset( $tax_query['relation'] ) ) {
				$tax_query['relation'] = 'AND';
			}
			$query->set( 'tax_query', $tax_query );
		}

		$meta_query = (array) $query->get( 'meta_query' );
		foreach ( self::selected_flags() as $flag ) {
			$clause = self::flag_meta_clause( $flag );
			if ( $clause ) {
				$meta_query[] = $clause;
			}
		}

		self::apply_sort( $query, $meta_query );
	}

	/**
	 * Sorting by a meta value needs that meta to exist on every row or the
	 * post vanishes from the archive — PC_Ratings seeds _pc_rating_avg /
	 * _pc_rating_count at 0 for exactly this reason. The clause is NAMED
	 * ('pc_sort') and referenced by name in `orderby`, rather than going
	 * through `meta_key`, so it can't collide with the flag clauses above.
	 */
	private static function apply_sort( $query, array $meta_query ) {
		$sort = self::current_sort();

		if ( 'alpha' === $sort ) {
			$query->set( 'orderby', array( 'title' => 'ASC' ) );
		} elseif ( 'rating' === $sort || 'reviews' === $sort ) {
			$meta_query['pc_sort'] = array(
				'key' => 'rating' === $sort ? PC_Ratings::META_AVG : PC_Ratings::META_COUNT,
				// DECIMAL, not NUMERIC: meta_query's NUMERIC casts to SIGNED,
				// which truncates — 4.9 and 4.1 would both sort as 4 and the
				// "по рейтингу" order would be mostly arbitrary.
				'type'    => 'rating' === $sort ? 'DECIMAL(10,2)' : 'NUMERIC',
				'compare' => 'EXISTS',
			);
			$query->set( 'orderby', array( 'pc_sort' => 'DESC', 'date' => 'DESC' ) );
		}

		if ( $meta_query ) {
			if ( count( $meta_query ) > 1 && ! isset( $meta_query['relation'] ) ) {
				$meta_query['relation'] = 'AND';
			}
			$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
	}

	/** The meta_query clause one flag stands for, or array() if unknown. */
	public static function flag_meta_clause( $flag ) {
		if ( 'promo' === $flag ) {
			return array(
				'relation' => 'AND',
				array(
					'key'     => '_pc_promo_code',
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_pc_promo_expires',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_pc_promo_expires',
						'value'   => '',
						'compare' => '=',
					),
					array(
						'key'     => '_pc_promo_expires',
						'value'   => current_time( 'Y-m-d' ),
						'compare' => '>=',
					),
				),
			);
		}

		foreach ( array_keys( PC_Meta_Fields::flags() ) as $meta_key ) {
			if ( str_replace( array( '_pc_is_', '_pc_has_' ), '', $meta_key ) === $flag ) {
				return array(
					'key'     => $meta_key,
					'value'   => '1',
					'compare' => '=',
				);
			}
		}

		return array();
	}

	/** @return string[] Flag slugs selected in the URL. */
	public static function selected_flags() {
		if ( empty( $_GET[ self::FLAG_PARAM ] ) || ! is_array( $_GET[ self::FLAG_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only filter state.
			return array();
		}
		$known = array_keys( self::flags() );
		$out   = array();
		foreach ( wp_unslash( $_GET[ self::FLAG_PARAM ] ) as $value ) { // phpcs:ignore WordPress.Security.NonceVerification
			$value = sanitize_key( $value );
			if ( in_array( $value, $known, true ) ) {
				$out[] = $value;
			}
		}
		return $out;
	}

	/** @return string One of sort_options()' keys. */
	public static function current_sort() {
		$sort = isset( $_GET[ self::SORT_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::SORT_PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return array_key_exists( $sort, self::sort_options() ) ? $sort : 'default';
	}

	/** @return string[] Sanitized slugs selected for a given GET param. */
	public static function selected( $param ) {
		if ( empty( $_GET[ $param ] ) || ! is_array( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only filter state, not a form action.
			return array();
		}
		return array_map( 'sanitize_title', wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function is_active() {
		foreach ( self::TAXONOMY_PARAMS as $param ) {
			if ( self::selected( $param ) ) {
				return true;
			}
		}
		return self::selected_flags() || 'default' !== self::current_sort();
	}

	/** Current archive/taxonomy URL with the query string stripped — the form's action + the "reset" link target. */
	public static function base_url() {
		$path = strtok( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', '?' ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		return esc_url( home_url( $path ) );
	}

	/**
	 * Published contractor IDs matching the current archive/taxonomy context
	 * and every active filter EXCEPT the one being counted — so a facet always
	 * shows what you'd get by adding it to the current selection, and ticking a
	 * second box inside the same group still widens the result (OR within a
	 * group, AND between groups) rather than showing 0 everywhere.
	 *
	 * @param string $skip_taxonomy Taxonomy whose own selection to ignore.
	 * @param string $skip_flag     Flag slug whose own selection to ignore.
	 * @return int[]
	 */
	private static function scoped_ids( $skip_taxonomy = '', $skip_flag = '' ) {
		$args = array(
			'post_type'      => 'contractor',
			'post_status'    => 'publish',
			'posts_per_page' => 2000,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$tax_query = array();

		if ( is_tax() ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Term && $queried->taxonomy !== $skip_taxonomy ) {
				$tax_query[] = array(
					'taxonomy' => $queried->taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $queried->term_id ),
				);
			}
		}

		foreach ( self::TAXONOMY_PARAMS as $tax => $param ) {
			if ( $tax === $skip_taxonomy ) {
				continue;
			}
			$values = self::selected( $param );
			if ( $values ) {
				$tax_query[] = array(
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => $values,
				);
			}
		}

		if ( $tax_query ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$meta_query = array();
		foreach ( self::selected_flags() as $flag ) {
			if ( $flag === $skip_flag ) {
				continue;
			}
			$clause = self::flag_meta_clause( $flag );
			if ( $clause ) {
				$meta_query[] = $clause;
			}
		}
		if ( $meta_query ) {
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		return get_posts( $args );
	}

	/**
	 * How many records each usage flag would leave, in the current context.
	 *
	 * @return array<string, int> flag slug => count
	 */
	public static function flag_counts() {
		$out = array();

		foreach ( array_keys( self::flags() ) as $flag ) {
			$ids = self::scoped_ids( '', $flag );
			if ( empty( $ids ) ) {
				$out[ $flag ] = 0;
				continue;
			}
			$clause = self::flag_meta_clause( $flag );
			if ( ! $clause ) {
				$out[ $flag ] = 0;
				continue;
			}
			$matching     = get_posts(
				array(
					'post_type'      => 'contractor',
					'post_status'    => 'publish',
					'post__in'       => $ids,
					'posts_per_page' => 2000,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array( $clause ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			$out[ $flag ] = count( $matching );
		}

		return $out;
	}

	/**
	 * Non-empty terms for one taxonomy facet, scoped to the current
	 * archive/taxonomy context plus every OTHER active filter.
	 *
	 * @return array<int, object{term_id:int, slug:string, name:string, cnt:int}>
	 */
	public static function facet_counts( $taxonomy ) {
		$ids = self::scoped_ids( $taxonomy );
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $placeholders is a fixed-shape %d list, not user input.
		$sql = $wpdb->prepare(
			"SELECT t.term_id, t.slug, t.name, COUNT(DISTINCT tr.object_id) AS cnt
			 FROM {$wpdb->term_relationships} tr
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			 WHERE tt.taxonomy = %s AND tr.object_id IN ($placeholders)
			 GROUP BY t.term_id
			 ORDER BY cnt DESC, t.name ASC",
			array_merge( array( $taxonomy ), $ids )
		);

		return $wpdb->get_results( $sql );
	}
}
