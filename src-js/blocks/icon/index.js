/**
 * Inline editor for bindery/icon.
 *
 * Pick an icon from the curated set (shared icons.json, also read by render.php).
 * The chosen key is saved to Bindery's per-locale store via window.bindery.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl, RangeControl, ColorPicker } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import icons from '../../../blocks/icon/icons.json';

function svg( path, size, color ) {
	return el(
		'svg',
		{ width: size, height: size, viewBox: '0 0 24 24', fill: color || 'currentColor', 'aria-hidden': true },
		el( 'path', { d: path } )
	);
}

registerBlockType( 'bindery/icon', {
	edit( props ) {
		const a = props.attributes;
		const key = a.fieldKey;
		const store = window.bindery;
		const blockProps = useBlockProps( { className: 'bindery-icon-block' } );

		const [ value, setValue ] = useState( '' );

		useEffect( function () {
			if ( ! store || ! key ) {
				return undefined;
			}
			const apply = () => {
				const v = store.getValue( key );
				setValue( v == null ? '' : String( v ) );
			};
			const unsub = store.subscribe( apply );
			store.ensureLoaded( store.getLocale() ).then( apply );
			apply();
			return unsub;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ key ] );

		function pick( name ) {
			setValue( name );
			if ( store && key ) {
				store.setValue( key, name );
			}
		}

		const inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Bindery icon', 'bindery' ), initialOpen: true },
				el( TextControl, {
					label: __( 'Field key', 'bindery' ),
					value: a.fieldKey,
					onChange: ( v ) => props.setAttributes( { fieldKey: v } ),
				} ),
				el( RangeControl, {
					label: __( 'Size', 'bindery' ),
					value: a.size || 48,
					min: 16,
					max: 120,
					onChange: ( v ) => props.setAttributes( { size: v } ),
				} ),
				el( 'p', { style: { margin: '12px 0 4px', fontSize: '11px', textTransform: 'uppercase', opacity: 0.7 } }, __( 'Color', 'bindery' ) ),
				el( ColorPicker, {
					color: a.color || '#d8b75d',
					onChange: ( v ) => props.setAttributes( { color: v } ),
					enableAlpha: false,
				} )
			)
		);

		if ( ! key ) {
			return el( 'div', blockProps, inspector, el( 'p', { style: { opacity: 0.6 } }, __( 'Set a field key in the block settings →', 'bindery' ) ) );
		}

		const grid = el(
			'div',
			{ contentEditable: false, style: { display: 'flex', flexWrap: 'wrap', gap: '8px', justifyContent: 'center', padding: '8px' } },
			Object.keys( icons ).map( ( name ) =>
				el(
					'button',
					{
						key: name,
						type: 'button',
						title: name,
						onClick: () => pick( name ),
						style: {
							width: '44px',
							height: '44px',
							border: name === value ? '2px solid ' + ( a.color || '#d8b75d' ) : '1px solid #3a3f4d',
							borderRadius: '10px',
							background: 'transparent',
							color: a.color || '#d8b75d',
							cursor: 'pointer',
							display: 'inline-flex',
							alignItems: 'center',
							justifyContent: 'center',
						},
					},
					svg( icons[ name ], 22, 'currentColor' )
				)
			)
		);

		const preview = value && icons[ value ]
			? el( 'div', { contentEditable: false, style: { textAlign: 'center', color: a.color || '#d8b75d', marginBottom: '8px' } }, svg( icons[ value ], a.size || 48, 'currentColor' ) )
			: el( 'p', { style: { textAlign: 'center', opacity: 0.6 } }, __( 'Pick an icon below', 'bindery' ) );

		return el( 'div', blockProps, inspector, preview, grid );
	},
	save() {
		return null;
	},
} );
