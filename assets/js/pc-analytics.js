/**
 * Two client-side Yandex.Metrika goals — see docs/partner-catalog-plan.md
 * section 7.4. Both no-op silently if Metrika isn't installed on the site
 * (typeof ym check) or no counter ID is configured in plugin settings.
 * Server-side click counting/UTM/redirect happen separately in PC_Links —
 * this file only fires the Metrika goals, it does not do any counting itself.
 */
( function() {
	'use strict';

	var config = window.pcAnalytics || {};
	var counterId = config.metrikaCounterId;

	function fireGoal( name, params ) {
		if ( ! counterId || typeof window.ym !== 'function' ) {
			return;
		}
		window.ym( counterId, 'reachGoal', name, params || {} );
	}

	if ( config.isSingle ) {
		fireGoal( 'partner_profile_view', {
			contractor_id: config.contractorId,
			contractor_type: config.contractorType
		} );
	}

	document.addEventListener( 'click', function( event ) {
		var link = event.target.closest( '.pc-outbound-link' );
		if ( ! link ) {
			return;
		}
		fireGoal( 'partner_click', { href: link.getAttribute( 'href' ) } );
	} );
} )();
