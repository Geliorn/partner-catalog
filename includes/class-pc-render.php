<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared server-side markup for the v2 design (docs/mockups/partner-catalog-v2/):
 * SVG stars, logo with letter fallback, key facts, the archive card and the
 * mini card. One place, so the card, the comparison table, the supplier page
 * and the "Где попробовать" module can't drift apart — the mockups' rule was
 * "the card is the page header, compressed".
 *
 * Everything returned here is already escaped HTML.
 */
class PC_Render {

	/** Material "star" — the glyph Google draws in rating snippets. */
	const STAR_PATH = 'M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z';

	/** Line icons (24×24, stroke = currentColor), same set as the mockups. */
	private static function icon_paths() {
		return array(
			'check'  => '<path d="M20 6 9 17l-5-5"/>',
			'x'      => '<path d="M18 6 6 18M6 6l12 12"/>',
			'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
			'card'   => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19"/>',
			'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5z"/><path d="m3 13 9 5 9-5"/>',
			'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
			'server' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01"/>',
			'tag'    => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><path d="M7.5 7.5h.01"/>',
			'ext'    => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
			'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
			'list'   => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
			'filter' => '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
			'spark'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
		);
	}

	public static function icon( $name, $class = 'pc-ico' ) {
		$paths = self::icon_paths();
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Five stars with a fractional fill measured per star (4,6 fills 60% of
	 * the fifth star, not 60% of the strip). 0 = five grey stars.
	 */
	public static function stars( $value, $size = 15 ) {
		$value = max( 0.0, min( 5.0, (float) $value ) );
		$gap   = 2;
		$width = $size * 5 + $gap * 4;
		$paths = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$paths .= sprintf( '<path transform="translate(%1$s 0) scale(%2$s)" d="%3$s"/>', $i * ( $size + $gap ), round( $size / 24, 4 ), self::STAR_PATH );
		}
		$full  = (int) floor( $value );
		$clip  = $full * ( $size + $gap ) + ( $value - $full ) * $size;
		$id    = wp_unique_id( 'pc-stars-' );
		$label = $value > 0 ? sprintf( 'Оценка %s из 5', number_format_i18n( $value, 1 ) ) : 'Оценок пока нет';

		return sprintf(
			'<svg class="pc-stars" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-label="%3$s">'
				. '<defs><clipPath id="%4$s"><rect width="%5$s" height="%2$d"/></clipPath></defs>'
				. '<g class="pc-stars-empty">%6$s</g><g class="pc-stars-full" clip-path="url(#%4$s)">%6$s</g></svg>',
			$width,
			$size,
			esc_attr( $label ),
			esc_attr( $id ),
			round( $clip, 2 ),
			$paths
		);
	}

	/**
	 * Google-snippet order: "4,6 ★★★★★ (128)". Always stars (decided
	 * 2026-10-17): any number of votes shows the real score, no votes shows
	 * five grey stars and nothing else.
	 */
	public static function rating( $post_id, $size = 15, $long = false ) {
		if ( ! PC_Settings::integration_enabled( 'ratings' ) ) {
			return '';
		}
		$count = PC_Ratings::get_count( $post_id );
		if ( $count < 1 ) {
			return '<span class="pc-rating-line is-empty" title="Оценок пока нет">' . self::stars( 0, $size ) . '</span>';
		}
		$avg = PC_Ratings::get_average( $post_id );
		$cnt = $long
			? $count . ' ' . PC_Ratings::ru_plural( $count, 'оценка', 'оценки', 'оценок' )
			: '(' . $count . ')';
		return '<span class="pc-rating-line"><span class="pc-r-val">' . esc_html( number_format_i18n( $avg, 1 ) ) . '</span>'
			. self::stars( $avg, $size ) . '<span class="pc-r-count">' . esc_html( $cnt ) . '</span></span>';
	}

