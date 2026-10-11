/**
 * Auto-submit on change — a plain GET form works without any JS at all
 * (the "Применить" button covers that), this just removes the extra click.
 */
( function() {
	'use strict';

	document.addEventListener( 'change', function( event ) {
		var field = event.target.closest( '.pc-filters input[type="checkbox"], .pc-filters select' );
		if ( ! field ) {
			return;
		}
		var form = field.closest( 'form' );
		if ( form ) {
			form.submit();
		}
	} );
} )();
