/**
 * Inline editor for bindery/image.
 *
 * The client picks an image from the media library; the attachment id is saved
 * to Bindery's per-locale store via window.bindery, and render.php outputs it.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, MediaUpload, MediaUploadCheck, InspectorControls } from '@wordpress/block-editor';
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl, SelectControl, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const SIZES = [ 'thumbnail', 'medium', 'large', 'full' ].map( ( s ) => ( { label: s, value: s } ) );

registerBlockType( 'bindery/image', {
	edit( props ) {
		const a = props.attributes;
		const key = a.fieldKey;
		const store = window.bindery;
		const blockProps = useBlockProps( { className: 'bindery-image' } );

		const [ id, setId ] = useState( 0 );
		const [ previewUrl, setPreviewUrl ] = useState( '' );

		useEffect( function () {
			if ( ! store || ! key ) {
				return undefined;
			}
			const apply = () => {
				const v = store.getValue( key );
				setId( v ? parseInt( v, 10 ) || 0 : 0 );
				setPreviewUrl( '' );
			};
			const unsub = store.subscribe( apply );
			store.ensureLoaded( store.getLocale() ).then( apply );
			apply();
			return unsub;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ key ] );

		const media = useSelect( ( select ) => ( id ? select( 'core' ).getMedia( id ) : null ), [ id ] );
		const url = previewUrl || ( media ? media.source_url : '' );

		function onSelect( m ) {
			setId( m.id );
			setPreviewUrl( m.url || '' );
			if ( store && key ) {
				store.setValue( key, m.id );
			}
		}
		function clearImage() {
			setId( 0 );
			setPreviewUrl( '' );
			if ( store && key ) {
				store.setValue( key, 0 );
			}
		}

		const inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Bindery image', 'bindery' ), initialOpen: true },
				el( TextControl, {
					label: __( 'Field key', 'bindery' ),
					value: a.fieldKey,
					onChange: ( v ) => props.setAttributes( { fieldKey: v } ),
				} ),
				el( SelectControl, {
					label: __( 'Image size', 'bindery' ),
					value: a.size,
					options: SIZES,
					onChange: ( v ) => props.setAttributes( { size: v } ),
				} ),
				el( TextControl, {
					label: __( 'Alt text', 'bindery' ),
					value: a.alt,
					onChange: ( v ) => props.setAttributes( { alt: v } ),
				} )
			)
		);

		if ( ! key ) {
			return el( 'div', blockProps, inspector, el( 'p', { style: { opacity: 0.6 } }, __( 'Set a field key in the block settings →', 'bindery' ) ) );
		}

		const picker = el(
			MediaUploadCheck,
			{},
			el( MediaUpload, {
				onSelect,
				allowedTypes: [ 'image' ],
				value: id,
				render: ( { open } ) =>
					id && url
						? el(
							'div',
							{},
							el( 'img', { src: url, alt: a.alt || '', style: { display: 'block', maxWidth: '100%', height: 'auto', borderRadius: '12px' } } ),
							el(
								'div',
								{ contentEditable: false, style: { marginTop: '8px', display: 'flex', gap: '6px', justifyContent: 'center' } },
								el( Button, { variant: 'secondary', onClick: open }, __( 'Replace', 'bindery' ) ),
								el( Button, { variant: 'tertiary', isDestructive: true, onClick: clearImage }, __( 'Remove', 'bindery' ) )
							)
						)
						: el(
							'div',
							{ style: { textAlign: 'center', padding: '40px 20px', border: '1px dashed #3a3f4d', borderRadius: '12px' } },
							el( Button, { variant: 'primary', onClick: open }, id ? __( 'Loading…', 'bindery' ) : __( 'Select image', 'bindery' ) )
						),
			} )
		);

		return el( 'div', blockProps, inspector, picker );
	},
	save() {
		return null;
	},
} );
