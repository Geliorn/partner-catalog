( function( blocks, element, blockEditor ) {
	'use strict';
	var el = element.createElement;

	blocks.registerBlockType( 'partner-catalog/related-posts', {
		edit: function() {
			var blockProps = blockEditor.useBlockProps();
			return el( 'div', blockProps, el( 'em', null, 'Похожие сервисы (по типу/специализации, вычисляется на странице записи)' ) );
		},
		save: function() {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
