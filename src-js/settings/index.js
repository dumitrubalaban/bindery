/**
 * Bindery settings app.
 *
 * A small React admin screen (rendered into #bindery-settings-root) that reads and
 * writes the plugin's settings over the bindery/v1 REST API. Tabs: Editable
 * Content, Editing Experience, Permissions, History & Data.
 */
import { createElement as el, createRoot, useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	ColorPicker,
	FormTokenField,
	Notice,
	Panel,
	PanelBody,
	RangeControl,
	Spinner,
	TabPanel,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const cfg = window.binderySettings || {};
apiFetch.use( apiFetch.createNonceMiddleware( cfg.nonce ) );

/** Immutably set a dot-path on a nested object. */
function setIn( obj, path, value ) {
	const keys = path.split( '.' );
	const next = Array.isArray( obj ) ? obj.slice() : { ...obj };
	let cur = next;
	for ( let i = 0; i < keys.length - 1; i++ ) {
		cur[ keys[ i ] ] = { ...cur[ keys[ i ] ] };
		cur = cur[ keys[ i ] ];
	}
	cur[ keys[ keys.length - 1 ] ] = value;
	return next;
}

function getIn( obj, path ) {
	return path.split( '.' ).reduce( ( o, k ) => ( o == null ? o : o[ k ] ), obj );
}

/** A unique, human-readable token string for a { value, label, email } user choice. */
function userToken( u ) {
	return u.email ? `${ u.label } <${ u.email }>` : `${ u.label } (#${ u.value })`;
}

/**
 * Search-as-you-type multi-select for individual WP users, built on the same
 * token-field the block editor uses for tags/categories so it looks and
 * behaves natively. Debounced search hits `/users/search`; tokens the user
 * types but never selects from a real search result are dropped on change
 * rather than kept as free text, since only real user ids are meaningful here.
 */
function UserPicker( { selected, selectedDetails, onChange } ) {
	const [ known, setKnown ] = useState( {} );
	const [ suggestions, setSuggestions ] = useState( [] );
	const [ searching, setSearching ] = useState( false );
	const timer = useRef( null );

	// Seed (and keep in sync) the id->details map from whatever the server has
	// already resolved for us, so already-selected users render immediately
	// without waiting on a search.
	useEffect( () => {
		if ( ! selectedDetails || 0 === selectedDetails.length ) {
			return;
		}
		setKnown( ( prev ) => {
			const next = { ...prev };
			selectedDetails.forEach( ( u ) => {
				next[ userToken( u ) ] = u;
			} );
			return next;
		} );
		// selectedDetails is a fresh array from the server on each /settings
		// response; comparing its content (not identity) avoids re-seeding on
		// every unrelated re-render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ JSON.stringify( selectedDetails || [] ) ] );

	const knownList = Object.values( known );
	const tokens = ( selected || [] )
		.map( ( id ) => knownList.find( ( u ) => u.value === id ) )
		.filter( Boolean )
		.map( userToken );

	const search = ( term ) => {
		if ( timer.current ) {
			window.clearTimeout( timer.current );
		}
		timer.current = window.setTimeout( () => {
			setSearching( true );
			apiFetch( { url: cfg.rest + '/users/search?q=' + encodeURIComponent( term ) } )
				.then( ( results ) => {
					setKnown( ( prev ) => {
						const next = { ...prev };
						results.forEach( ( u ) => {
							next[ userToken( u ) ] = u;
						} );
						return next;
					} );
					setSuggestions( results.map( userToken ) );
				} )
				.catch( () => setSuggestions( [] ) )
				.finally( () => setSearching( false ) );
		}, 250 );
	};

	const handleChange = ( newTokens ) => {
		const ids = [];
		newTokens.forEach( ( t ) => {
			const match = known[ t ];
			// Ignore free text that never resolved to a real, selected user —
			// there is no meaningful id to store for it.
			if ( match && ! ids.includes( match.value ) ) {
				ids.push( match.value );
			}
		} );
		onChange( ids );
	};

	// Pre-fetch a browsable default list so opening the field shows suggestions
	// immediately, before the person has typed anything.
	useEffect( () => {
		search( '' );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return el( 'div', {}, [
		el( FormTokenField, {
			key: 'picker',
			label: __( 'Search by name or email…', 'bindery' ),
			value: tokens,
			suggestions,
			onInputChange: search,
			onChange: handleChange,
			__experimentalExpandOnFocus: true,
			__experimentalShowHowTo: false,
			__next40pxDefaultSize: true,
		} ),
		searching && el( 'p', { key: 'busy', style: { color: '#777', fontSize: '12px', margin: '4px 0 0' } },
			__( 'Searching…', 'bindery' ) ),
		0 === tokens.length && ! searching && el( 'p', { key: 'empty', style: { color: '#777', fontSize: '12px', margin: '4px 0 0' } },
			__( 'No individually-permitted people yet — only the roles above can edit.', 'bindery' ) ),
	] );
}

function App() {
	const [ settings, setSettings ] = useState( null );
	const [ choices, setChoices ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { url: cfg.rest + '/settings' } ).then( ( res ) => {
			setSettings( res.settings );
			setChoices( res.choices );
		} );
	}, [] );

	if ( ! settings || ! choices ) {
		return el( 'div', { style: { padding: '40px' } }, el( Spinner ) );
	}

	const update = ( path, value ) => setSettings( ( s ) => setIn( s, path, value ) );

	const toggleInArray = ( path, value, on ) => {
		const list = getIn( settings, path ) || [];
		update( path, on ? [ ...new Set( [ ...list, value ] ) ] : list.filter( ( v ) => v !== value ) );
	};

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { url: cfg.rest + '/settings', method: 'POST', data: settings } )
			.then( ( res ) => {
				setSettings( res.settings );
				setNotice(
					res.self_added
						? {
								status: 'warning',
								text: __(
									'Settings saved. You were kept on the individually-permitted list below so you don’t lose editing access — your role or account wasn’t otherwise covered.',
									'bindery'
								),
						  }
						: { status: 'success', text: __( 'Settings saved.', 'bindery' ) }
				);
			} )
			.catch( () => setNotice( { status: 'error', text: __( 'Could not save settings.', 'bindery' ) } ) )
			.finally( () => setSaving( false ) );
	};

	const tabs = [
		{ name: 'content', title: __( 'Editable Content', 'bindery' ) },
		{ name: 'experience', title: __( 'Editing Experience', 'bindery' ) },
		{ name: 'permissions', title: __( 'Permissions', 'bindery' ) },
		{ name: 'data', title: __( 'History & Data', 'bindery' ) },
	];

	const renderTab = ( tab ) => {
		if ( 'content' === tab.name ) {
			return contentTab();
		}
		if ( 'experience' === tab.name ) {
			return experienceTab();
		}
		if ( 'permissions' === tab.name ) {
			return permissionsTab();
		}
		return dataTab();
	};

	function contentTab() {
		const autoOn = !! getIn( settings, 'auto.enabled' );
		return el( Panel, {}, [
			el( PanelBody, { key: 'a', title: __( 'Automatic content editing', 'bindery' ), initialOpen: true }, [
				el( ToggleControl, {
					key: 't',
					label: __( 'Let clients edit existing page text in place', 'bindery' ),
					help: __( 'Headings, paragraphs and other text become editable on the live page — no blocks or code needed.', 'bindery' ),
					checked: autoOn,
					onChange: ( v ) => update( 'auto.enabled', v ),
				} ),
				el( 'p', { key: 'l', style: { fontWeight: 600, margin: '16px 0 4px' } }, __( 'Which elements are editable', 'bindery' ) ),
				...choices.tags.map( ( t ) =>
					el( CheckboxControl, {
						key: t.value,
						label: t.label,
						checked: ( getIn( settings, 'auto.tags' ) || [] ).includes( t.value ),
						disabled: ! autoOn,
						onChange: ( on ) => toggleInArray( 'auto.tags', t.value, on ),
					} )
				),
				el( 'p', { key: 'note', style: { color: '#777', fontSize: '12px', marginTop: '8px' } },
					__( 'Images, links and buttons are made editable with Bindery blocks instead.', 'bindery' ) ),
			] ),
			el( PanelBody, { key: 'b', title: __( 'Where it applies', 'bindery' ), initialOpen: false },
				choices.post_types.map( ( pt ) =>
					el( CheckboxControl, {
						key: pt.value,
						label: pt.label,
						checked: ( getIn( settings, 'auto.post_types' ) || [] ).includes( pt.value ),
						onChange: ( on ) => toggleInArray( 'auto.post_types', pt.value, on ),
					} )
				)
			),
		] );
	}

	function experienceTab() {
		return el( Panel, {}, el( PanelBody, { title: __( 'On-page overlay', 'bindery' ), initialOpen: true }, [
			el( ToggleControl, {
				key: 'show',
				label: __( 'Show the floating “Edit page” button', 'bindery' ),
				checked: !! getIn( settings, 'overlay.show_toggle' ),
				onChange: ( v ) => update( 'overlay.show_toggle', v ),
			} ),
			el( ToggleControl, {
				key: 'auto',
				label: __( 'Enter edit mode automatically for editors', 'bindery' ),
				checked: !! getIn( settings, 'overlay.auto_enter' ),
				onChange: ( v ) => update( 'overlay.auto_enter', v ),
			} ),
			el( ToggleControl, {
				key: 'strict',
				label: __( 'Strict mode — only edit regions the developer marked', 'bindery' ),
				help: __( 'Leaves block content to the block editor; the overlay touches only hand-coded regions.', 'bindery' ),
				checked: !! getIn( settings, 'overlay.strict' ),
				onChange: ( v ) => update( 'overlay.strict', v ),
			} ),
			el( 'p', { key: 'cl', style: { fontWeight: 600, margin: '16px 0 4px' } }, __( 'Accent colour', 'bindery' ) ),
			el( ColorPicker, {
				key: 'cp',
				color: getIn( settings, 'overlay.accent' ),
				enableAlpha: false,
				onChange: ( c ) => update( 'overlay.accent', c ),
			} ),
		] ) );
	}

	function permissionsTab() {
		return el( Panel, {}, [
			el( PanelBody, { key: 'roles', title: __( 'Who can edit — by role', 'bindery' ), initialOpen: true }, [
				el( 'p', { key: 'h', style: { color: '#555', marginTop: 0 } },
					__( 'Everyone with one of these roles can edit content.', 'bindery' ) ),
				...choices.roles.map( ( r ) =>
					el( CheckboxControl, {
						key: r.value,
						label: r.label,
						checked: ( getIn( settings, 'permissions.roles' ) || [] ).includes( r.value ),
						onChange: ( on ) => toggleInArray( 'permissions.roles', r.value, on ),
					} )
				),
			] ),
			el( PanelBody, { key: 'users', title: __( 'Who can edit — specific people', 'bindery' ), initialOpen: true }, [
				el( 'p', { key: 'h', style: { color: '#555', marginTop: 0 } },
					__( 'Grant editing to individual accounts, independent of role. Use this when only some Administrators — not all of them — should be able to edit, or to let a specific low-privilege account (e.g. a client with no Editor access) edit content without changing their role.', 'bindery' ) ),
				el( UserPicker, {
					key: 'picker',
					selected: getIn( settings, 'permissions.users' ) || [],
					selectedDetails: choices.selected_users || [],
					onChange: ( ids ) => update( 'permissions.users', ids ),
				} ),
			] ),
		] );
	}

	function dataTab() {
		return el( Panel, {}, [
			el( PanelBody, { key: 'h', title: __( 'History', 'bindery' ), initialOpen: true }, [
				el( ToggleControl, {
					key: 'on',
					label: __( 'Keep a revision history of every edit', 'bindery' ),
					checked: !! getIn( settings, 'history.enabled' ),
					onChange: ( v ) => update( 'history.enabled', v ),
				} ),
				el( RangeControl, {
					key: 'cap',
					label: __( 'Versions kept per field', 'bindery' ),
					min: 1,
					max: 100,
					value: getIn( settings, 'history.cap' ),
					onChange: ( v ) => update( 'history.cap', v ),
				} ),
			] ),
			el( PanelBody, { key: 'm', title: __( 'Migrate content', 'bindery' ), initialOpen: false }, [
				el( 'p', { key: 'd', style: { color: '#555' } },
					__( 'Move all edited values between sites (e.g. staging → production).', 'bindery' ) ),
				el( Button, {
					key: 'exp',
					variant: 'secondary',
					onClick: exportValues,
				}, __( 'Export values (JSON)', 'bindery' ) ),
				el( 'div', { key: 'imp', style: { marginTop: '12px' } },
					el( 'input', { type: 'file', accept: 'application/json', onChange: importValues } ) ),
			] ),
			el( PanelBody, { key: 'u', title: __( 'Uninstall', 'bindery' ), initialOpen: false },
				el( ToggleControl, {
					label: __( 'Delete all Bindery data when the plugin is deleted', 'bindery' ),
					help: __( 'Off by default, so your edits survive a reinstall.', 'bindery' ),
					checked: !! getIn( settings, 'data.delete_on_uninstall' ),
					onChange: ( v ) => update( 'data.delete_on_uninstall', v ),
				} )
			),
		] );
	}

	function exportValues() {
		apiFetch( { url: cfg.rest + '/values/export' } ).then( ( res ) => {
			const blob = new window.Blob( [ JSON.stringify( res, null, 2 ) ], { type: 'application/json' } );
			const a = document.createElement( 'a' );
			a.href = window.URL.createObjectURL( blob );
			a.download = 'bindery-values.json';
			a.click();
		} );
	}

	function importValues( e ) {
		const file = e.target.files && e.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new window.FileReader();
		reader.onload = () => {
			let payload;
			try {
				payload = JSON.parse( reader.result );
			} catch ( err ) {
				setNotice( { status: 'error', text: __( 'That file is not valid JSON.', 'bindery' ) } );
				return;
			}
			apiFetch( { url: cfg.rest + '/values/import', method: 'POST', data: payload } )
				.then( ( res ) => setNotice( { status: 'success', text: `${ __( 'Imported', 'bindery' ) } ${ res.imported } ${ __( 'values.', 'bindery' ) }` } ) )
				.catch( () => setNotice( { status: 'error', text: __( 'Import failed.', 'bindery' ) } ) );
		};
		reader.readAsText( file );
	}

	return el( 'div', { style: { maxWidth: '820px' } }, [
		el( 'div', { key: 'head', style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', margin: '10px 0 16px' } }, [
			el( 'h1', { key: 't', style: { margin: 0 } }, 'Bindery' ),
			el( Button, { key: 's', variant: 'primary', isBusy: saving, onClick: save }, __( 'Save settings', 'bindery' ) ),
		] ),
		notice && el( Notice, { key: 'n', status: notice.status, isDismissible: true, onRemove: () => setNotice( null ) }, notice.text ),
		el( ToggleControl, {
			key: 'master',
			label: __( 'Enable Bindery editing on this site', 'bindery' ),
			checked: !! settings.enabled,
			onChange: ( v ) => update( 'enabled', v ),
			__nextHasNoMarginBottom: true,
		} ),
		el( TabPanel, { key: 'tabs', tabs, style: { marginTop: '12px' } }, renderTab ),
	] );
}

const root = document.getElementById( 'bindery-settings-root' );
if ( root ) {
	createRoot( root ).render( el( App ) );
}
