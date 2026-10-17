/**
 * «Сообщить о неточности»: toggles the reasons popover and posts the picked
 * reason (admin-ajax `pc_report`). No text field on purpose — see PC_Reports.
 */
( function() {
	'use strict';

	document.addEventListener( 'click', function( event ) {
		var toggle = event.target.closest( '.pc-report-btn' );
		var openReport = document.querySelector( '.pc-report.is-open' );
		if ( toggle ) {
			var report = toggle.closest( '.pc-report' );
			var open = ! report.classList.contains( 'is-open' );
			report.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			return;
		}

		var reason = event.target.closest( '.pc-report-pop [data-reason]' );
		if ( reason ) {
			var box = reason.closest( '.pc-verify' );
			var pop = reason.closest( '.pc-report-pop' );
			var body = new URLSearchParams();
			body.set( 'action', 'pc_report' );
			body.set( 'post_id', box.getAttribute( 'data-post-id' ) );
			body.set( 'nonce', box.getAttribute( 'data-nonce' ) );
			body.set( 'reason', reason.getAttribute( 'data-reason' ) );
			pop.querySelectorAll( 'button' ).forEach( function( b ) {
				b.disabled = true;
			} );
			fetch( box.getAttribute( 'data-ajax-url' ), {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} )
				.then( function( r ) { return r.json(); } )
				.then( function( json ) {
					pop.textContent = json && json.data && json.data.message ? json.data.message : 'Спасибо!';
				} )
				.catch( function() {
					pop.textContent = 'Не удалось отправить. Попробуйте позже.';
				} );
			return;
		}

		if ( openReport && ! openReport.contains( event.target ) ) {
			openReport.classList.remove( 'is-open' );
		}
	} );
} )();
