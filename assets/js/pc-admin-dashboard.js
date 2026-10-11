/**
 * Minimal hand-rolled line chart — deliberately no charting library (no CDN
 * dependency in wp-admin, keeps the plugin portable/dependency-free across
 * the network's sites). Draws two series (views, clicks) from the JSON
 * <script type="application/json"> block the PHP side prints next to the
 * canvas — see PC_Admin::render_analytics_page().
 */
( function() {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function() {
		var canvas = document.getElementById( 'pc-analytics-chart' );
		var dataEl = document.getElementById( 'pc-analytics-series' );
		if ( ! canvas || ! dataEl ) {
			return;
		}

		var series;
		try {
			series = JSON.parse( dataEl.textContent || '[]' );
		} catch ( e ) {
			return;
		}
		if ( ! series.length ) {
			return;
		}

		var ctx = canvas.getContext( '2d' );
		var w = canvas.width;
		var h = canvas.height;
		var padding = { top: 20, right: 20, bottom: 30, left: 40 };
		var plotW = w - padding.left - padding.right;
		var plotH = h - padding.top - padding.bottom;

		var maxVal = 1;
		series.forEach( function( d ) {
			maxVal = Math.max( maxVal, d.views, d.clicks );
		} );

		function x( i ) {
			return padding.left + ( series.length > 1 ? ( i / ( series.length - 1 ) ) * plotW : plotW / 2 );
		}
		function y( v ) {
			return padding.top + plotH - ( v / maxVal ) * plotH;
		}

		ctx.clearRect( 0, 0, w, h );

		// grid
		ctx.strokeStyle = '#e2e4e7';
		ctx.lineWidth = 1;
		for ( var g = 0; g <= 4; g++ ) {
			var gy = padding.top + ( plotH / 4 ) * g;
			ctx.beginPath();
			ctx.moveTo( padding.left, gy );
			ctx.lineTo( w - padding.right, gy );
			ctx.stroke();
		}

		function drawLine( key, color ) {
			ctx.strokeStyle = color;
			ctx.lineWidth = 2;
			ctx.beginPath();
			series.forEach( function( d, i ) {
				var px = x( i );
				var py = y( d[ key ] );
				if ( 0 === i ) {
					ctx.moveTo( px, py );
				} else {
					ctx.lineTo( px, py );
				}
			} );
			ctx.stroke();
		}

		drawLine( 'views', '#2f5d4f' );
		drawLine( 'clicks', '#c0392b' );

		// x-axis labels: first, middle, last date only — keeps it readable at any width.
		ctx.fillStyle = '#646970';
		ctx.font = '11px sans-serif';
		ctx.textAlign = 'center';
		[ 0, Math.floor( ( series.length - 1 ) / 2 ), series.length - 1 ].forEach( function( i ) {
			ctx.fillText( series[ i ].date, x( i ), h - 8 );
		} );

		// legend
		ctx.textAlign = 'left';
		ctx.fillStyle = '#2f5d4f';
		ctx.fillText( '─ Переходы', padding.left, 14 );
		ctx.fillStyle = '#c0392b';
		ctx.fillText( '─ Клики', padding.left + 100, 14 );
	} );
} )();
