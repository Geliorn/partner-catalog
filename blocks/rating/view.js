/**
 * Front-end voting behaviour for partner-catalog/rating. Enqueued
 * automatically by WP core wherever the block appears (block.json's
 * "viewScript") — no manual wp_enqueue_script() needed.
 *
 * Unlike the single-score version this replaced (and unlike the sibling
 * llm-agent-github-repos widget), a click here only SELECTS a score: the vote
 * is sent once, for all criteria together, when "Оценить" is pressed — one
 * request per voter, so the per-browser cookie guard still matches one
 * submission, and a half-filled form can't lock the visitor out.
 */
( function() {
	'use strict';

	function paint( row, value ) {
		row.querySelectorAll( '.pc-rating-star' ).forEach( function( star, index ) {
			star.classList.toggle( 'is-selected', index < value );
		} );
	}

	function selections( wrap ) {
		var values = {};
		wrap.querySelectorAll( '.pc-rating-row' ).forEach( function( row ) {
			var picked = row.getAttribute( 'data-selected' );
			if ( picked ) {
				values[ row.getAttribute( 'data-criterion' ) ] = picked;
			}
		} );
		return values;
	}

	document.addEventListener( 'click', function( event ) {
		var star = event.target.closest( '.pc-rating-star' );
		if ( star && ! star.disabled && 'BUTTON' === star.tagName ) {
			var wrap = star.closest( '.pc-rating' );
			var row  = star.closest( '.pc-rating-row' );
			if ( ! wrap || ! row || wrap.classList.contains( 'pc-rating-readonly' ) || wrap.classList.contains( 'pc-rating-voted' ) ) {
				return;
			}
			var value = star.getAttribute( 'data-value' );
			row.setAttribute( 'data-selected', value );
			paint( row, Number( value ) );

			var submit = wrap.querySelector( '.pc-rating-submit' );
			if ( submit ) {
				submit.disabled = Object.keys( selections( wrap ) ).length === 0;
			}
			return;
		}

		var button = event.target.closest( '.pc-rating-submit' );
		if ( ! button || button.disabled ) {
			return;
		}

		var box = button.closest( '.pc-rating' );
		if ( ! box ) {
			return;
		}

		var values = selections( box );
		if ( ! Object.keys( values ).length ) {
			return;
		}

		var body = new URLSearchParams();
		body.set( 'action', 'pc_rate' );
		body.set( 'post_id', box.getAttribute( 'data-post-id' ) );
		body.set( 'nonce', box.getAttribute( 'data-nonce' ) );
		Object.keys( values ).forEach( function( criterion ) {
			body.set( 'values[' + criterion + ']', values[ criterion ] );
		} );

		button.disabled = true;

		fetch( box.getAttribute( 'data-ajax-url' ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function( r ) { return r.json(); } )
			.then( function( json ) {
				if ( ! json || ! json.success ) {
					button.disabled = false;
					return;
				}

				box.classList.add( 'pc-rating-voted' );
				box.querySelectorAll( '.pc-rating-star' ).forEach( function( el ) {
					el.disabled = true;
				} );

				var overall = box.querySelector( '.pc-rating-overall .pc-rating-score' );
				if ( overall ) {
					overall.classList.remove( 'pc-rating-empty' );
					overall.innerHTML = '<b>' + Number( json.data.average ).toFixed( 1 ).replace( '.', ',' ) +
						'</b> / 5 · ' + json.data.count;
				}

				box.querySelectorAll( '.pc-rating-row' ).forEach( function( row ) {
					var data  = json.data.criteria[ row.getAttribute( 'data-criterion' ) ];
					var score = row.querySelector( '.pc-rating-crit-score' );
					if ( data && score && data.count > 0 ) {
						score.textContent = Number( data.average ).toFixed( 1 ).replace( '.', ',' );
					}
				} );

				var note = document.createElement( 'p' );
				note.className = 'pc-rating-note pc-rating-thanks';
				note.textContent = 'Спасибо, ваша оценка учтена.';
				button.replaceWith( note );
			} )
			.catch( function() {
				button.disabled = false;
			} );
	} );
} )();
