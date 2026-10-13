/**
 * Copy-to-clipboard for the promo code. navigator.clipboard is unavailable on
 * plain http (non-secure context) and in older browsers, so the code stays
 * visible as text and selectable either way — the button is a convenience, not
 * the only way to get the code.
 */
( function() {
	'use strict';

	document.addEventListener( 'click', function( event ) {
		var button = event.target.closest( '.pc-promo-copy' );
		if ( ! button ) {
			return;
		}

		var code = button.getAttribute( 'data-code' ) || '';
		var done = function() {
			var original = button.textContent;
			button.textContent = button.getAttribute( 'data-done' ) || 'OK';
			button.classList.add( 'is-copied' );
			window.setTimeout( function() {
				button.textContent = original;
				button.classList.remove( 'is-copied' );
			}, 2000 );
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( code ).then( done ).catch( function() {} );
			return;
		}

		var field = document.createElement( 'textarea' );
		field.value = code;
		field.setAttribute( 'readonly', 'readonly' );
		field.style.position = 'fixed';
		field.style.opacity = '0';
		document.body.appendChild( field );
		field.select();
		try {
			document.execCommand( 'copy' );
			done();
		} catch ( e ) {}
		document.body.removeChild( field );
	} );
} )();
