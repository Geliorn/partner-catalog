( function( blocks, element, blockEditor ) {
	'use strict';
	var el = element.createElement;

	blocks.registerBlockType( 'partner-catalog/verify', {
		edit: function() {
			var blockProps = blockEditor.useBlockProps();
			return el( 'p', blockProps, el( 'em', null, '✅ Проверено редакцией · Сообщить о неточности' ) );
		},
		save: function() {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
