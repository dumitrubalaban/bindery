/**
 * Bindery editor runtime (built with @wordpress/scripts).
 *
 * Provides window.bindery — a shared per-locale value store backed by the REST
 * API that all Bindery blocks read/write — and a "Bindery language" document
 * panel (the only global control: which language you're editing).
 */

import { createElement as el, Fragment, useState, useEffect } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import { select, dispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

const cfg = window.binderyEditor || { locales: {}, current: '', default: '' };

// ---- shared per-locale value store ----
const store = ( function () {
	let listeners = [];
	let locale = cfg.current || cfg.default || '';
	const cache = {}; // locale -> { key: { value, def } }
	const loaded = {}; // locale -> promise
	const pending = {}; // locale -> { key: value }
	let saveTimer = null;
	let status = ''; // '', 'saving', 'saved', 'error'

	function postId() {
		try {
			return select( 'core/editor' ).getCurrentPostId();
		} catch ( e ) {
			return 0;
		}
	}

	function emit() {
		listeners.forEach( function ( fn ) {
			try {
				fn();
			} catch ( e ) {}
		} );
	}

	function ensureLoaded( loc ) {
		if ( loaded[ loc ] ) {
			return loaded[ loc ];
		}
		loaded[ loc ] = apiFetch( {
			path: '/bindery/v1/values?post=' + postId() + '&locale=' + encodeURIComponent( loc ),
		} )
			.then( function ( res ) {
				const map = {};
				const fields = ( res && res.fields ) || {};
				Object.keys( fields ).forEach( function ( k ) {
					map[ k ] = { value: fields[ k ].value, def: fields[ k ].default };
				} );
				cache[ loc ] = map;
				emit();
				return map;
			} )
			.catch( function () {
				cache[ loc ] = cache[ loc ] || {};
				emit();
				return cache[ loc ];
			} );
		return loaded[ loc ];
	}

	function flush( retry ) {
		saveTimer = null;
		const pid = postId();
		Object.keys( pending ).forEach( function ( loc ) {
			const values = pending[ loc ];
			delete pending[ loc ];
			if ( ! values || ! Object.keys( values ).length ) {
				return;
			}
			status = 'saving';
			emit();
			apiFetch( {
				path: '/bindery/v1/values',
				method: 'POST',
				data: { post: pid, locale: loc, values },
			} )
				.then( function ( res ) {
					const rejected = ( res && res.rejected ) || [];
					if ( ! rejected.length ) {
						status = 'saved';
						emit();
						return;
					}
					// Rejected keys belong to blocks not yet in the SAVED post
					// content. Save the page once, then retry just those keys.
					if ( retry ) {
						status = 'error';
						emit();
						return;
					}
					status = 'saving';
					emit();
					const requeue = {};
					rejected.forEach( function ( k ) {
						if ( k in values ) {
							requeue[ k ] = values[ k ];
						}
					} );
					dispatch( 'core/editor' )
						.savePost()
						.then( function () {
							if ( ! pending[ loc ] ) {
								pending[ loc ] = {};
							}
							Object.assign( pending[ loc ], requeue );
							flush( true );
						} )
						.catch( function () {
							status = 'error';
							emit();
						} );
				} )
				.catch( function () {
					status = 'error';
					emit();
				} );
		} );
	}

	return {
		getLocale: () => locale,
		setLocale( loc ) {
			locale = loc;
			ensureLoaded( loc ).then( emit );
			emit();
		},
		subscribe( fn ) {
			listeners.push( fn );
			return () => {
				listeners = listeners.filter( ( x ) => x !== fn );
			};
		},
		ensureLoaded,
		getValue( key ) {
			const c = cache[ locale ] || {};
			return c[ key ] ? c[ key ].value : null;
		},
		setValue( key, val ) {
			if ( ! cache[ locale ] ) {
				cache[ locale ] = {};
			}
			cache[ locale ][ key ] = Object.assign( {}, cache[ locale ][ key ], { value: val } );
			if ( ! pending[ locale ] ) {
				pending[ locale ] = {};
			}
			pending[ locale ][ key ] = val;
			status = 'saving';
			emit();
			if ( saveTimer ) {
				clearTimeout( saveTimer );
			}
			saveTimer = setTimeout( flush, 600 );
		},
		status: () => status,
	};
}() );

window.bindery = store;

// ---- language switch panel ----
function localeOptions() {
	const out = [];
	Object.keys( cfg.locales || {} ).forEach( function ( code ) {
		out.push( { label: cfg.locales[ code ] || code, value: code } );
	} );
	if ( ! out.length ) {
		out.push( { label: cfg.current || __( 'Default', 'bindery' ), value: cfg.current || '' } );
	}
	return out;
}

function LangPanel() {
	const [ locale, setLocale ] = useState( store.getLocale() );
	const [ status, setStatus ] = useState( store.status() );

	useEffect( function () {
		const unsub = store.subscribe( () => setStatus( store.status() ) );
		store.ensureLoaded( store.getLocale() );
		return unsub;
	}, [] );

	function change( loc ) {
		setLocale( loc );
		store.setLocale( loc );
	}

	let note = __( 'Click any Bindery block on the page and type. Each language saves separately.', 'bindery' );
	if ( 'saving' === status ) {
		note = __( 'Saving…', 'bindery' );
	} else if ( 'saved' === status ) {
		note = __( 'All changes saved.', 'bindery' );
	} else if ( 'error' === status ) {
		note = __( 'Save failed — try saving the page first.', 'bindery' );
	}

	return el(
		Fragment,
		{},
		el( SelectControl, {
			label: __( 'Editing language', 'bindery' ),
			value: locale,
			options: localeOptions(),
			onChange: change,
		} ),
		el( 'p', { style: { margin: '8px 0 0', opacity: 0.7, fontSize: '12px' } }, note )
	);
}

if ( PluginDocumentSettingPanel && registerPlugin ) {
	registerPlugin( 'bindery-lang', {
		render() {
			return el(
				PluginDocumentSettingPanel,
				{ name: 'bindery-lang', title: __( 'Bindery language', 'bindery' ), className: 'bindery-lang-panel' },
				el( LangPanel, {} )
			);
		},
	} );
}
