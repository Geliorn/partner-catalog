<?php
/**
 * Above the card grid (v2 mockup A): result count, the active filters as
 * removable chips, the sort select and the grid/list switch. On phones it
 * also carries the «Фильтры · N» button that opens the sidebar as a drawer.
 *
 * Sorting is a tiny GET form that re-posts every active filter as hidden
 * fields, so changing the order never drops the selection.
 *
 * @var array $attributes Unused beyond className.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;
$found = (int) $wp_query->found_posts;

$chips = array();
foreach ( PC_Filters::selected_flags() as $flag ) {
	$chips[] = array( PC_Filters::flags()[ $flag ], PC_Filters::toggle_flag_url( $flag ) );
}
foreach ( PC_Filters::TAXONOMY_PARAMS as $taxonomy => $param ) {
	foreach ( PC_Filters::selected( $param ) as $slug ) {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term ) {
			$chips[] = array( $term->name, PC_Filters::url_with( array( $param => array_values( array_diff( PC_Filters::selected( $param ), array( $slug ) ) ) ) ) );
		}
	}
}

echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'pc-toolbar' ) ) . '>';

echo '<div class="pc-toolbar-left">';
printf(
	'<button type="button" class="pc-btn pc-btn-ghost pc-btn-sm pc-filters-open" aria-controls="pc-filters">%1$sФильтры%2$s</button>',
	PC_Render::icon( 'filter' ), // phpcs:ignore WordPress.Security.EscapeOutput
	$chips ? ' · ' . count( $chips ) : ''
);
printf( '<b class="pc-found">%1$d %2$s</b>', $found, esc_html( PC_Ratings::ru_plural( $found, 'сервис', 'сервиса', 'сервисов' ) ) );
foreach ( $chips as $chip ) {
	printf( '<a class="pc-chip" href="%1$s">%2$s <span aria-hidden="true">✕</span><span class="screen-reader-text">убрать фильтр</span></a>', esc_url( $chip[1] ), esc_html( $chip[0] ) );
}
echo '</div>';

echo '<div class="pc-toolbar-right">';
echo '<form method="get" action="' . esc_url( PC_Filters::base_url() ) . '" class="pc-sort-form">';
foreach ( PC_Filters::TAXONOMY_PARAMS as $param ) {
	foreach ( PC_Filters::selected( $param ) as $slug ) {
		echo '<input type="hidden" name="' . esc_attr( $param ) . '[]" value="' . esc_attr( $slug ) . '">';
	}
}
foreach ( PC_Filters::selected_flags() as $flag ) {
	echo '<input type="hidden" name="' . esc_attr( PC_Filters::FLAG_PARAM ) . '[]" value="' . esc_attr( $flag ) . '">';
}
echo '<label class="screen-reader-text" for="pc-sort">Сортировка</label>';
echo '<select id="pc-sort" class="pc-select" name="' . esc_attr( PC_Filters::SORT_PARAM ) . '">';
foreach ( PC_Filters::sort_options() as $value => $label ) {
	printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( PC_Filters::current_sort(), $value, false ), esc_html( $label ) );
}
echo '</select>';
echo '<noscript><button type="submit" class="pc-btn pc-btn-ghost pc-btn-sm">OK</button></noscript>';
echo '</form>';

echo '<div class="pc-seg" role="group" aria-label="Вид списка">';
printf( '<button type="button" data-pc-view="grid" aria-pressed="true" title="Плитка">%s<span class="screen-reader-text">Плитка</span></button>', PC_Render::icon( 'grid' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
printf( '<button type="button" data-pc-view="list" aria-pressed="false" title="Список">%s<span class="screen-reader-text">Список</span></button>', PC_Render::icon( 'list' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
echo '</div>';
echo '</div>';

echo '</div>';
