<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Own site-rating widget for contractor — replaces WP-PostRatings as a
 * dependency (decided 2026-09-04, same call made for llm-agent-github-repos —
 * see LAGR_Ratings's docblock there for the full reasoning).
 *
 * 0.4.0 turned the single 1-5 score into SEVERAL criteria, each on its own 1-5
 * scale, because one number says nothing useful about an AI service: "цена" and
 * "качество результата" are the two things people actually disagree about, and
 * averaging them into one star count throws exactly that away. Deliberately
 * WITHOUT review text: free-form text is personal data of the author, which
 * brings consent/moderation/takedown duties the site isn't set up for yet (the
 * user's call, 2026-10-13) — so this stays a rating widget, not a review
 * system, and nothing a visitor types is stored.
 *
 * Storage, per contractor:
 *   _pc_rating_{criterion}_total / _pc_rating_{criterion}_count — per criterion
 *   _pc_rating_total / _pc_rating_count — the OVERALL aggregate, where one
 *       voter contributes the mean of their own criteria (so a voter who rated
 *       two criteria doesn't outweigh one who rated five), kept under the old
 *       keys so pre-0.4.0 votes stay valid and get_average() means the same.
 *   _pc_rating_avg — the same overall average, cached as a sortable number.
 *       Sorting by rating can't recompute total/count per row, and a post
 *       MISSING the meta would silently drop out of a meta-ordered query — so
 *       every contractor gets this key seeded at 0 (see ensure_sort_meta()).
 */
class PC_Ratings {

	const META_TOTAL = '_pc_rating_total';
	const META_COUNT = '_pc_rating_count';
	const META_AVG   = '_pc_rating_avg';
	/** Sortable rank: the average with enough votes, else a tiny fraction of it (sinks 1–2-vote records). */
	const META_RANK  = '_pc_rating_rank';

	/**
	 * Below this many votes a score is still SHOWN (stars are always shown,
	 * decided 2026-10-17) but kept out of schema.org and sunk in "по рейтингу".
	 */
	const MIN_VOTES = 3;

	const POST_TYPE    = 'contractor';
	const NONCE_ACTION = 'pc_rate';

	/** Bumped whenever the backfill below needs to run again. */
	const BACKFILL_OPTION = 'pc_ratings_backfill';
	const BACKFILL_VERSION = '2'; // 2: + META_RANK (0.5.0)

	/**
	 * The criteria a visitor rates. Keys are stored in meta keys, so they are
	 * [a-z_] only and must not be renamed without a migration. Filterable so a
	 * sibling site in the network can use its own set without forking.
	 *
	 * @return array<string, string> key => label
	 */
	public static function criteria() {
		return (array) apply_filters(
			'pc_rating_criteria',
			array(
				'quality' => 'Качество результата',
				'price'   => 'Цена и тарифы',
				'ux'      => 'Удобство использования',
				'support' => 'Поддержка',
				'speed'   => 'Стабильность и скорость',
			)
		);
	}

	public static function init() {
		add_action( 'wp_ajax_pc_rate', array( __CLASS__, 'handle_vote' ) );
		add_action( 'wp_ajax_nopriv_pc_rate', array( __CLASS__, 'handle_vote' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'ensure_sort_meta' ) );
		// `init`, not `plugins_loaded`: init() itself runs ON plugins_loaded
		// (pc_boot), and a callback added to the hook currently firing, at the
		// same priority, is never called — the backfill silently never ran.
		add_action( 'init', array( __CLASS__, 'maybe_backfill' ) );
	}

	private static function criterion_key( $criterion, $suffix ) {
		return '_pc_rating_' . $criterion . '_' . $suffix;
	}

	public static function get_average( $post_id ) {
		$count = self::get_count( $post_id );
		if ( $count < 1 ) {
			return 0.0;
		}
		$total = (float) get_post_meta( $post_id, self::META_TOTAL, true );
		return round( $total / $count, 1 );
	}

	public static function get_count( $post_id ) {
		return (int) get_post_meta( $post_id, self::META_COUNT, true );
	}

	/** @return array{average: float, count: int} for one criterion. */
	public static function get_criterion( $post_id, $criterion ) {
		$count = (int) get_post_meta( $post_id, self::criterion_key( $criterion, 'count' ), true );
		if ( $count < 1 ) {
			return array( 'average' => 0.0, 'count' => 0 );
		}
		$total = (float) get_post_meta( $post_id, self::criterion_key( $criterion, 'total' ), true );
		return array( 'average' => round( $total / $count, 1 ), 'count' => $count );
	}

	public static function has_voted( $post_id ) {
		return isset( $_COOKIE[ 'pc_voted_' . $post_id ] );
	}

	/**
	 * Seeds the sortable keys at 0 so "сортировать по рейтингу" never hides a
	 * contractor nobody has rated yet — WP_Query ordered by a meta value drops
	 * every post without that meta row, which would quietly shrink the archive.
	 */
	public static function ensure_sort_meta( $post_id ) {
		add_post_meta( $post_id, self::META_AVG, 0, true );
		add_post_meta( $post_id, self::META_COUNT, 0, true );
		add_post_meta( $post_id, self::META_RANK, 0, true );
	}

	/** One-time pass so contractors created before 0.4.0 are sortable too. */
	public static function maybe_backfill() {
		if ( get_option( self::BACKFILL_OPTION ) === self::BACKFILL_VERSION ) {
			return;
		}

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			self::ensure_sort_meta( $id );
			self::refresh_average( $id );
		}

		update_option( self::BACKFILL_OPTION, self::BACKFILL_VERSION, false );
	}

	/** Recomputes and stores META_AVG / META_RANK from the overall total/count. */
	public static function refresh_average( $post_id ) {
		$avg = self::get_average( $post_id );
		update_post_meta( $post_id, self::META_AVG, $avg );
		update_post_meta( $post_id, self::META_RANK, self::get_count( $post_id ) >= self::MIN_VOTES ? $avg : round( $avg / 100, 3 ) );
	}

	/** The criterion with the highest average (≥1 vote), for the «Сильная сторона» tile. */
	public static function best_criterion( $post_id ) {
		$best = null;
		foreach ( self::criteria() as $key => $label ) {
			$c = self::get_criterion( $post_id, $key );
			if ( $c['count'] > 0 && ( null === $best || $c['average'] > $best['average'] ) ) {
				$best = array( 'label' => $label, 'average' => $c['average'] );
			}
		}
		return $best;
	}

	/** Classic 3-form Russian plural — see LAGR_Ratings::ru_plural() for the same helper. */
	public static function ru_plural( $n, $one, $few, $many ) {
		$n10  = $n % 10;
		$n100 = $n % 100;
		if ( $n100 >= 11 && $n100 <= 14 ) {
			return $many;
		}
		if ( 1 === $n10 ) {
			return $one;
		}
		if ( $n10 >= 2 && $n10 <= 4 ) {
			return $few;
		}
		return $many;
	}

	/**
	 * Atomic increments via SQL rather than get+update: two visitors voting in
	 * the same second would otherwise overwrite each other's total. Note the
	 * overall total is a FLOAT (a voter's own mean), so it increments with %f.
	 */
	private static function bump( $post_id, $meta_key, $delta, $float = false ) {
		global $wpdb;
		add_post_meta( $post_id, $meta_key, 0, true ); // no-op if the row already exists.
		$wpdb->query(
			$wpdb->prepare(
				$float
					? "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + %f WHERE post_id = %d AND meta_key = %s"
					: "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + %d WHERE post_id = %d AND meta_key = %s",
				$delta,
				$post_id,
				$meta_key
			)
		);
	}

	public static function handle_vote() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Некорректный запрос.' ), 400 );
		}

		$criteria = self::criteria();
		$raw      = isset( $_POST['values'] ) && is_array( $_POST['values'] ) ? wp_unslash( $_POST['values'] ) : array();
		$values   = array();
		foreach ( $raw as $criterion => $value ) {
			$criterion = sanitize_key( $criterion );
			$value     = absint( $value );
			if ( isset( $criteria[ $criterion ] ) && $value >= 1 && $value <= 5 ) {
				$values[ $criterion ] = $value;
			}
		}

		if ( empty( $values ) ) {
			wp_send_json_error( array( 'message' => 'Поставьте хотя бы одну оценку.' ), 400 );
		}

		if ( self::has_voted( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Вы уже оценивали эту запись.' ), 409 );
		}

		foreach ( $values as $criterion => $value ) {
			self::bump( $post_id, self::criterion_key( $criterion, 'total' ), $value );
			self::bump( $post_id, self::criterion_key( $criterion, 'count' ), 1 );
		}

		// One voter = one point in the overall score, weighted by their own mean
		// rather than by how many criteria they happened to fill in.
		self::bump( $post_id, self::META_TOTAL, array_sum( $values ) / count( $values ), true );
		self::bump( $post_id, self::META_COUNT, 1 );

		clean_post_cache( $post_id );
		self::refresh_average( $post_id );

		setcookie( 'pc_voted_' . $post_id, '1', time() + YEAR_IN_SECONDS, ( COOKIEPATH ? COOKIEPATH : '/' ), COOKIE_DOMAIN );

		$per_criterion = array();
		foreach ( array_keys( $criteria ) as $criterion ) {
			$per_criterion[ $criterion ] = self::get_criterion( $post_id, $criterion );
		}

		wp_send_json_success(
			array(
				'average'  => self::get_average( $post_id ),
				'count'    => self::get_count( $post_id ),
				'criteria' => $per_criterion,
			)
		);
	}
}
