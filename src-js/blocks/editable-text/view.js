/**
 * Frontend script for bindery/editable-text. Loaded only when the block is
 * present — a hook point for richer self-contained behaviour.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.wp-block-bindery-editable-text' ).forEach( function ( node ) {
		node.setAttribute( 'data-bindery-ready', '1' );
	} );
} );
