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
);

// Skip the facet matching the taxonomy we're already on — the URL itself
// is that filter, showing it again in the sidebar would just duplicate it.
$current_taxonomy = is_tax() ? get_queried_object()->taxonomy ?? '' : '';

$base_url = PC_Filters::base_url();
$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'pc-filters' ) );

echo '<form ' . $wrapper_attributes . ' method="get" action="' . esc_url( $base_url ) . '">';

// Sorting lives inside the same GET form as the facets, so changing it keeps
// every active filter instead of resetting the archive.
$sort = PC_Filters::current_sort();
echo '<div class="pc-filter-sort">';
echo '<label for="pc-sort">Сортировать по</label>';
echo '<select id="pc-sort" name="' . esc_attr( PC_Filters::SORT_PARAM ) . '">';
foreach ( PC_Filters::sort_options() as $value => $label ) {
	printf(
		'<option value="%1$s"%2$s>%3$s</option>',
		esc_attr( $value ),
		selected( $sort, $value, false ),
		esc_html( $label )
	);
}
echo '</select>';
echo '</div>';

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

// Usage flags — meta-based, so unlike the taxonomy facets above a zero-count
// option is still shown (unchecking it has to stay possible, and the count
// tells the visitor there is nothing there without them clicking).
$flag_counts    = PC_Filters::flag_counts();
$selected_flags = PC_Filters::selected_flags();
$flags          = array_filter(
	PC_Filters::flags(),
	function ( $label, $flag ) use ( $flag_counts, $selected_flags ) {
		return ! empty( $flag_counts[ $flag ] ) || in_array( $flag, $selected_flags, true );
	},
	ARRAY_FILTER_USE_BOTH
);

if ( $flags ) {
	echo '<fieldset class="pc-filter-group">';
	echo '<legend>Условия</legend>';
	foreach ( $flags as $flag => $label ) {
		$checked_id = 'pc-filter-flag-' . $flag;
		printf(
			'<label class="pc-filter-option" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s"%4$s> %5$s <span class="pc-filter-count">%6$d</span></label>',
			esc_attr( $checked_id ),
			esc_attr( PC_Filters::FLAG_PARAM ),
			esc_attr( $flag ),
			in_array( $flag, $selected_flags, true ) ? ' checked' : '',
			esc_html( $label ),
			(int) ( $flag_counts[ $flag ] ?? 0 )
		);
	}
	echo '</fieldset>';
}

echo '<button type="submit" class="pc-filter-apply">Применить</button>';
if ( PC_Filters::is_active() ) {
	echo '<a class="pc-filter-reset" href="' . esc_url( $base_url ) . '">Сбросить</a>';
}

echo '</form>';
