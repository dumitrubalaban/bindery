/**
 * Inline editor for bindery/button.
 *
 * Inline-editable label (RichText anchor) + a link URL (inspector). Label saves
 * under the field key, URL under `{key}__url` — both per-locale via window.bindery.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'bindery/button', {
	edit( props ) {
		const a = props.attributes;
		const key = a.fieldKey;
		const store = window.bindery;
		const blockProps = useBlockProps( { className: 'bindery-button-block' } );

		const [ label, setLabel ] = useState( '' );
		const [ url, setUrl ] = useState( '' );

		useEffect( function () {
			if ( ! store || ! key ) {
				return undefined;
			}
			const apply = () => {
				const l = store.getValue( key );
				setLabel( l == null ? '' : String( l ) );
				const u = store.getValue( key + '__url' );
				setUrl( u == null ? '' : String( u ) );
			};
			const unsub = store.subscribe( apply );
			store.ensureLoaded( store.getLocale() ).then( apply );
			apply();
			return unsub;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ key ] );

		function onLabel( v ) {
			setLabel( v );
			if ( store && key ) {
				store.setValue( key, v );
			}
		}
		function onUrl( v ) {
			setUrl( v );
			if ( store && key ) {
				store.setValue( key + '__url', v );
			}
		}

		const inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Bindery button', 'bindery' ), initialOpen: true },
				el( TextControl, {
					label: __( 'Field key', 'bindery' ),
					value: a.fieldKey,
					onChange: ( v ) => props.setAttributes( { fieldKey: v } ),
				} ),
				el( TextControl, {
					label: __( 'Link URL', 'bindery' ),
					value: url,
					type: 'url',
					placeholder: 'https://…',
					onChange: onUrl,
				} )
			)
		);

		if ( ! key ) {
			return el( 'div', blockProps, inspector, el( 'p', { style: { opacity: 0.6 } }, __( 'Set a field key in the block settings →', 'bindery' ) ) );
		}

		return el(
			'div',
			blockProps,
			inspector,
			el( RichText, {
				tagName: 'a',
				className: 'bindery-button-link',
				value: label,
				allowedFormats: [],
				onChange: onLabel,
				placeholder: __( 'Button label', 'bindery' ),
			} )
		);
	},
	save() {
		return null;
	},
} );
