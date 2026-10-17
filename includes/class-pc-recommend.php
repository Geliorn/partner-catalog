<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * «Где попробовать» — the catalog module inside articles (v2 mockup, variant
 * V1 «врезка в тексте»). Three suppliers, each a link to its catalog page
 * (not to the supplier's site): type pill, stars, «Подойдёт, если…». No price
 * and no ad mark (decided 2026-10-17). Moves article traffic into the catalog.
 *
 * Ranking is by the manual `_pc_priority` (0–100) only (decided 2026-10-17 —
 * no matching on the article's tags). Rotation: the server renders a pool of
 * up to POOL_SIZE candidates, the top 3 by priority visible; a small script
 * then re-picks 3 by weighted random sampling without replacement
 * (Efraimidis–Spirakis: key = u^(1/w)). Doing the pick in the browser keeps
 * rotation alive behind a full-page cache, which would otherwise freeze one
 * pick for everyone; without JS the priority order is what shows.
 */
class PC_Recommend {

	const POOL_SIZE  = 8;
	const SHOW       = 3;
	const BLOCK      = 'partner-catalog/recommended';
	const POOL_CACHE = 'pc_recommend_pool';

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'auto_insert' ), 20 );
		add_action( 'init', array( __CLASS__, 'register_script' ) );
		add_action( 'save_post_contractor', array( __CLASS__, 'flush_pool' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_pool' ) );
	}

	public static function register_script() {
		wp_register_script( 'pc-recommend', PC_PLUGIN_URL . 'assets/js/pc-recommend.js', array(), PC_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	public static function flush_pool() {
		delete_transient( self::POOL_CACHE );
	}

	/** @return int[] Published contractors with priority > 0, highest first. */
	public static function pool() {
		$ids = get_transient( self::POOL_CACHE );
		if ( is_array( $ids ) ) {
			return $ids;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'contractor',
				'post_status'    => 'publish',
				'posts_per_page' => self::POOL_SIZE,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_pc_priority', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 0, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'   => '>',
				'meta_type'      => 'NUMERIC',
				'orderby'        => array(
					'meta_value_num' => 'DESC',
					'date'           => 'DESC',
				),
			)
		);
		$ids = array_map( 'intval', $ids );
		set_transient( self::POOL_CACHE, $ids, HOUR_IN_SECONDS );
		return $ids;
	}

	public static function post_types() {
		return array_filter( array_map( 'trim', explode( ',', (string) PC_Settings::get( 'recommend_post_types', 'post' ) ) ) );
	}

	/** Puts the module after the article's 2nd H2 (or 3rd paragraph) unless the block is already in the post. */
	public static function auto_insert( $content ) {
		if ( ! PC_Settings::integration_enabled( 'recommend' ) || is_admin() || is_feed() ) {
			return $content;
		}
		if ( ! is_singular( self::post_types() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( has_block( self::BLOCK, get_post() ) ) {
			return $content;
		}

		$module = self::render();
		if ( '' === $module ) {
			return $content;
		}

		foreach ( array( array( '</h2>', 2 ), array( '</p>', 3 ) ) as $anchor ) {
			list( $tag, $nth ) = $anchor;
			$pos = -1;
			for ( $i = 0; $i < $nth; $i++ ) {
				$pos = stripos( $content, $tag, $pos + 1 );
				if ( false === $pos ) {
					break;
				}
			}
			if ( false === $pos || $pos < 0 ) {
				continue;
			}
			// After the 2nd heading means after the paragraph that follows it,
			// so the module never splits a heading from its own text.
			$at = $pos + strlen( $tag );
			if ( '</h2>' === $tag ) {
				$next = stripos( $content, '</p>', $at );
				if ( false !== $next ) {
					$at = $next + 4;
				}
			}
			return substr( $content, 0, $at ) . $module . substr( $content, $at );
		}

		return $content; // too short for a module in the middle — none.
	}

	/** The module markup ('' when no supplier has a priority). */
	public static function render( $wrapper_attributes = '' ) {
		$pool = self::pool();
		if ( ! $pool ) {
			return '';
		}
		wp_enqueue_script( 'pc-recommend' );

		$rows = '';
		foreach ( $pool as $i => $id ) {
			$type = PC_Render::type_name( $id );
			$fit  = trim( (string) get_post_meta( $id, '_pc_fit', true ) );
			$why  = '' !== $fit ? 'Подойдёт, если ' . rtrim( $fit, '.' ) : trim( (string) get_post_meta( $id, '_pc_short_description', true ) );
			$url  = get_permalink( $id );

			$rows .= sprintf(
				'<li class="pc-rec-row" data-weight="%1$d"%2$s>%3$s<div class="pc-rec-name"><a href="%4$s">%5$s</a>%6$s%7$s</div>%8$s<a class="pc-rec-more" href="%4$s" tabindex="-1" aria-hidden="true">Подробнее →</a></li>',
				max( 1, (int) get_post_meta( $id, '_pc_priority', true ) ),
				$i >= self::SHOW ? ' hidden' : '',
				PC_Render::logo( $id, 'pc-logo-sm' ),
				esc_url( $url ),
				esc_html( get_the_title( $id ) ),
				'' !== $type ? '<span class="pc-badge pc-badge-type">' . esc_html( $type ) . '</span>' : '',
				PC_Render::rating( $id, 12 ),
				'' !== $why ? '<div class="pc-rec-why">' . esc_html( $why ) . '</div>' : ''
			);
		}

		$total = (int) wp_count_posts( 'contractor' )->publish;
		$more  = max( 0, $total - self::SHOW );

		$attrs = '' !== $wrapper_attributes ? $wrapper_attributes : 'class="pc-rec"';

		return '<aside ' . $attrs . ' aria-label="Сервисы из каталога">'
			. '<p class="pc-rec-kicker">Где попробовать</p>'
			. '<ul class="pc-rec-rows">' . $rows . '</ul>'
			. '<div class="pc-rec-foot">'
			. ( $more ? '<span>Ещё ' . esc_html( $more . ' ' . PC_Ratings::ru_plural( $more, 'сервис', 'сервиса', 'сервисов' ) ) . ' с фильтрами «без VPN» и «оплата картой РФ»</span>' : '<span></span>' )
			. '<a href="' . esc_url( get_post_type_archive_link( 'contractor' ) ) . '">Все сервисы →</a></div>'
			. '</aside>';
	}
}
