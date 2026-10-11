<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! PC_Settings::integration_enabled( 'filters' ) ) {
	return;
}

$facets = array(
	'contractor_type'           => 'Тип сервиса',
	'contractor_specialization' => 'Специализация',
	'contractor_region'         => 'Регион',
);

// Skip the facet matching the taxonomy we're already on — the URL itself
// is that filter, showing it again in the sidebar would just duplicate it.
$current_taxonomy = is_tax() ? get_queried_object()->taxonomy ?? '' : '';

$base_url = PC_Filters::base_url();
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-filters' ) );

echo '<form ' . $wrapper_attributes . ' method="get" action="' . esc_url( $base_url ) . '">';

foreach ( $facets as $taxonomy => $label ) {
	if ( $taxonomy === $current_taxonomy ) {
		continue;
	}
	$param = PC_Filters::TAXONOMY_PARAMS[ $taxonomy ];
	$terms = PC_Filters::facet_counts( $taxonomy );
	if ( empty( $terms ) ) {
		continue;
	}
	$selected = PC_Filters::selected( $param );

	echo '<fieldset class="pc-filter-group">';
	echo '<legend>' . esc_html( $label ) . '</legend>';
	foreach ( $terms as $term ) {
		$checked_id = 'pc-filter-' . $param . '-' . $term->term_id;
		printf(
			'<label class="pc-filter-option" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s"%4$s> %5$s <span class="pc-filter-count">%6$d</span></label>',
			esc_attr( $checked_id ),
			esc_attr( $param ),
			esc_attr( $term->slug ),
			in_array( $term->slug, $selected, true ) ? ' checked' : '',
			esc_html( $term->name ),
			(int) $term->cnt
		);
	}
	echo '</fieldset>';
}

echo '<button type="submit" class="pc-filter-apply">Применить</button>';
if ( PC_Filters::is_active() ) {
	echo '<a class="pc-filter-reset" href="' . esc_url( $base_url ) . '">Сбросить</a>';
}

echo '</form>';
