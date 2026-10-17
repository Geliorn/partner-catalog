/**
 * Auto-submit on change — a plain GET form works without any JS at all
 * (the "Применить" button covers that), this just removes the extra click.
 * Not in the phone drawer, where «Готово» submits the whole selection.
 */
( function() {
	'use strict';

	document.addEventListener( 'change', function( event ) {
		var field = event.target.closest( ".pc-filters input[type=\"checkbox\"]" );
		if ( ! field ) {
			return;
		}
		var form = field.closest( 'form' );
		// In the phone drawer several boxes are ticked first, then «Готово»
		// (a submit button) applies them all at once.
		if ( form && ! form.classList.contains( 'is-open' ) ) {
			form.submit();
		}
	} );
} )();
