<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Own archive sidebar filter (type/specialization/region) — replaces Filter
 * Everything as a dependency (decided 2026-09-29, identical shape to
 * LAGR_Filters — see that class's docblock in llm-agent-github-repos for
 * the full reasoning, duplicated rather than shared per this plugin's
 * "independently portable" convention).
 */
class PC_Filters {

	/** taxonomy => GET param name. Kept short/ASCII, and none of these
	 * collide with a WP_Query legacy query var (unlike `tag` — see
	 * LAGR_Filters's docblock for what that broke there). */
	const TAXONOMY_PARAMS = array(
		'contractor_type'           => 'type',
		'contractor_specialization' => 'spec',
		'contractor_region'         => 'region',
	);

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
		return false;
	}

	/** Current archive/taxonomy URL with the query string stripped — the form's action + the "reset" link target. */
	public static function base_url() {
		$path = strtok( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', '?' ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		return esc_url( home_url( $path ) );
	}

	/**
	 * Non-empty terms for one taxonomy facet, scoped to the current
	 * archive/taxonomy context plus every OTHER active filter.
	 *
	 * @return array<int, object{term_id:int, slug:string, name:string, cnt:int}>
	 */
	public static function facet_counts( $taxonomy ) {
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
			if ( $queried instanceof WP_Term && $queried->taxonomy !== $taxonomy ) {
				$tax_query[] = array(
					'taxonomy' => $queried->taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $queried->term_id ),
				);
			}
		}

		foreach ( self::TAXONOMY_PARAMS as $tax => $param ) {
			if ( $tax === $taxonomy ) {
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

		$ids = get_posts( $args );
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
