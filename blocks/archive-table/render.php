<?php
/**
 * Archive variant B (v2 mockup): type tabs, quick-filter toggles, the
 * comparison table and pagination — one block over the MAIN archive query,
 * so filtering/sorting/paging go through PC_Filters::apply_to_query() exactly
 * like the card grid does. Every control is a plain link (or a GET select),
 * nothing here needs JS except the select's submit-on-change.
 *
 * No «На сайт» and no promo label in the rows (decided 2026-10-17): the only
 * action is «Подробнее →», the visitor goes through the supplier page.
 *
 * @var array $attributes Unused beyond className.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;

$yes = function ( $text ) {
	return '<span class="pc-yn is-y">' . PC_Render::icon( 'check' ) . esc_html( $text ) . '</span>';
};
$no  = function ( $text ) {
	return '<span class="pc-yn is-n">' . PC_Render::icon( 'x' ) . esc_html( $text ) . '</span>';
};

echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'pc-archive-b' ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

// ---- type tabs (not on a type page — there the URL already is the tab).
if ( ! is_tax( 'contractor_type' ) ) {
	$types    = get_terms( array( 'taxonomy' => 'contractor_type', 'hide_empty' => true ) );
	$selected = PC_Filters::selected( 'type' );
	if ( is_array( $types ) && $types ) {
		$total = (int) wp_count_posts( 'contractor' )->publish;
		echo '<nav class="pc-topics" aria-label="Типы сервисов">';
		printf( '<a class="%1$s" href="%2$s">Все <span>%3$d</span></a>', $selected ? '' : 'is-on', esc_url( PC_Filters::url_with( array( 'type' => null ) ) ), $total );
		foreach ( $types as $type ) {
			$on = array( $type->slug ) === $selected;
			printf( '<a class="%1$s" href="%2$s">%3$s <span>%4$d</span></a>', $on ? 'is-on' : '', esc_url( PC_Filters::url_with( array( 'type' => $on ? null : array( $type->slug ) ) ) ), esc_html( $type->name ), (int) $type->count );
		}
		echo '</nav>';
	}
}

// ---- quick filters
$quick = array(
	'no_vpn'    => array( 'globe', 'Без VPN' ),
	'free_tier' => array( 'spark', 'Есть бесплатный тариф' ),
	'promo'     => array( 'tag', 'Есть промокод' ),
	'data_ru'   => array( 'shield', 'Данные в РФ' ),
);
$active = PC_Filters::selected_flags();
echo '<div class="pc-qf" role="group" aria-label="Быстрые фильтры">';
foreach ( $quick as $flag => $q ) {
	$on = in_array( $flag, $active, true );
	printf(
		'<a class="pc-qf-t" href="%1$s" aria-pressed="%2$s">%3$s%4$s</a>',
		esc_url( PC_Filters::toggle_flag_url( $flag ) ),
		$on ? 'true' : 'false',
		PC_Render::icon( $q[0] ), // phpcs:ignore WordPress.Security.EscapeOutput
		esc_html( $q[1] )
	);
}
if ( ! is_tax( 'contractor_specialization' ) ) {
	$specs = get_terms( array( 'taxonomy' => 'contractor_specialization', 'hide_empty' => true ) );
	if ( is_array( $specs ) && $specs ) {
		$current_spec = PC_Filters::selected( 'spec' );
		echo '<span class="pc-qf-sep"></span>';
		echo '<select class="pc-select pc-qf-spec" aria-label="Специализация" onchange="if(this.value)location.href=this.value">';
		printf( '<option value="%s">Специализация: все</option>', esc_url( PC_Filters::url_with( array( 'spec' => null ) ) ) );
		foreach ( $specs as $spec ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_url( PC_Filters::url_with( array( 'spec' => array( $spec->slug ) ) ) ), selected( array( $spec->slug ) === $current_spec, true, false ), esc_html( $spec->name ) );
		}
		echo '</select>';
	}
}
printf( '<span class="pc-qf-count">Найдено: %d</span>', (int) $wp_query->found_posts );
if ( PC_Filters::is_active() ) {
	echo '<a class="pc-f-reset" href="' . esc_url( PC_Filters::base_url() ) . '">Сбросить</a>';
}
echo '</div>';

// ---- table
if ( ! $wp_query->have_posts() ) {
	echo '<p class="pc-empty">По этим условиям сервисов нет. Попробуйте убрать один из фильтров.</p></div>';
	return;
}

$sort       = PC_Filters::current_sort();
$sort_link  = function ( $label, $key ) use ( $sort ) {
	if ( 'price' === $key ) {
		$target = 'price' === $sort ? 'price_desc' : 'price';
		$dir    = 'price' === $sort ? 'asc' : ( 'price_desc' === $sort ? 'desc' : '' );
	} else {
		$target = 'rating';
		$dir    = 'rating' === $sort ? 'desc' : '';
	}
	return '<a class="pc-th-sort" href="' . esc_url( PC_Filters::url_with( array( PC_Filters::SORT_PARAM => $target ) ) ) . '" data-dir="' . esc_attr( $dir ) . '">' . esc_html( $label ) . '</a>';
};

$per_page = (int) $wp_query->get( 'posts_per_page' );
$offset   = max( 0, ( max( 1, (int) get_query_var( 'paged' ) ) - 1 ) * max( 1, $per_page ) );

echo '<div class="pc-tbl-wrap"><table class="pc-tbl"><thead><tr>';
echo '<th class="pc-c-rank">#</th><th>Сервис</th>';
echo '<th>' . $sort_link( 'Рейтинг', 'rating' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
echo '<th>Без VPN</th><th>Платформы</th>';
echo '<th>' . $sort_link( 'Цена', 'price' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
echo '<th><span class="screen-reader-text">Действия</span></th></tr></thead><tbody>';

foreach ( $wp_query->posts as $i => $post ) {
	$id   = $post->ID;
	$type = PC_Render::type_name( $id );
	$fit  = trim( (string) get_post_meta( $id, '_pc_fit', true ) );
	$sub  = trim( $type . ( '' !== $fit ? ' — ' . $fit : '' ) );

	$vpn = (string) get_post_meta( $id, '_pc_vpn', true );
	$vpn_cell = 'no' === $vpn ? $yes( 'Без VPN' ) : ( 'yes' === $vpn ? $no( 'Нужен VPN' ) : '<span class="pc-yn is-u">—</span>' );

	echo '<tr>';
	echo '<td class="pc-c-rank">' . (int) ( $offset + $i + 1 ) . '</td>';
	echo '<td class="pc-c-svc"><div class="pc-svc">' . PC_Render::logo( $id, 'pc-logo-sm' ) . '<div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<a class="pc-svc-name" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
	if ( '' !== $sub ) {
		echo '<div class="pc-svc-sub">' . esc_html( $sub ) . '</div>';
	}
	echo '</div></div></td>';
	echo '<td class="pc-c-rating">' . PC_Render::rating( $id, 13 ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<td class="pc-c-vpn">' . $vpn_cell . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<td class="pc-c-plat">' . esc_html( implode( ', ', PC_Meta_Fields::platform_labels( $id ) ) ) . '</td>';
	echo '<td class="pc-c-price">' . PC_Render::price( $id ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<td class="pc-c-go">' . PC_Render::details_button( $id ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</tr>';
}
echo '</tbody></table></div>';

echo '<p class="pc-legend">Рейтинг — средняя оценка посетителей по 5 критериям; при сортировке по рейтингу сервисы с 1–2 оценками идут после остальных.</p>';

$links = paginate_links(
	array(
		'total'     => (int) $wp_query->max_num_pages,
		'current'   => max( 1, (int) get_query_var( 'paged' ) ),
		'prev_text' => '←',
		'next_text' => 'Далее →',
		'type'      => 'plain',
	)
);
if ( $links ) {
	echo '<nav class="pc-pager" aria-label="Страницы">' . $links . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput -- core-escaped.
}

echo '</div>';
