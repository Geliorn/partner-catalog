/**
 * Archive toolbar: sort select submits on change; grid/list switch toggles a
 * class on the card grid and remembers the choice per browser; the
 * «Фильтры» button opens the sidebar filters as a drawer on phones.
 */
( function() {
	'use strict';

	var KEY = 'pc-archive-view';

	function grid() {
		return document.querySelector( '.pc-tile-grid' );
	}

	function setView( view ) {
		var g = grid();
		if ( g ) {
			g.classList.toggle( 'is-list', 'list' === view );
		}
		document.querySelectorAll( '[data-pc-view]' ).forEach( function( b ) {
			b.setAttribute( 'aria-pressed', b.getAttribute( 'data-pc-view' ) === view ? 'true' : 'false' );
		} );
	}

	try {
		var saved = window.localStorage.getItem( KEY );
		if ( 'list' === saved ) {
			setView( 'list' );
		}
	} catch ( e ) {}

	document.addEventListener( 'click', function( event ) {
		var viewBtn = event.target.closest( '[data-pc-view]' );
		if ( viewBtn ) {
			var view = viewBtn.getAttribute( 'data-pc-view' );
			setView( view );
			try {
				window.localStorage.setItem( KEY, view );
			} catch ( e ) {}
			return;
		}
		var open = event.target.closest( '.pc-filters-open' );
		if ( open ) {
			var panel = document.querySelector( '.pc-filters' );
			if ( panel ) {
				panel.classList.add( 'is-open' );
				document.body.classList.add( 'pc-drawer-open' );
			}
			return;
		}
		var close = event.target.closest( '.pc-filters-close' );
		if ( close || event.target.classList.contains( 'pc-drawer-open' ) ) {
			var p = document.querySelector( '.pc-filters.is-open' );
			if ( p ) {
				p.classList.remove( 'is-open' );
				document.body.classList.remove( 'pc-drawer-open' );
			}
		}
	} );

	document.addEventListener( 'change', function( event ) {
		var select = event.target.closest( '.pc-sort-form select' );
		if ( select ) {
			select.form.submit();
		}
	} );
} )();
