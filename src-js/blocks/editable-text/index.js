/**
 * Inline editor for bindery/editable-text (built with @wordpress/scripts).
 *
 * Edited like a native block (RichText); the value saves to Bindery's per-locale
 * store via window.bindery. `locked` makes it visible but read-only.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { createElement as el, Fragment, useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const TAGS = [ 'p', 'h1', 'h2', 'h3', 'h4', 'span', 'div' ].map( ( t ) => ( { label: t, value: t } ) );

function decodeEntities( s ) {
	const t = document.createElement( 'textarea' );
	t.innerHTML = String( s == null ? '' : s );
	return t.value;
}

function inspector( props ) {
	const a = props.attributes;
	return el(
		InspectorControls,
		{},
		el(
			PanelBody,
			{ title: __( 'Bindery field', 'bindery' ), initialOpen: true },
			el( TextControl, {
				label: __( 'Field key', 'bindery' ),
				help: __( 'The Bindery field this block edits.', 'bindery' ),
				value: a.fieldKey,
				onChange: ( v ) => props.setAttributes( { fieldKey: v } ),
			} ),
			el( TextControl, {
				label: __( 'Default text', 'bindery' ),
				help: __( 'Shown until a client overrides it.', 'bindery' ),
				value: a.placeholder,
				onChange: ( v ) => props.setAttributes( { placeholder: v } ),
			} ),
			el( SelectControl, {
				label: __( 'HTML tag', 'bindery' ),
				value: a.tag,
				options: TAGS,
				onChange: ( v ) => props.setAttributes( { tag: v } ),
			} ),
			el( ToggleControl, {
				label: __( 'Locked (display only)', 'bindery' ),
				help: __( 'Visible on the page, but clients cannot edit it.', 'bindery' ),
				checked: !! a.locked,
				onChange: ( v ) => props.setAttributes( { locked: v } ),
			} )
		)
	);
}

registerBlockType( 'bindery/editable-text', {
	edit( props ) {
		const a = props.attributes;
		const key = a.fieldKey;
		const store = window.bindery;
		const blockProps = useBlockProps();
		const Tag = a.tag || 'p';

		const [ value, setValue ] = useState( '' );
		const [ , setLocale ] = useState( store ? store.getLocale() : '' );

		useEffect( function () {
			if ( ! store || ! key ) {
				return undefined;
			}
			const apply = () => {
				setLocale( store.getLocale() );
				const v = store.getValue( key );
				setValue( null == v ? '' : String( v ) );
			};
			const unsub = store.subscribe( apply );
			store.ensureLoaded( store.getLocale() ).then( apply );
			apply();
			return unsub;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ key ] );

		function onChange( v ) {
			setValue( v );
			if ( store && key ) {
				store.setValue( key, v );
			}
		}

		if ( ! key ) {
			return el(
				Fragment,
				{},
				inspector( props ),
				el( Tag, blockProps, el( 'em', { style: { opacity: 0.6 } }, __( 'Set a field key in the block settings →', 'bindery' ) ) )
			);
		}

		if ( a.locked ) {
			const lockedText = ( null == value || '' === value ) ? decodeEntities( a.placeholder || '' ) : value;
			return el(
				Fragment,
				{},
				inspector( props ),
				el( Tag, blockProps, [
					lockedText,
					el( 'span', {
						key: 'lock',
						contentEditable: false,
						className: 'bindery-locked-badge',
						title: __( 'Locked — clients cannot edit this', 'bindery' ),
						style: { marginLeft: '8px', opacity: 0.45, fontSize: '0.7em', verticalAlign: 'middle' },
					}, '🔒' ),
				] )
			);
		}

		return el(
			Fragment,
			{},
			inspector( props ),
			el( RichText, Object.assign( {}, blockProps, {
				tagName: Tag,
				value,
				allowedFormats: [],
				onChange,
				placeholder: decodeEntities( a.placeholder || __( 'Type…', 'bindery' ) ),
			} ) )
		);
	},
	save() {
		return null;
	},
} );
