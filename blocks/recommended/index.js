( function( blocks, element, blockEditor ) {
	'use strict';
	var el = element.createElement;

	blocks.registerBlockType( 'partner-catalog/recommended', {
		edit: function() {
			var blockProps = blockEditor.useBlockProps();
			return el( 'p', blockProps, el( 'em', null, '💡 Где попробовать (сервисы из каталога)' ) );
		},
		save: function() {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
