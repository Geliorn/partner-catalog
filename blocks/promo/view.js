/**
 * «Показать код»: un-blurs the code, copies it, and reports a
 * `partner_promo_copy` Metrika goal when pc-analytics is configured.
 * navigator.clipboard is unavailable on plain http and in older browsers, so
 * the revealed code stays selectable text either way.
 */
( function() {
	'use strict';

	document.addEventListener( 'click', function( event ) {
		var button = event.target.closest( '.pc-promo-copy' );
		if ( ! button ) {
			return;
		}

		var code = button.getAttribute( 'data-code' ) || '';
		var shown = button.parentNode.querySelector( '.pc-ticket-code' );
		if ( shown ) {
			shown.classList.add( 'is-shown' );
			shown.removeAttribute( 'aria-hidden' );
		}
		button.textContent = button.getAttribute( 'data-done' ) || 'OK';
		button.classList.add( 'is-copied' );

		if ( window.pcAnalytics && window.pcAnalytics.metrikaCounterId && typeof window.ym === 'function' ) {
			window.ym( window.pcAnalytics.metrikaCounterId, 'reachGoal', 'partner_promo_copy' );
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( code ).catch( function() {} );
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
		} catch ( e ) {}
		document.body.removeChild( field );
	} );
} )();
