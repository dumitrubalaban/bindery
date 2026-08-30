/**
 * Inline editor for bindery/slider (built with @wordpress/scripts).
 *
 * A working carousel in the editor (prev/next/dots) with the visible slide
 * inline-editable; slides are a Bindery repeater saved per-locale via
 * window.bindery. Front-end autoplay comes from the block's view.js.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { createElement as el, useState, useEffect } from '@wordpress/element';
import { PanelBody, TextControl, ToggleControl, RangeControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function decodeEntities( s ) {
	const t = document.createElement( 'textarea' );
	t.innerHTML = String( s == null ? '' : s );
	return t.value;
}

function subKeys( subfields ) {
	return subfields && 'object' === typeof subfields ? Object.keys( subfields ) : [ 'title', 'text' ];
}

function humanize( s ) {
	s = String( s || '' );
	return s.charAt( 0 ).toUpperCase() + s.slice( 1 ).replace( /[-_]/g, ' ' );
}

function labelFor( a, sub ) {
	return ( a.labels && a.labels[ sub ] ) || humanize( sub );
}

registerBlockType( 'bindery/slider', {
	edit( props ) {
		const a = props.attributes;
		const key = a.fieldKey;
		const subfields = a.subfields || { title: 'h3', text: 'p' };
		const store = window.bindery;

		const blockProps = useBlockProps( { className: 'bindery-slider' } );

		const [ rows, setRows ] = useState( [] );
		const [ current, setCurrent ] = useState( 0 );

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

		const n = rows.length;
		const active = n > 0 ? Math.min( current, n - 1 ) : 0;

		function go( i ) {
			if ( n < 1 ) {
				return;
			}
			setCurrent( ( ( i % n ) + n ) % n );
		}
		function commit( next, focusIndex ) {
			setRows( next );
			if ( store && key ) {
				store.setValue( key, next );
			}
			if ( 'number' === typeof focusIndex ) {
				setCurrent( Math.max( 0, Math.min( focusIndex, next.length - 1 ) ) );
			}
		}
		function setCell( i, sub, val ) {
			commit( rows.map( ( r, idx ) => ( idx !== i ? r : Object.assign( {}, r, { [ sub ]: val } ) ) ) );
		}
		function addSlide() {
			const blank = {};
			subKeys( subfields ).forEach( ( s ) => ( blank[ s ] = '' ) );
			const next = rows.concat( [ blank ] );
			commit( next, next.length - 1 );
		}
		function removeSlide( i ) {
			const next = rows.filter( ( _, idx ) => idx !== i );
			commit( next, Math.max( 0, i - 1 ) );
		}
		function move( i, dir ) {
			const j = i + dir;
			if ( j < 0 || j >= rows.length ) {
				return;
			}
			const next = rows.slice();
			const tmp = next[ i ];
			next[ i ] = next[ j ];
			next[ j ] = tmp;
			commit( next, j );
		}

		const inspectorEl = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Bindery slider', 'bindery' ), initialOpen: true },
				el( TextControl, {
					label: __( 'Field key', 'bindery' ),
					value: a.fieldKey,
					onChange: ( v ) => props.setAttributes( { fieldKey: v } ),
				} ),
				el( ToggleControl, {
					label: __( 'Autoplay (front end)', 'bindery' ),
					checked: !! a.autoplay,
					onChange: ( v ) => props.setAttributes( { autoplay: v } ),
				} ),
				el( RangeControl, {
					label: __( 'Interval (ms)', 'bindery' ),
					value: a.interval || 5000,
					min: 1500,
					max: 12000,
					step: 500,
					onChange: ( v ) => props.setAttributes( { interval: v } ),
				} )
			)
		);

		if ( ! key ) {
			return el( 'div', blockProps, inspectorEl, el( 'p', { style: { opacity: 0.6 } }, __( 'Set a field key in the block settings →', 'bindery' ) ) );
		}

		const slides = rows.map( function ( row, i ) {
			const cells = subKeys( subfields ).map( ( sub ) =>
				el( RichText, {
					key: sub,
					tagName: subfields[ sub ] || 'p',
					className: 'bindery-slide__' + sub,
					value: row[ sub ] == null ? '' : String( row[ sub ] ),
					allowedFormats: [],
					onChange: ( v ) => setCell( i, sub, v ),
					placeholder: labelFor( a, sub ),
				} )
			);
			const controls = el(
				'div',
				{ contentEditable: false, style: { display: 'flex', gap: '4px', justifyContent: 'center', marginTop: '16px' } },
				el( Button, { icon: 'arrow-left-alt2', label: __( 'Move slide left', 'bindery' ), onClick: () => move( i, -1 ), disabled: 0 === i, size: 'small' } ),
				el( Button, { icon: 'trash', label: __( 'Remove slide', 'bindery' ), onClick: () => removeSlide( i ), isDestructive: true, size: 'small' } ),
				el( Button, { icon: 'arrow-right-alt2', label: __( 'Move slide right', 'bindery' ), onClick: () => move( i, 1 ), disabled: i === rows.length - 1, size: 'small' } )
			);
			return el( 'div', { key: i, className: 'bindery-slide' }, cells.concat( [ controls ] ) );
		} );

		const track = el(
			'div',
			{ className: 'bindery-slider__viewport' },
			el( 'div', { className: 'bindery-slider__track', style: { transform: 'translateX(-' + active * 100 + '%)' } }, slides )
		);

		const nav = n > 1
			? [
				el( 'button', { key: 'prev', type: 'button', contentEditable: false, className: 'bindery-slider__nav bindery-slider__prev', 'aria-label': __( 'Previous slide', 'bindery' ), onClick: () => go( active - 1 ) }, '‹' ),
				el( 'button', { key: 'next', type: 'button', contentEditable: false, className: 'bindery-slider__nav bindery-slider__next', 'aria-label': __( 'Next slide', 'bindery' ), onClick: () => go( active + 1 ) }, '›' ),
			]
			: [];

		const dots = el(
			'div',
			{ className: 'bindery-slider__dots', contentEditable: false },
			rows.map( ( _, i ) =>
				el( 'button', {
					key: i,
					type: 'button',
					className: 'bindery-slider__dot' + ( i === active ? ' is-active' : '' ),
					onClick: () => go( i ),
					'aria-label': __( 'Go to slide', 'bindery' ) + ' ' + ( i + 1 ),
				} )
			)
		);

		const addBtn = el(
			'div',
			{ contentEditable: false, style: { textAlign: 'center', marginTop: '14px' } },
			el( Button, { variant: 'secondary', onClick: addSlide }, __( '+ Add slide', 'bindery' ) )
		);

		return el( 'div', blockProps, [ inspectorEl, track ].concat( nav ).concat( [ dots, addBtn ] ) );
	},
	save() {
		return null;
	},
} );
