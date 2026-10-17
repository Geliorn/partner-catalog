/**
 * «Где попробовать»: re-picks which 3 of the server-rendered pool are shown,
 * weighted by priority (Efraimidis–Spirakis: key = u^(1/w), take the largest
 * keys) — higher priority shows more often but never takes every slot. Runs
 * in the browser so a full-page cache doesn't freeze one pick for everyone.
 */
( function() {
	'use strict';

	var SHOW = 3;

	document.querySelectorAll( '.pc-rec' ).forEach( function( box ) {
		var rows = Array.prototype.slice.call( box.querySelectorAll( '.pc-rec-row' ) );
		if ( rows.length <= SHOW ) {
			return;
		}
		var keyed = rows.map( function( row ) {
			var w = Math.max( 1, Number( row.getAttribute( 'data-weight' ) ) || 1 );
			return { row: row, key: Math.pow( Math.random(), 1 / w ) };
		} ).sort( function( a, b ) {
			return b.key - a.key;
		} );
		var list = box.querySelector( '.pc-rec-rows' );
		keyed.forEach( function( item, i ) {
			item.row.hidden = i >= SHOW;
			list.appendChild( item.row );
		} );
	} );
} )();
