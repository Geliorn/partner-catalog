/**
 * Front-end voting behaviour for partner-catalog/rating. Enqueued
 * automatically by WP core wherever the block appears (block.json's
 * "viewScript") — no manual wp_enqueue_script() needed. Identical to
 * llm-agent-github-repos/blocks/rating/view.js apart from the action name.
 */
( function() {
	'use strict';

	document.addEventListener( 'click', function( event ) {
		var button = event.target.closest( '.pc-rating-star' );
		if ( ! button || button.disabled || 'BUTTON' !== button.tagName ) {
			return;
		}

		var wrap = button.closest( '.pc-rating' );
		if ( ! wrap || wrap.classList.contains( 'pc-rating-readonly' ) ) {
			return;
		}

		var body = new URLSearchParams();
		body.set( 'action', 'pc_rate' );
		body.set( 'post_id', wrap.getAttribute( 'data-post-id' ) );
		body.set( 'value', button.getAttribute( 'data-value' ) );
		body.set( 'nonce', wrap.getAttribute( 'data-nonce' ) );

		fetch( wrap.getAttribute( 'data-ajax-url' ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function( r ) { return r.json(); } )
			.then( function( json ) {
				if ( ! json || ! json.success ) {
					return;
				}
				wrap.classList.add( 'pc-rating-voted' );
				wrap.querySelectorAll( '.pc-rating-star' ).forEach( function( star ) {
					star.disabled = true;
				} );
				var scoreEl = wrap.querySelector( '.pc-rating-score' );
				if ( scoreEl ) {
					scoreEl.classList.remove( 'pc-rating-empty' );
					var avg = Number( json.data.average ).toFixed( 1 ).replace( '.', ',' );
					scoreEl.innerHTML = '<b>' + avg + '</b> / 5 · ' + json.data.count + ' оценок';
				}
			} )
			.catch( function() {} );
	} );
} )();
