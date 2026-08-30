/**
 * Inline editor for bindery/form.
 *
 * Previews the form; the title and submit label are inline-editable, the field
 * labels + success message are edited in the inspector. All strings save to the
 * Bindery per-locale store under {key}_title/_name/_email/_message/_submit/_success.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'bindery/form', {
	edit( props ) {
		const a = props.attributes;
		const key = a.fieldKey;
		const store = window.bindery;
		const blockProps = useBlockProps( { className: 'bindery-form' } );

		const [ title, setTitle ] = useState( '' );
		const [ submit, setSubmit ] = useState( '' );
		const [ labels, setLabels ] = useState( { _name: '', _email: '', _message: '', _success: '' } );

		useEffect( function () {
			if ( ! store || ! key ) {
				return undefined;
			}
			const g = ( s ) => {
				const x = store.getValue( key + s );
				return x == null ? '' : String( x );
			};
			const apply = () => {
				setTitle( g( '_title' ) );
				setSubmit( g( '_submit' ) );
				setLabels( { _name: g( '_name' ), _email: g( '_email' ), _message: g( '_message' ), _success: g( '_success' ) } );
			};
			const unsub = store.subscribe( apply );
			store.ensureLoaded( store.getLocale() ).then( apply );
			apply();
			return unsub;
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ key ] );

		function save( suffix, val ) {
			if ( store && key ) {
				store.setValue( key + suffix, val );
			}
		}
		function saveLabel( suffix, val ) {
			setLabels( ( p ) => Object.assign( {}, p, { [ suffix ]: val } ) );
			save( suffix, val );
		}

		const inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Bindery form', 'bindery' ), initialOpen: true },
				el( TextControl, { label: __( 'Field key', 'bindery' ), value: a.fieldKey, onChange: ( v ) => props.setAttributes( { fieldKey: v } ) } ),
				el( TextControl, { label: __( 'Name label', 'bindery' ), value: labels._name, placeholder: __( 'Name', 'bindery' ), onChange: ( v ) => saveLabel( '_name', v ) } ),
				el( TextControl, { label: __( 'Email label', 'bindery' ), value: labels._email, placeholder: __( 'Email', 'bindery' ), onChange: ( v ) => saveLabel( '_email', v ) } ),
				el( TextControl, { label: __( 'Message label', 'bindery' ), value: labels._message, placeholder: __( 'Message', 'bindery' ), onChange: ( v ) => saveLabel( '_message', v ) } ),
				el( TextControl, { label: __( 'Success message', 'bindery' ), value: labels._success, placeholder: __( 'Thanks!', 'bindery' ), onChange: ( v ) => saveLabel( '_success', v ) } )
			)
		);

		if ( ! key ) {
			return el( 'div', blockProps, inspector, el( 'p', { style: { opacity: 0.6 } }, __( 'Set a field key in the block settings →', 'bindery' ) ) );
		}

		function previewField( labelText, placeholder, type ) {
			return el(
				'label',
				{ className: 'bindery-form__field', contentEditable: false },
				el( 'span', {}, labelText || placeholder ),
				'textarea' === type
					? el( 'textarea', { rows: 3, disabled: true } )
					: el( 'input', { type: type || 'text', disabled: true } )
			);
		}

		return el(
			'div',
			blockProps,
			inspector,
			el( RichText, { tagName: 'h3', className: 'bindery-form__title', value: title, allowedFormats: [], onChange: ( v ) => { setTitle( v ); save( '_title', v ); }, placeholder: __( 'Get in touch', 'bindery' ) } ),
			previewField( labels._name, __( 'Name', 'bindery' ), 'text' ),
			previewField( labels._email, __( 'Email', 'bindery' ), 'email' ),
			previewField( labels._message, __( 'Message', 'bindery' ), 'textarea' ),
			el( RichText, { tagName: 'span', className: 'bindery-form__submit bindery-button-link', value: submit, allowedFormats: [], onChange: ( v ) => { setSubmit( v ); save( '_submit', v ); }, placeholder: __( 'Send', 'bindery' ) } )
		);
	},
	save() {
		return null;
	},
} );