	/** Featured image, or the first letter of the title on an accent tile. */
	public static function logo( $post_id, $class = '' ) {
		$class = trim( 'pc-logo ' . $class );
		$thumb = get_the_post_thumbnail( $post_id, 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) );
		if ( $thumb ) {
			return '<span class="' . esc_attr( $class ) . '" aria-hidden="true">' . $thumb . '</span>';
		}
		$title  = trim( wp_strip_all_tags( get_the_title( $post_id ) ) );
		$letter = '' !== $title ? mb_strtoupper( mb_substr( $title, 0, 1 ) ) : '?';
		return '<span class="' . esc_attr( $class . ' is-letter' ) . '" aria-hidden="true">' . esc_html( $letter ) . '</span>';
	}

	/** @return WP_Term[] */
	private static function terms( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		return is_array( $terms ) ? $terms : array();
	}

	/** First contractor_type name ('' if none). */
	public static function type_name( $post_id ) {
		$types = self::terms( $post_id, 'contractor_type' );
		return $types ? $types[0]->name : '';
	}

	/** "Агрегатор нейросетей · Тексты, Изображения" — type + specializations as one quiet line, linked. */
	public static function meta_line( $post_id ) {
		$parts = array();
		foreach ( self::terms( $post_id, 'contractor_type' ) as $term ) {
			$parts[] = '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
		}
		$specs = array();
		foreach ( self::terms( $post_id, 'contractor_specialization' ) as $term ) {
			$specs[] = '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
		}
		if ( $specs ) {
			$parts[] = implode( ', ', $specs );
		}
		return $parts ? '<p class="pc-meta-line">' . implode( ' · ', $parts ) . '</p>' : '';
	}

	/**
	 * The card's pills: «Часто выбирают» first, then free > free tier > trial,
	 * at most $max (2 on the archive card — decided 2026-10-17).
	 */
	public static function badges( $post_id, $max = 2 ) {
		$items = array();
		if ( in_array( (int) $post_id, PC_Stats::popular_ids(), true ) ) {
			$items[] = array( 'hot', 'Часто выбирают' );
		}
		$flags = array(
			'_pc_is_free'       => array( 'ok', 'Бесплатный' ),
			'_pc_has_free_tier' => array( 'ok', 'Бесплатный тариф' ),
			'_pc_has_trial'     => array( 'info', 'Пробный период' ),
		);
		foreach ( $flags as $key => $badge ) {
			if ( '1' === get_post_meta( $post_id, $key, true ) ) {
				$items[] = $badge;
			}
		}
		$items = array_slice( $items, 0, $max );
		if ( ! $items ) {
			return '';
		}
		$out = '<ul class="pc-badges">';
		foreach ( $items as $item ) {
			$out .= '<li class="pc-badge pc-badge-' . esc_attr( $item[0] ) . '">' . esc_html( $item[1] ) . '</li>';
		}
		return $out . '</ul>';
	}

	public static function facts( $post_id, $max = 3 ) {
		$facts = PC_Meta_Fields::facts( $post_id, $max );
		if ( ! $facts ) {
			return '';
		}
		$out = '<ul class="pc-facts">';
		foreach ( $facts as $fact ) {
			$out .= '<li class="pc-fact is-' . esc_attr( $fact[0] ) . '">' . self::icon( $fact[1] ) . '<span>' . esc_html( $fact[2] ) . '</span></li>';
		}
		return $out . '</ul>';
	}

	public static function price( $post_id, $with_note = true ) {
		$text = PC_Meta_Fields::price_text( $post_id );
		if ( '' === $text ) {
			return '';
		}
		$note = $with_note ? PC_Meta_Fields::price_note( $post_id ) : '';
		return '<div class="pc-price">' . esc_html( $text ) . ( '' !== $note ? '<small>' . esc_html( $note ) . '</small>' : '' ) . '</div>';
	}

	/** «Подробнее →» — the only action on listing cards: the visitor goes through the supplier page. */
	public static function details_button( $post_id, $class = '' ) {
		return '<a class="' . esc_attr( trim( 'pc-btn pc-btn-primary pc-btn-sm ' . $class ) ) . '" href="' . esc_url( get_permalink( $post_id ) ) . '">Подробнее →</a>';
	}

	/** Archive card (pattern A): logo, name, meta line, rating, ≤2 pills, description, 3 facts, price + «Подробнее». */
	public static function card( $post_id ) {
		$desc = trim( (string) get_post_meta( $post_id, '_pc_short_description', true ) );

		return '<article class="pc-card">'
			. '<div class="pc-card-head">' . self::logo( $post_id ) . '<div>'
			. '<h3 class="pc-card-name"><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></h3>'
			. self::meta_line( $post_id ) . '</div></div>'
			. self::rating( $post_id )
			. self::badges( $post_id, 2 )
			. ( '' !== $desc ? '<p class="pc-card-desc">' . esc_html( $desc ) . '</p>' : '' )
			. self::facts( $post_id, 3 )
			. '<div class="pc-card-foot">' . self::price( $post_id ) . '<div class="pc-card-actions">' . self::details_button( $post_id ) . '</div></div>'
			. '</article>';
	}

	/** Mini card ("Альтернативы"): price on its own line, «Подробнее →» under it; footer pinned to the bottom. */
	public static function mini( $post_id ) {
		$type = self::type_name( $post_id );
		return '<article class="pc-mini">'
			. '<div class="pc-mini-head">' . self::logo( $post_id, 'pc-logo-sm' ) . '<div>'
			. '<h3 class="pc-mini-name"><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></h3>'
			. ( '' !== $type ? '<p class="pc-meta-line">' . esc_html( $type ) . '</p>' : '' )
			. '</div></div>'
			. self::rating( $post_id, 13 )
			. self::facts( $post_id, 2 )
			. '<div class="pc-mini-foot">' . self::price( $post_id, false ) . '<div class="pc-mini-actions">' . self::details_button( $post_id ) . '</div></div>'
			. '</article>';
	}
}
