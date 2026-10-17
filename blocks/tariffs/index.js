( function( blocks, element, blockEditor ) {
	'use strict';
	var el = element.createElement;

	blocks.registerBlockType( 'partner-catalog/tariffs', {
		edit: function() {
			var blockProps = blockEditor.useBlockProps();
			return el( 'p', blockProps, el( 'em', null, '💳 Тарифы сервиса' ) );
		},
		save: function() {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
