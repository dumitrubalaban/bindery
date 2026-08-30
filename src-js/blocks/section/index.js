/**
 * Inline editor for bindery/section.
 *
 * A full-width section: editable background image (saved to Bindery's per-locale
 * store via window.bindery) + a darkening overlay + InnerBlocks content. The
 * inner blocks are saved to post content; render.php wraps them with the
 * resolved background.
 */

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	MediaUpload,
	MediaUploadCheck,
	InspectorControls,
} from '@wordpress/block-editor';
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl, RangeControl, Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
	[ 'bindery/editable-text', { fieldKey: '', tag: 'h2', placeholder: 'Section title' } ],
];

registerBlockType( 'bindery/section', {
	edit( props ) {
		const a = props.attributes;
		const key = a.fieldKey;
		const store = window.bindery;

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
		function clearBg() {
			setId( 0 );
			setPreviewUrl( '' );
			if ( store && key ) {
				store.setValue( key, 0 );
			}
		}

		const style = { minHeight: a.minHeight || '420px', backgroundSize: 'cover', backgroundPosition: 'center' };
		if ( url ) {
			style.backgroundImage = 'url(' + url + ')';
		}
		const blockProps = useBlockProps( { className: 'bindery-section', style } );
		const innerBlocksProps = useInnerBlocksProps( { className: 'bindery-section__inner' }, { template: TEMPLATE } );

		const inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Bindery section', 'bindery' ), initialOpen: true },
				el( TextControl, {
					label: __( 'Background field key', 'bindery' ),
					value: a.fieldKey,
					onChange: ( v ) => props.setAttributes( { fieldKey: v } ),
				} ),
				el( RangeControl, {
					label: __( 'Overlay darkness (%)', 'bindery' ),
					value: a.overlay,
					min: 0,
					max: 100,
					onChange: ( v ) => props.setAttributes( { overlay: v } ),
				} ),
				el( TextControl, {
					label: __( 'Min height', 'bindery' ),
					value: a.minHeight,
					onChange: ( v ) => props.setAttributes( { minHeight: v } ),
				} ),
				key &&
					el(
						MediaUploadCheck,
						{},
						el( MediaUpload, {
							onSelect,
							allowedTypes: [ 'image' ],
							value: id,
							render: ( { open } ) =>
								el(
									'div',
									{ style: { display: 'flex', gap: '6px', marginTop: '8px' } },
									el( Button, { variant: 'secondary', onClick: open }, id ? __( 'Replace background', 'bindery' ) : __( 'Set background', 'bindery' ) ),
									id && el( Button, { variant: 'tertiary', isDestructive: true, onClick: clearBg }, __( 'Remove', 'bindery' ) )
								),
						} )
					)
			)
		);

		return el(
			'div',
			blockProps,
			inspector,
			el( 'div', { className: 'bindery-section__overlay', style: { opacity: ( a.overlay || 0 ) / 100 } } ),
			el( 'div', innerBlocksProps )
		);
	},
	save() {
		return el( InnerBlocks.Content );
	},
} );
