<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Own "related contractors" lookup (shown as «Альтернативы» since 0.5.0) — replaces the Contextual Related Posts
 * shortcode (decided 2026-09-04, see class-pc-ratings.php docblock). Matches
 * on EITHER shared contractor_type OR shared contractor_specialization —
 * contractor has two meaningful taxonomy axes (unlike github_repo's single
 * repo_topic), so an OR tax_query casts the right net: two integrators of
 * the same platform, or two providers of the same service type, both count
 * as "related".
 */
class PC_Related {

	public static function get_related( $post_id, $limit = 3 ) {
		$type_terms = wp_get_post_terms( $post_id, 'contractor_type', array( 'fields' => 'ids' ) );
		$spec_terms = wp_get_post_terms( $post_id, 'contractor_specialization', array( 'fields' => 'ids' ) );

		$tax_query = array( 'relation' => 'OR' );
		if ( ! empty( $type_terms ) && ! is_wp_error( $type_terms ) ) {
			$tax_query[] = array(
				'taxonomy' => 'contractor_type',
				'field'    => 'term_id',
				'terms'    => $type_terms,
			);
		}
		if ( ! empty( $spec_terms ) && ! is_wp_error( $spec_terms ) ) {
			$tax_query[] = array(
				'taxonomy' => 'contractor_specialization',
				'field'    => 'term_id',
				'terms'    => $spec_terms,
			);
		}
		if ( count( $tax_query ) < 2 ) {
			return array(); // no shared-taxonomy signal to match on.
		}

		return get_posts(
			array(
				'post_type'      => 'contractor',
				'post_status'    => 'publish',
				'post__not_in'   => array( $post_id ),
				'posts_per_page' => $limit,
				// Best-rated first (rank sinks 1–2-vote records, see PC_Ratings);
				// every contractor has the key seeded, so none drop out.
				'meta_key'       => PC_Ratings::META_RANK, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => array(
					'meta_value_num' => 'DESC',
					'date'           => 'DESC',
				),
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);
	}
}
