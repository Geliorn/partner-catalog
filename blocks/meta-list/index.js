( function( blocks, element, blockEditor ) {
	'use strict';
	var el = element.createElement;

	blocks.registerBlockType( 'partner-catalog/meta-list', {
		edit: function( props ) {
			var blockProps = blockEditor.useBlockProps();
			var key = props.attributes.metaKey || '_pc_features';
			return el(
				'div',
				blockProps,
				el( 'em', null, 'Список из meta-поля: ' + key + ' (заполняется на странице записи, здесь — только плейсхолдер)' )
			);
		},
		// Dynamic block — actual markup comes from render.php on the server.
		save: function() {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
