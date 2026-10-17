/**
 * Voting for partner-catalog/rating. A click only SELECTS a score; the vote
 * is sent once for all criteria together on «Отправить оценку» — one request
 * per voter, so the per-browser cookie guard matches one submission, and a
 * half-filled form can't lock the visitor out. Hover previews the fill.
 */
( function() {
	'use strict';

	var STAR = 'M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z';
	var uid = 0;

	function fmt( n ) {
		return ( Math.round( n * 10 ) / 10 ).toFixed( 1 ).replace( '.', ',' );
	}

	function plural( n, a, b, c ) {
		var m10 = n % 10, m100 = n % 100;
		if ( m100 >= 11 && m100 <= 14 ) { return c; }
		if ( 1 === m10 ) { return a; }
		if ( m10 >= 2 && m10 <= 4 ) { return b; }
		return c;
	}

	/** Same geometry as PC_Render::stars(). */
	function stars( value, size ) {
		var gap = 2, w = size * 5 + gap * 4, paths = '', id = 'pc-stars-js-' + ( ++uid );
		for ( var i = 0; i < 5; i++ ) {
			paths += '<path transform="translate(' + ( i * ( size + gap ) ) + ' 0) scale(' + ( size / 24 ) + ')" d="' + STAR + '"/>';
		}
		var full = Math.floor( value ), clip = full * ( size + gap ) + ( value - full ) * size;
		return '<svg class="pc-stars" width="' + w + '" height="' + size + '" viewBox="0 0 ' + w + ' ' + size + '" role="img" aria-label="Оценка ' + fmt( value ) + ' из 5">' +
			'<defs><clipPath id="' + id + '"><rect width="' + clip + '" height="' + size + '"/></clipPath></defs>' +
			'<g class="pc-stars-empty">' + paths + '</g><g class="pc-stars-full" clip-path="url(#' + id + ')">' + paths + '</g></svg>';
	}

	function paint( row, value ) {
		row.querySelectorAll( '.pc-star-input button' ).forEach( function( b, i ) {
			b.classList.toggle( 'is-on', i < value );
		} );
	}

	function selections( box ) {
		var values = {};
		box.querySelectorAll( '.pc-rate-row' ).forEach( function( row ) {
			var v = row.getAttribute( 'data-selected' );
			if ( v ) {
				values[ row.getAttribute( 'data-criterion' ) ] = v;
			}
		} );
		return values;
	}

	document.addEventListener( 'mouseover', function( event ) {
		var b = event.target.closest( '.pc-star-input button' );
		if ( b ) {
			paint( b.closest( '.pc-rate-row' ), Number( b.getAttribute( 'data-value' ) ) );
		}
	} );
	document.addEventListener( 'mouseout', function( event ) {
		var input = event.target.closest( '.pc-star-input' );
		if ( input && ! input.contains( event.relatedTarget ) ) {
			var row = input.closest( '.pc-rate-row' );
			paint( row, Number( row.getAttribute( 'data-selected' ) || 0 ) );
		}
	} );

	document.addEventListener( 'click', function( event ) {
		var star = event.target.closest( '.pc-star-input button' );
		if ( star ) {
			var row = star.closest( '.pc-rate-row' );
			var box = star.closest( '.pc-rates' );
			row.setAttribute( 'data-selected', star.getAttribute( 'data-value' ) );
			paint( row, Number( star.getAttribute( 'data-value' ) ) );
			var submit = box.querySelector( '.pc-rate-submit' );
			if ( submit ) {
				submit.disabled = ! Object.keys( selections( box ) ).length;
			}
			return;
		}

		var button = event.target.closest( '.pc-rate-submit' );
		if ( ! button || button.disabled ) {
			return;
		}
		var wrap = button.closest( '.pc-rates' );
		var values = selections( wrap );
		if ( ! Object.keys( values ).length ) {
			return;
		}

		var body = new URLSearchParams();
		body.set( 'action', 'pc_rate' );
		body.set( 'post_id', wrap.getAttribute( 'data-post-id' ) );
		body.set( 'nonce', wrap.getAttribute( 'data-nonce' ) );
		Object.keys( values ).forEach( function( k ) {
			body.set( 'values[' + k + ']', values[ k ] );
		} );
		button.disabled = true;

		fetch( wrap.getAttribute( 'data-ajax-url' ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function( r ) { return r.json(); } )
			.then( function( json ) {
				var form = wrap.querySelector( '.pc-rate-me' );
				if ( ! json || ! json.success ) {
					button.disabled = false;
					if ( json && json.data && json.data.message && form ) {
						form.insertAdjacentHTML( 'beforeend', '<p class="pc-rate-note">' + json.data.message + '</p>' );
					}
					return;
				}
				var d = json.data;
				wrap.classList.add( 'is-voted' );
				wrap.querySelector( '.pc-r-big' ).textContent = fmt( d.average );
				wrap.querySelector( '.pc-r-stars' ).innerHTML = stars( d.average, 22 );
				wrap.querySelector( '.pc-r-total' ).textContent = d.count + ' ' + plural( d.count, 'оценка', 'оценки', 'оценок' );
				wrap.querySelectorAll( '.pc-r-bar' ).forEach( function( bar ) {
					var c = d.criteria[ bar.getAttribute( 'data-criterion' ) ];
					if ( c && c.count > 0 ) {
						bar.querySelector( 'b' ).textContent = fmt( c.average );
						bar.querySelector( 'i' ).style.width = ( c.average / 5 * 100 ) + '%';
					}
				} );
				if ( form ) {
					form.innerHTML = '<p class="pc-rate-thanks">Спасибо, ваша оценка учтена.</p>';
				}
			} )
			.catch( function() {
				button.disabled = false;
			} );
	} );
} )();
