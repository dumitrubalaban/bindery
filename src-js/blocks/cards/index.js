/**
 * Inline editor for bindery/cards (built with @wordpress/scripts).
 *
 * A Bindery repeater: inline-editable card grid with add / remove / reorder; the
 * row array saves to the per-locale store via window.bindery.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl, RangeControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function decodeEntities( s ) {
	const t = document.createElement( 'textarea' );
	t.innerHTML = String( s == null ? '' : s );
	return t.value;
}

function subKeys( subfields ) {
	return subfields && 'object' === typeof subfields ? Object.keys( subfields ) : [ 'title', 'body' ];
}

function humanize( s ) {
	s = String( s || '' );
	return s.charAt( 0 ).toUpperCase() + s.slice( 1 ).replace( /[-_]/g, ' ' );
}

function labelFor( a, sub ) {
	return ( a.labels && a.labels[ sub ] ) || humanize( sub );
}

registerBlockType( 'bindery/cards', {
	edit( props ) {
		const a = props.attributes;
		const key = a.fieldKey;
		const subfields = a.subfields || { title: 'h4', body: 'p' };
		const store = window.bindery;

		const blockProps = useBlockProps( {
			className: 'bindery-cards',
			style: { '--bindery-cols': a.columns || 3 },
		} );

		const [ rows, setRows ] = useState( [] );

		useEffect( function () {
			if ( ! store || ! key ) {
				return undefined;
			}
			const apply = () => {
				const v = store.getValue( key );
				setRows( Array.isArray( v ) ? v : [] );
			};
			const unsub = store.subscribe( apply );
			store.ensureLoaded( store.getLocale() ).then( apply );
			apply();
			return unsub;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ key ] );

		function commit( next ) {
			setRows( next );
			if ( store && key ) {
				store.setValue( key, next );
			}
		}
		function setCell( i, sub, val ) {
			commit( rows.map( ( r, idx ) => ( idx !== i ? r : Object.assign( {}, r, { [ sub ]: val } ) ) ) );
		}
		function addRow() {
			const blank = {};
			subKeys( subfields ).forEach( ( s ) => ( blank[ s ] = '' ) );
			commit( rows.concat( [ blank ] ) );
		}
		function removeRow( i ) {
			commit( rows.filter( ( _, idx ) => idx !== i ) );
		}
		function move( i, dir ) {
			const j = i + dir;
			if ( j < 0 || j >= rows.length ) {
				return;
			}
			const n = rows.slice();
			const tmp = n[ i ];
			n[ i ] = n[ j ];
			n[ j ] = tmp;
			commit( n );
		}

		const inspectorEl = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Bindery cards', 'bindery' ), initialOpen: true },
				el( TextControl, {
					label: __( 'Field key', 'bindery' ),
					value: a.fieldKey,
					onChange: ( v ) => props.setAttributes( { fieldKey: v } ),
				} ),
				el( RangeControl, {
					label: __( 'Columns', 'bindery' ),
					value: a.columns || 3,
					min: 1,
					max: 4,
					onChange: ( v ) => props.setAttributes( { columns: v } ),
				} )
			)
		);

		if ( ! key ) {
			return el( 'div', blockProps, inspectorEl, el( 'p', { style: { opacity: 0.6 } }, __( 'Set a field key in the block settings →', 'bindery' ) ) );
		}

		const cards = rows.map( function ( row, i ) {
			const cells = subKeys( subfields ).map( ( sub ) =>
				el( RichText, {
					key: sub,
					tagName: subfields[ sub ] || 'p',
					className: 'bindery-card__' + sub,
					value: row[ sub ] == null ? '' : String( row[ sub ] ),
					allowedFormats: [],
					onChange: ( v ) => setCell( i, sub, v ),
					placeholder: labelFor( a, sub ),
				} )
			);
			const controls = el(
				'div',
				{ contentEditable: false, style: { display: 'flex', gap: '4px', marginTop: '12px' } },
				el( Button, { icon: 'arrow-up-alt2', label: __( 'Move up', 'bindery' ), onClick: () => move( i, -1 ), disabled: 0 === i, size: 'small' } ),
				el( Button, { icon: 'arrow-down-alt2', label: __( 'Move down', 'bindery' ), onClick: () => move( i, 1 ), disabled: i === rows.length - 1, size: 'small' } ),
				el( Button, { icon: 'trash', label: __( 'Remove', 'bindery' ), onClick: () => removeRow( i ), isDestructive: true, size: 'small' } )
			);
			return el( 'div', { key: i, className: 'bindery-card' }, cells.concat( [ controls ] ) );
		} );

		const addBtn = el(
			'div',
			{ contentEditable: false, style: { gridColumn: '1 / -1', textAlign: 'center', marginTop: '8px' } },
			el( Button, { variant: 'secondary', onClick: addRow }, __( '+ Add card', 'bindery' ) )
		);

		return el( 'div', blockProps, inspectorEl, cards.concat( [ addBtn ] ) );
	},
	save() {
		return null;
	},
} );
