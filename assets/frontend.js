/**
 * Bindery front-end edit overlay.
 *
 * Loaded only for users who can edit. Adds a floating "Edit page" toggle; in
 * edit mode, every text region marked with data-bindery-field becomes editable
 * inline on the real page and saves to the Bindery store (current locale) on
 * blur via the REST API. A language switcher reloads the page in that locale.
 */
( function () {
	'use strict';

	var cfg = window.binderyFront;
	if ( ! cfg || ! cfg.canEdit ) {
		return;
	}

	// Types the overlay can edit inline today. Other declared types (image, icon …)
	// are edited in the block editor. An empty/absent type means plain text.
	var INLINE_TYPES = { '': 1, text: 1, richtext: 1, url: 1 };

	// Of those, only `richtext` gets the formatting toolbar + HTML persistence —
	// everything else stays plain text (both in the DOM and in what gets saved),
	// which matches what the field type's own server-side sanitiser accepts:
	// RichTextField runs wp_kses_post(), the rest strip all markup. Giving a
	// plain-text field a bold button would be a lie — the formatting would be
	// silently discarded on save.
	var RICH_TYPES = { richtext: 1 };

	function regions() {
		return Array.prototype.slice.call( document.querySelectorAll( '[data-bindery-field]' ) );
	}

	// The set the overlay actually turns into editable fields. Only inline-capable
	// types qualify; in strict mode (bindery/strict_overlay) only hand-coded
	// bindery_attrs() regions — which carry data-bindery-type — are touched, so
	// editable-text blocks are left to the block editor.
	function editableRegions() {
		return regions().filter( function ( el ) {
			if ( cfg.strict && ! el.hasAttribute( 'data-bindery-type' ) ) {
				return false;
			}
			return INLINE_TYPES[ el.getAttribute( 'data-bindery-type' ) || '' ];
		} );
	}

	// Image regions can't be contenteditable — they open the native media
	// library instead. Always hand-coded (bindery_attrs() always sets
	// data-bindery-type), so strict mode never needs to filter these out.
	function imageRegions() {
		return regions().filter( function ( el ) {
			return 'image' === el.getAttribute( 'data-bindery-type' );
		} );
	}

	// Repeaters are a different shape entirely — a container the theme marked
	// with bindery_repeater_attrs(), holding rows the theme rendered itself.
	// Nothing about their markup is standardised (a slider, a testimonial
	// grid, a feature list — any of it), so the overlay only ever adds
	// controls around what's already there rather than owning the row markup.
	function repeaterRegions() {
		return Array.prototype.slice.call( document.querySelectorAll( '[data-bindery-repeater]' ) );
	}

	if ( ! editableRegions().length && ! imageRegions().length && ! repeaterRegions().length ) {
		return;
	}

	// Apply the configurable accent colour (settings page) to the overlay chrome.
	if ( cfg.accent ) {
		document.documentElement.style.setProperty( '--bindery-fe-accent', cfg.accent );
	}

	var toggle = document.createElement( 'button' );
	toggle.type = 'button';
	toggle.className = 'bindery-fe-toggle';
	toggle.textContent = '✎';
	toggle.setAttribute( 'aria-pressed', 'false' );
	toggle.setAttribute( 'aria-label', 'Toggle Bindery page editing' );
	// Hide the floating button when the setting disables it (editing can still be
	// entered via ?bindery-edit=1 or the auto-enter setting).
	if ( false !== cfg.showToggle ) {
		document.body.appendChild( toggle );
	}

	var bar = document.createElement( 'div' );
	bar.className = 'bindery-fe-bar';
	bar.setAttribute( 'role', 'toolbar' );
	bar.setAttribute( 'aria-label', 'Bindery editing toolbar' );
	var label = document.createElement( 'span' );
	label.textContent = 'Bindery';
	var sel = document.createElement( 'select' );
	var selId = 'bindery-fe-locale';
	sel.id = selId;
	sel.setAttribute( 'aria-label', 'Editing language' );
	var locales = cfg.locales || {};
	var codes = Object.keys( locales );
	if ( codes.length <= 1 ) {
		sel.style.display = 'none';
	}
	codes.forEach( function ( code ) {
		var o = document.createElement( 'option' );
		o.value = code;
		o.textContent = locales[ code ] || code;
		if ( code === cfg.locale ) {
			o.selected = true;
		}
		sel.appendChild( o );
	} );
	// Status is a polite live region so screen readers announce save results
	// without stealing focus from the field being edited.
	var status = document.createElement( 'span' );
	status.style.opacity = '0.7';
	status.setAttribute( 'role', 'status' );
	status.setAttribute( 'aria-live', 'polite' );
	bar.appendChild( label );
	bar.appendChild( sel );
	bar.appendChild( status );
	document.body.appendChild( bar );

	sel.addEventListener( 'change', function () {
		var url = new URL( window.location.href );
		url.searchParams.set( 'lang', sel.value );
		url.searchParams.set( 'bindery-edit', '1' );
		window.location.href = url.toString();
	} );

	var editing = false;

	function isRich( el ) {
		return !! RICH_TYPES[ el.getAttribute( 'data-bindery-type' ) || '' ];
	}

	// richtext regions persist markup (innerHTML, kept in step with what
	// wp_kses_post() will accept server-side); every other type stays plain
	// text — reading textContent also has the useful side effect of silently
	// discarding any formatting a browser's default contenteditable behaviour
	// might have applied, so what you see matches what gets saved.
	function readValue( el ) {
		return isRich( el ) ? el.innerHTML : el.textContent;
	}

	// --- Formatting toolbar (richtext regions only) ------------------------
	// Every command here was checked against wp_kses_post() first (see the
	// PR notes) — underline/strike/alignment(inline style)/blockquote/link
	// with target+rel all survive server-side sanitisation, so nothing the
	// toolbar can produce gets silently discarded on save.
	var toolbar = null;
	var toolbarTarget = null;
	var toolbarButtons = [];

	// Small inline icon set (no external icon font/library — this asset is
	// deliberately dependency-free). Text commands (B/I/U/S) are styled
	// letters, which read instantly and match how most editors show them;
	// everything else is a minimal stroke-only SVG.
	var ICONS = {
		undo: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6H4v6"/><path d="M4 8.5A6.5 6.5 0 1 1 6.3 15.7"/></svg>',
		redo: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 6h2v6"/><path d="M16 8.5a6.5 6.5 0 1 0-2.3 7.2"/></svg>',
		alignLeft: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 5h14M3 9h9M3 13h14M3 17h9"/></svg>',
		alignCenter: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 5h14M6 9h8M3 13h14M6 17h8"/></svg>',
		alignRight: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 5h14M8 9h9M3 13h14M8 17h9"/></svg>',
		blockquote: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H7v2.2c-.9.15-1.5.7-1.5 1.55V9h2v4H4V6.5Zm7 0A2.5 2.5 0 0 1 13.5 4h.5v2.2c-.9.15-1.5.7-1.5 1.55V9h2v4h-4V6.5Z"/></svg>',
		link: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M8.5 11.5 11.5 8.5"/><path d="M7 13 4.8 10.8a3 3 0 0 1 0-4.2l1-1a3 3 0 0 1 4.2 0L11.5 7"/><path d="M13 7l2.2 2.2a3 3 0 0 1 0 4.2l-1 1a3 3 0 0 1-4.2 0L8.5 13"/></svg>',
	};

	// Grouped like a real editor toolbar: history | inline styles | alignment
	// | block/link — with visual separators between groups (see CSS).
	var TOOLBAR_DEFS = [
		{ cmd: 'undo', icon: ICONS.undo, title: 'Undo' },
		{ cmd: 'redo', icon: ICONS.redo, title: 'Redo' },
		{ group: true },
		{ cmd: 'bold', label: 'B', title: 'Bold', cls: 'is-bold' },
		{ cmd: 'italic', label: 'I', title: 'Italic', cls: 'is-italic' },
		{ cmd: 'underline', label: 'U', title: 'Underline', cls: 'is-underline' },
		{ cmd: 'strikeThrough', label: 'S', title: 'Strikethrough', cls: 'is-strike' },
		{ group: true },
		{ cmd: 'justifyLeft', icon: ICONS.alignLeft, title: 'Align left' },
		{ cmd: 'justifyCenter', icon: ICONS.alignCenter, title: 'Align center' },
		{ cmd: 'justifyRight', icon: ICONS.alignRight, title: 'Align right' },
		{ group: true },
		{ cmd: 'blockquote', icon: ICONS.blockquote, title: 'Quote' },
		{ cmd: 'insertUnorderedList', label: '•', title: 'Bulleted list' },
		{ cmd: 'link', icon: ICONS.link, title: 'Link' },
	];

	// queryCommandState()/queryCommandValue() are unreliable (or outright
	// unsupported) for 'link'/'unlink' across engines, unlike the simple
	// toggles (bold/italic/…). Walking the actual DOM from the selection is
	// the one method that works consistently for both detecting "is this
	// selection inside an <a>/<blockquote>" and deciding what to toggle.
	function closestAncestorTag( tagName ) {
		var sel = window.getSelection();
		var node = sel && sel.anchorNode ? sel.anchorNode : null;
		while ( node && node !== toolbarTarget ) {
			if ( node.nodeType === 1 && tagName === node.tagName ) {
				return node;
			}
			node = node.parentNode;
		}
		return null;
	}

	// Bold defaults to a semantic <b>, whose visual weight the browser resolves
	// via the CSS "bolder" keyword RELATIVE to the surrounding font-weight —
	// e.g. on a theme with a very light base weight (300, as in Twenty
	// Twenty-Five), "bolder" only steps up one bucket to 400, so bold text can
	// render almost unchanged even though the correct tag was applied, on the
	// saved/published page as much as while editing. styleWithCSS makes the
	// Bold command set an explicit `font-weight: bold` inline style instead,
	// which resolves to 700 regardless of the theme's base typography.
	//
	// Italic/underline/strikethrough do NOT have this problem — font-style and
	// text-decoration are absolute values, not relative keywords — so they
	// stay as plain <i>/<u>/<s>. That matters here for a second reason: Chrome's
	// styleWithCSS mode writes underline/strikethrough using the *longhand*
	// `text-decoration-line`, which wp_kses_post() strips outright (only the
	// shorthand `text-decoration` survives) — enabling styleWithCSS for those
	// two would silently lose the formatting on save.
	var EXPLICIT_STYLE_CMDS = { bold: 1 };

	function runCommand( def ) {
		if ( ! toolbarTarget ) {
			return;
		}
		// Set explicitly on every call (not just once) so it can't leak into
		// unrelated commands (list/blockquote/alignment/link) run afterwards.
		document.execCommand( 'styleWithCSS', false, !! EXPLICIT_STYLE_CMDS[ def.cmd ] );
		if ( 'link' === def.cmd ) {
			if ( closestAncestorTag( 'A' ) ) {
				document.execCommand( 'unlink' );
			} else {
				var url = window.prompt( 'Link URL' );
				if ( url ) {
					document.execCommand( 'createLink', false, url );
				}
			}
		} else if ( 'blockquote' === def.cmd ) {
			document.execCommand( 'formatBlock', false, closestAncestorTag( 'BLOCKQUOTE' ) ? 'P' : 'BLOCKQUOTE' );
		} else {
			document.execCommand( def.cmd );
		}
		updateActiveStates();
	}

	// Reflects the current selection's formatting on the buttons (e.g. Bold
	// lights up while the caret is inside bold text) — the difference between
	// a toolbar and four unlabelled buttons that might already be "on".
	function updateActiveStates() {
		toolbarButtons.forEach( function ( entry ) {
			var state = false;
			try {
				if ( 'blockquote' === entry.def.cmd ) {
					state = !! closestAncestorTag( 'BLOCKQUOTE' );
				} else if ( 'link' === entry.def.cmd ) {
					state = !! closestAncestorTag( 'A' );
				} else if ( 'undo' !== entry.def.cmd && 'redo' !== entry.def.cmd ) {
					state = document.queryCommandState( entry.def.cmd );
				}
			} catch ( e ) {}
			entry.btn.classList.toggle( 'is-active', state );
		} );
	}

	function ensureToolbar() {
		if ( toolbar ) {
			return toolbar;
		}
		toolbar = document.createElement( 'div' );
		toolbar.className = 'bindery-fe-format-bar';
		toolbar.setAttribute( 'role', 'toolbar' );
		toolbar.setAttribute( 'aria-label', 'Text formatting' );
		toolbar.hidden = true;

		TOOLBAR_DEFS.forEach( function ( def ) {
			if ( def.group ) {
				var sep = document.createElement( 'span' );
				sep.className = 'bindery-fe-format-sep';
				sep.setAttribute( 'aria-hidden', 'true' );
				toolbar.appendChild( sep );
				return;
			}
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'bindery-fe-format-btn' + ( def.cls ? ' ' + def.cls : '' );
			btn.innerHTML = def.icon || def.label; // icons/labels are from the fixed ICONS/TOOLBAR_DEFS list above, never user input.
			btn.title = def.title;
			btn.setAttribute( 'aria-label', def.title );
			// mousedown (not click) + preventDefault keeps the contenteditable
			// region focused and its text selection intact, which execCommand
			// needs to act on — a click would blur the field and lose it first.
			btn.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault();
				runCommand( def );
			} );
			toolbar.appendChild( btn );
			toolbarButtons.push( { def: def, btn: btn } );
		} );

		document.addEventListener( 'selectionchange', function () {
			if ( toolbarTarget ) {
				updateActiveStates();
			}
		} );

		document.body.appendChild( toolbar );
		return toolbar;
	}

	function showToolbarFor( el ) {
		toolbarTarget = el;
		var bar = ensureToolbar();
		bar.hidden = false;
		updateActiveStates();
		var rect = el.getBoundingClientRect();
		var top = window.scrollY + rect.top - bar.offsetHeight - 8;
		// Flip below the element when there isn't room above it (e.g. it's the
		// first thing on the page).
		if ( top < window.scrollY + 4 ) {
			top = window.scrollY + rect.bottom + 8;
		}
		bar.style.top = top + 'px';
		bar.style.left = Math.max( 8, window.scrollX + rect.left ) + 'px';
	}

	function hideToolbar() {
		toolbarTarget = null;
		if ( toolbar ) {
			toolbar.hidden = true;
		}
	}

	// Losing focus to the toolbar itself shouldn't hide it — only hide once
	// focus has actually left both the field and the toolbar.
	function onRichFocusOut( e ) {
		var next = e.relatedTarget;
		if ( next && toolbar && toolbar.contains( next ) ) {
			return;
		}
		hideToolbar();
	}

	// Pasted content almost always drags in foreign markup/inline styles (Word,
	// Google Docs, another site). Force plain text on paste everywhere — the
	// toolbar is how richtext regions get formatting back, deliberately, rather
	// than inheriting whatever the clipboard happened to contain.
	function onPaste( e ) {
		e.preventDefault();
		var text = ( e.clipboardData || window.clipboardData ).getData( 'text/plain' );
		document.execCommand( 'insertText', false, text );
	}

	// Shared save path for every field type — text regions call it on blur,
	// image regions call it once a media-library selection is made. The
	// backend is already fully generic (ImageField::sanitize() accepts an
	// attachment id same as any other type's sanitiser accepts its value), so
	// nothing here needs to know or care what kind of field it's saving.
	function persist( el, key, val, obj ) {
		status.textContent = 'Saving…';
		var values = {};
		values[ key ] = val;
		fetch( cfg.rest + '/values', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( { post: obj, locale: cfg.locale, values: values } ),
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				var ok = j && j.saved && Object.prototype.hasOwnProperty.call( j.saved, key );
				status.textContent = ok ? 'Saved' : 'Save failed';
				el.style.outlineColor = ok ? '#48a05a' : '#c84646';
				setTimeout( function () {
					el.style.outlineColor = '';
					if ( 'Saved' === status.textContent ) {
						status.textContent = '';
					}
				}, 1200 );
			} )
			.catch( function () { status.textContent = 'Network error'; } );
	}

	function onBlur( e ) {
		var el = e.target;
		var key = el.getAttribute( 'data-bindery-field' );
		var obj = parseInt( el.getAttribute( 'data-bindery-object' ), 10 ) || cfg.post;
		var val = readValue( el );
		if ( val === el.__binderyOrig ) {
			return;
		}
		el.__binderyOrig = val;
		persist( el, key, val, obj );
	}

	function onKey( e ) {
		// Plain text fields are single-line: Enter saves instead of inserting a
		// literal newline that sanitize_text_field would collapse anyway.
		// Richtext fields allow real paragraphs/list items, so Enter behaves
		// normally there (the browser's default contenteditable behaviour).
		if ( 'Enter' === e.key && 'TEXTAREA' !== e.target.tagName && ! isRich( e.target ) ) {
			e.preventDefault();
			e.target.blur();
		}
	}

	// One shared media frame, reused across clicks (a fresh wp.media() per
	// click works too, but re-parses the whole library view every time —
	// reusing the frame is how wp-admin itself does it). __binderyImageTarget
	// tracks which element the *current* open picker applies to, since the
	// frame's own 'select' listener is bound once, not per element.
	var mediaFrame = null;
	var imageTarget = null;

	function openMediaPicker( el ) {
		if ( ! window.wp || ! wp.media ) {
			status.textContent = 'Media library unavailable';
			return;
		}
		imageTarget = el;
		if ( ! mediaFrame ) {
			mediaFrame = wp.media( {
				title: 'Select or upload an image',
				button: { text: 'Use this image' },
				multiple: false,
			} );
			mediaFrame.on( 'select', function () {
				if ( ! imageTarget ) {
					return;
				}
				var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
				applyImage( imageTarget, attachment );
			} );
		}
		mediaFrame.open();
	}

	// Attachment id is what gets saved (ImageField::sanitize() stores it as
	// such and re-resolves the URL server-side on every future render); the
	// URL is only for painting the change into the page immediately, without
	// waiting on a save round-trip.
	function applyImage( el, attachment ) {
		var url = attachment.url;
		// The editable region is often a crop/hover-zoom wrapper around the
		// actual <img> (its outline needs to live on the non-clipped wrapper,
		// not on the image itself — see bindery_attrs() usage notes) — walk
		// down to the real <img> when the region isn't one itself, so both
		// markup shapes update the visible photo, not just a hidden bg layer.
		var innerImg = 'IMG' === el.tagName ? el : el.querySelector( 'img' );
		if ( innerImg ) {
			innerImg.src = url;
		} else {
			el.style.backgroundImage = "url('" + url + "')";
		}
		var key = el.getAttribute( 'data-bindery-field' );
		var obj = parseInt( el.getAttribute( 'data-bindery-object' ), 10 ) || cfg.post;
		persist( el, key, attachment.id, obj );
	}

	function onImageKey( e ) {
		if ( 'Enter' === e.key || ' ' === e.key ) {
			e.preventDefault();
			openMediaPicker( e.target );
		}
	}

	// --- Repeaters -----------------------------------------------------
	// A row's current values live on its own data-bindery-row-data attribute
	// (JSON, written by the theme's template loop) rather than in any JS
	// state — that way add/move/delete always start from exactly what the
	// server last rendered, never from a stale in-memory copy.
	function rowEls( container ) {
		return Array.prototype.slice.call( container.querySelectorAll( '[data-bindery-row-index]' ) )
			// Only this container's own rows — querySelectorAll would also
			// reach into a nested repeater's rows if a theme ever put one
			// inside another, which :scope isn't reliably supported enough
			// (older WebViews) to filter out on its own.
			.filter( function ( row ) { return row.closest( '[data-bindery-repeater]' ) === container; } );
	}

	function readRows( container ) {
		return rowEls( container ).map( function ( row ) {
			try {
				return JSON.parse( row.getAttribute( 'data-bindery-row-data' ) || '{}' );
			} catch ( e ) {
				return {};
			}
		} );
	}

	function schemaOf( container ) {
		try {
			return JSON.parse( container.getAttribute( 'data-bindery-schema' ) || '{}' );
		} catch ( e ) {
			return {};
		}
	}

	// Same save path as everything else (persist → POST /values) — the only
	// difference is what happens after a successful save. Text/image regions
	// patch themselves in place; a repeater reloads, because the row markup
	// belongs to the theme's template and can be arbitrarily complex (a
	// testimonial card, a slide, a pricing tier) — re-running that template
	// server-side is the only way to stay correct for every shape a theme
	// might build, rather than the overlay trying to clone theme markup in JS.
	function saveRows( container, rows ) {
		var key = container.getAttribute( 'data-bindery-repeater' );
		var obj = parseInt( container.getAttribute( 'data-bindery-object' ), 10 ) || 0;
		status.textContent = 'Saving…';
		var values = {};
		values[ key ] = rows;
		fetch( cfg.rest + '/values', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( { post: obj, locale: cfg.locale, values: values } ),
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				var ok = j && j.saved && Object.prototype.hasOwnProperty.call( j.saved, key );
				if ( ok ) {
					// Add/edit/delete reload to get the theme's real
					// server-rendered row markup — but a plain navigation
					// resets scroll to the top, which for a card you were
					// just looking at three screens down reads as "it threw
					// me somewhere else". Hand the position to the next load.
					try {
						sessionStorage.setItem( 'binderyScrollY', String( window.scrollY ) );
					} catch ( err ) {}
					var url = new URL( window.location.href );
					url.searchParams.set( 'bindery-edit', '1' );
					window.location.href = url.toString();
				} else {
					status.textContent = 'Save failed';
				}
			} )
			.catch( function () { status.textContent = 'Network error'; } );
	}

	function deleteRow( container, index ) {
		if ( ! window.confirm( 'Remove this item?' ) ) {
			return;
		}
		var rows = readRows( container );
		rows.splice( index, 1 );
		saveRows( container, rows );
	}

	function humanize( key ) {
		return key.replace( /_/g, ' ' ).replace( /^./, function ( c ) { return c.toUpperCase(); } );
	}

	// The add/edit form is built entirely from the schema declared in
	// bindery_repeater_attrs()'s `fields` arg — a generic {type, multiline}
	// map, not anything specific to one theme's repeater. New sub-field
	// types just need a case here, same as INLINE_TYPES/RICH_TYPES above.
	function openRowEditor( container, index ) {
		var schema = schemaOf( container );
		var rows = readRows( container );
		var existing = null === index ? {} : rows[ index ] || {};
		var values = {};
		Object.keys( schema ).forEach( function ( k ) { values[ k ] = existing[ k ]; } );

		var backdrop = document.createElement( 'div' );
		backdrop.className = 'bindery-fe-row-backdrop';
		var modal = document.createElement( 'div' );
		modal.className = 'bindery-fe-row-editor';
		var title = document.createElement( 'h3' );
		title.textContent = null === index ? 'Add item' : 'Edit item';
		modal.appendChild( title );

		Object.keys( schema ).forEach( function ( key ) {
			var spec = schema[ key ];
			var type = 'string' === typeof spec ? spec : ( spec && spec.type ) || 'text';
			var multiline = !! ( spec && spec.multiline );

			var field = document.createElement( 'label' );
			field.className = 'bindery-fe-row-field';
			var labelText = document.createElement( 'span' );
			labelText.textContent = humanize( key );
			field.appendChild( labelText );

			if ( 'image' === type ) {
				var preview = document.createElement( 'img' );
				preview.className = 'bindery-fe-row-image-preview';
				// The stored value is an attachment id, not a URL — a theme
				// that wants an existing image to preview correctly here
				// should include a `<key>_url` sibling alongside the raw id
				// in its row-data JSON (display-only, not part of the schema,
				// so it's never sent back on save).
				if ( 'string' === typeof values[ key + '_url' ] ) {
					preview.src = values[ key + '_url' ];
				}
				var pickBtn = document.createElement( 'button' );
				pickBtn.type = 'button';
				pickBtn.className = 'bindery-fe-row-image-btn';
				pickBtn.textContent = 'Choose image';
				pickBtn.addEventListener( 'click', function () {
					if ( ! window.wp || ! wp.media ) {
						return;
					}
					var frame = wp.media( { title: 'Select or upload an image', multiple: false } );
					frame.on( 'select', function () {
						var attachment = frame.state().get( 'selection' ).first().toJSON();
						values[ key ] = attachment.id;
						preview.src = attachment.url;
					} );
					frame.open();
				} );
				field.appendChild( preview );
				field.appendChild( pickBtn );
			} else {
				var input = document.createElement( multiline ? 'textarea' : 'input' );
				if ( ! multiline ) {
					input.type = 'text';
				}
				input.value = 'string' === typeof values[ key ] || 'number' === typeof values[ key ] ? values[ key ] : '';
				input.addEventListener( 'input', function () { values[ key ] = input.value; } );
				field.appendChild( input );
			}

			modal.appendChild( field );
		} );

		var actions = document.createElement( 'div' );
		actions.className = 'bindery-fe-row-actions';
		var cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.textContent = 'Cancel';
		cancel.className = 'bindery-fe-row-cancel';
		cancel.addEventListener( 'click', function () { backdrop.remove(); } );
		var save = document.createElement( 'button' );
		save.type = 'button';
		save.textContent = 'Save';
		save.className = 'bindery-fe-row-save';
		save.addEventListener( 'click', function () {
			var updated = readRows( container );
			if ( null === index ) {
				updated.push( values );
			} else {
				updated[ index ] = values;
			}
			backdrop.remove();
			saveRows( container, updated );
		} );
		actions.appendChild( cancel );
		actions.appendChild( save );
		modal.appendChild( actions );

		backdrop.appendChild( modal );
		backdrop.addEventListener( 'click', function ( e ) {
			if ( e.target === backdrop ) {
				backdrop.remove();
			}
		} );
		// Enter saves from any single-line field — setting up several fields
		// in a row via mouse-to-Save every time is exactly the friction this
		// removes. A <textarea> needs plain Enter to still insert a line
		// break, so it only saves on Cmd/Ctrl+Enter instead; Escape cancels,
		// the natural pairing.
		modal.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				backdrop.remove();
				return;
			}
			if ( 'Enter' !== e.key ) {
				return;
			}
			if ( 'TEXTAREA' === e.target.tagName && ! ( e.metaKey || e.ctrlKey ) ) {
				return;
			}
			e.preventDefault();
			save.click();
		} );
		document.body.appendChild( backdrop );
		var firstField = modal.querySelector( 'input, textarea' );
		if ( firstField ) {
			firstField.focus();
		}
	}

	// `markup` is only ever one of this file's own fixed ICON constants
	// below — never user input — so setting it via innerHTML is safe.
	function rowControlButton( label, title, fn, markup ) {
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'bindery-fe-row-btn';
		if ( markup ) {
			btn.innerHTML = markup;
		} else {
			btn.textContent = label;
		}
		btn.title = title;
		btn.setAttribute( 'aria-label', title );
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			fn();
		} );
		return btn;
	}

	// --- Repeater reordering: drag handle, not up/down buttons -------------
	// Up/down buttons are a dated pattern for anything beyond 3-4 items —
	// every product this is worth imitating (Notion, Trello, Airtable, even
	// the block editor's own List View) reorders by dragging a handle
	// instead. Native HTML5 drag-and-drop needs the *row* to be the
	// draggable element (so the whole card is the drag image, not a tiny
	// icon), which is why draggable is toggled on/off around the handle's
	// own mousedown/mouseup rather than left on permanently — a permanently
	// draggable row would swallow clicks on its own edit/delete buttons and
	// break text selection.
	var GRIP_ICON = '<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><circle cx="4" cy="3" r="1.3"/><circle cx="10" cy="3" r="1.3"/><circle cx="4" cy="7" r="1.3"/><circle cx="10" cy="7" r="1.3"/><circle cx="4" cy="11" r="1.3"/><circle cx="10" cy="11" r="1.3"/></svg>';
	var TRASH_ICON = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/><path d="M6 7l1 12.5A2 2 0 0 0 9 21h6a2 2 0 0 0 2-1.5L18 7"/><path d="M10 11v6M14 11v6"/></svg>';
	var dragState = null;
	var armedDragRow = null;

	document.addEventListener( 'mouseup', function () {
		if ( armedDragRow ) {
			armedDragRow.setAttribute( 'draggable', 'false' );
			armedDragRow = null;
		}
	} );

	function rowIndexOf( row ) {
		return parseInt( row.getAttribute( 'data-bindery-row-index' ), 10 );
	}

	function clearDropHighlights( container ) {
		rowEls( container ).forEach( function ( r ) { r.classList.remove( 'is-drop-target' ); } );
	}

	function makeDragHandle( row ) {
		var handle = document.createElement( 'button' );
		handle.type = 'button';
		handle.className = 'bindery-fe-row-btn bindery-fe-row-handle';
		// phpcs equivalent n/a (JS) — fixed, trusted markup, no user input.
		handle.innerHTML = GRIP_ICON;
		handle.title = 'Drag to reorder';
		handle.setAttribute( 'aria-label', 'Drag to reorder' );
		handle.addEventListener( 'mousedown', function () {
			row.setAttribute( 'draggable', 'true' );
			armedDragRow = row;
		} );
		return handle;
	}

	function onRowDragStart( container, row, e ) {
		dragState = { container: container, row: row };
		row.classList.add( 'is-dragging' );
		if ( e.dataTransfer ) {
			e.dataTransfer.effectAllowed = 'move';
			try { e.dataTransfer.setData( 'text/plain', '' ); } catch ( err ) {}
		}
	}

	function onRowDragEnd( container, row ) {
		row.classList.remove( 'is-dragging' );
		row.setAttribute( 'draggable', 'false' );
		clearDropHighlights( container );
		dragState = null;
	}

	function onRowDragOver( container, row, e ) {
		if ( ! dragState || dragState.container !== container ) {
			return;
		}
		e.preventDefault();
		if ( row !== dragState.row ) {
			clearDropHighlights( container );
			row.classList.add( 'is-drop-target' );
		}
	}

	// FLIP: capture every row's position before the DOM mutates, run the
	// mutation, then read where each row ended up and animate from the old
	// spot to the new one. Without this an instant DOM move is too subtle to
	// even register as "something happened" — the exact "too fast, didn't
	// realise it moved" feedback this replaced. Respects reduced-motion.
	function flip( container, mutate ) {
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var firstRects = new Map();
		rowEls( container ).forEach( function ( r ) { firstRects.set( r, r.getBoundingClientRect() ); } );

		mutate();

		if ( reduce ) {
			return;
		}
		rowEls( container ).forEach( function ( r ) {
			var first = firstRects.get( r );
			if ( ! first ) {
				return;
			}
			var last = r.getBoundingClientRect();
			var dx = first.left - last.left;
			var dy = first.top - last.top;
			if ( ! dx && ! dy ) {
				return;
			}
			r.style.transition = 'none';
			r.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
			// Forces the browser to commit the transform above before the
			// transition is turned back on — without this read, the two
			// style writes get batched together and there's nothing to
			// animate from.
			r.getBoundingClientRect();
			r.style.transition = 'transform 0.32s cubic-bezier(0.4, 0, 0.2, 1)';
			r.style.transform = '';
			// transitionend won't fire for a row whose measured delta rounds
			// to a sub-pixel no-op (e.g. one sitting outside the visible
			// scroll area during the drag) — a timeout fallback guarantees
			// the inline transition still gets cleared either way, so it
			// can't linger and affect that row's next unrelated transform
			// (its own hover lift, say) with an unwanted 0.32s delay.
			var cleared = false;
			var clear = function () {
				if ( cleared ) {
					return;
				}
				cleared = true;
				r.style.transition = '';
			};
			setTimeout( clear, 400 );
			r.addEventListener( 'transitionend', function handler() {
				clear();
				r.removeEventListener( 'transitionend', handler );
			} );
		} );
	}

	// Reordering moves the actual DOM node right there under the cursor and
	// saves quietly in the background — no page reload. That's only safe
	// because a move never changes what's rendered (same rows, new order);
	// add/edit/delete still reload, because the new/changed row's markup has
	// to come from the theme's own template, which only a real render can
	// produce correctly for an arbitrary card/slide/whatever shape.
	function onRowDrop( container, row, e ) {
		if ( ! dragState || dragState.container !== container ) {
			return;
		}
		e.preventDefault();
		var draggedRow = dragState.row;
		clearDropHighlights( container );
		dragState = null;
		if ( draggedRow === row ) {
			return;
		}
		flip( container, function () {
			var siblings = rowEls( container );
			var before = siblings.indexOf( draggedRow ) < siblings.indexOf( row );
			row.parentNode.insertBefore( draggedRow, before ? row.nextSibling : row );
		} );
		reindexRows( container );
		persist( container, container.getAttribute( 'data-bindery-repeater' ), readRows( container ), parseInt( container.getAttribute( 'data-bindery-object' ), 10 ) || 0 );
	}

	function reindexRows( container ) {
		rowEls( container ).forEach( function ( r, idx ) { r.setAttribute( 'data-bindery-row-index', idx ); } );
	}

	function enter() {
		editing = true;
		document.body.classList.add( 'bindery-editing' );
		toggle.textContent = '✓ Save';
		toggle.setAttribute( 'aria-pressed', 'true' );
		status.textContent = 'Editing on — click any highlighted text to edit';
		editableRegions().forEach( function ( el ) {
			el.classList.add( 'bindery-region-active' );
			if ( isRich( el ) ) {
				el.classList.add( 'bindery-region-rich' );
			}
			el.setAttribute( 'contenteditable', 'true' );
			// Expose each region as a labelled, role-appropriate edit target so
			// keyboard and screen-reader users can reach and identify it.
			el.setAttribute( 'role', 'textbox' );
			el.setAttribute( 'aria-label', 'Edit: ' + ( el.getAttribute( 'data-bindery-field' ) || 'field' ).replace( /_/g, ' ' ) );
			el.__binderyOrig = readValue( el );
			el.addEventListener( 'blur', onBlur );
			el.addEventListener( 'keydown', onKey );
			el.addEventListener( 'paste', onPaste );
			if ( isRich( el ) ) {
				// Named + stashed on the element so exit() can remove this exact
				// listener again (an inline closure here would leak a new one on
				// every enter(), stacking duplicates across edit on/off cycles).
				el.__binderyFocus = function () { showToolbarFor( el ); };
				el.addEventListener( 'focus', el.__binderyFocus );
				el.addEventListener( 'focusout', onRichFocusOut );
			}
		} );
		imageRegions().forEach( function ( el ) {
			el.classList.add( 'bindery-region-active', 'bindery-region-image' );
			el.setAttribute( 'role', 'button' );
			el.setAttribute( 'tabindex', '0' );
			el.setAttribute( 'aria-label', 'Replace image: ' + ( el.getAttribute( 'data-bindery-field' ) || 'field' ).replace( /_/g, ' ' ) );
			el.__binderyImageClick = function ( e ) {
				e.preventDefault();
				openMediaPicker( el );
			};
			el.addEventListener( 'click', el.__binderyImageClick );
			el.addEventListener( 'keydown', onImageKey );
		} );
		repeaterRegions().forEach( function ( container ) {
			container.classList.add( 'bindery-region-repeater-active' );
			rowEls( container ).forEach( function ( row ) {
				row.classList.add( 'bindery-region-repeater-row' );
				var controls = document.createElement( 'div' );
				controls.className = 'bindery-fe-row-controls';
				controls.appendChild( makeDragHandle( row ) );
				// Read the row's index live rather than closing over the loop's `i` —
				// drag-reorder moves rows without re-running enter(), so a captured
				// index would go stale the moment a card is dragged past this one.
				controls.appendChild( rowControlButton( '✎', 'Edit item', function () { openRowEditor( container, rowIndexOf( row ) ); } ) );
				controls.appendChild( rowControlButton( '', 'Delete item', function () { deleteRow( container, rowIndexOf( row ) ); }, TRASH_ICON ) );
				row.appendChild( controls );
				row.__binderyRowControls = controls;

				row.__binderyDragStart = function ( e ) { onRowDragStart( container, row, e ); };
				row.__binderyDragEnd = function () { onRowDragEnd( container, row ); };
				row.__binderyDragOver = function ( e ) { onRowDragOver( container, row, e ); };
				row.__binderyDrop = function ( e ) { onRowDrop( container, row, e ); };
				row.addEventListener( 'dragstart', row.__binderyDragStart );
				row.addEventListener( 'dragend', row.__binderyDragEnd );
				row.addEventListener( 'dragover', row.__binderyDragOver );
				row.addEventListener( 'drop', row.__binderyDrop );
			} );
			var addBtn = document.createElement( 'button' );
			addBtn.type = 'button';
			addBtn.className = 'bindery-fe-add-row';
			addBtn.textContent = '+ Add item';
			addBtn.addEventListener( 'click', function () { openRowEditor( container, null ); } );
			container.appendChild( addBtn );
			container.__binderyAddBtn = addBtn;
		} );
	}

	function exit() {
		editing = false;
		document.body.classList.remove( 'bindery-editing' );
		toggle.textContent = '✎ ';
		toggle.setAttribute( 'aria-pressed', 'false' );
		status.textContent = '';
		hideToolbar();
		editableRegions().forEach( function ( el ) {
			el.classList.remove( 'bindery-region-active', 'bindery-region-rich' );
			el.removeAttribute( 'contenteditable' );
			el.removeAttribute( 'role' );
			el.removeAttribute( 'aria-label' );
			el.removeEventListener( 'blur', onBlur );
			el.removeEventListener( 'keydown', onKey );
			el.removeEventListener( 'paste', onPaste );
			if ( el.__binderyFocus ) {
				el.removeEventListener( 'focus', el.__binderyFocus );
				el.__binderyFocus = null;
			}
			el.removeEventListener( 'focusout', onRichFocusOut );
		} );
		imageRegions().forEach( function ( el ) {
			el.classList.remove( 'bindery-region-active', 'bindery-region-image' );
			el.removeAttribute( 'role' );
			el.removeAttribute( 'tabindex' );
			el.removeAttribute( 'aria-label' );
			if ( el.__binderyImageClick ) {
				el.removeEventListener( 'click', el.__binderyImageClick );
				el.__binderyImageClick = null;
			}
			el.removeEventListener( 'keydown', onImageKey );
		} );
		repeaterRegions().forEach( function ( container ) {
			container.classList.remove( 'bindery-region-repeater-active' );
			rowEls( container ).forEach( function ( row ) {
				row.classList.remove( 'bindery-region-repeater-row', 'is-dragging', 'is-drop-target' );
				row.removeAttribute( 'draggable' );
				if ( row.__binderyRowControls ) {
					row.__binderyRowControls.remove();
					row.__binderyRowControls = null;
				}
				if ( row.__binderyDragStart ) {
					row.removeEventListener( 'dragstart', row.__binderyDragStart );
					row.removeEventListener( 'dragend', row.__binderyDragEnd );
					row.removeEventListener( 'dragover', row.__binderyDragOver );
					row.removeEventListener( 'drop', row.__binderyDrop );
					row.__binderyDragStart = row.__binderyDragEnd = row.__binderyDragOver = row.__binderyDrop = null;
				}
			} );
			if ( container.__binderyAddBtn ) {
				container.__binderyAddBtn.remove();
				container.__binderyAddBtn = null;
			}
		} );
	}

	toggle.addEventListener( 'click', function () {
		if ( editing ) {
			exit();
		} else {
			enter();
		}
	} );

	try {
		var forced = '1' === new URL( window.location.href ).searchParams.get( 'bindery-edit' );
		if ( forced || cfg.autoEnter ) {
			enter();
		}
	} catch ( e ) {}

	// Restore the scroll position saveRows() stashed before its reload. Done
	// twice — once now and once on the window's 'load' event — because
	// images below the fold are still loading at this point and can push
	// layout down after the first restore lands, undoing it.
	( function restoreScroll() {
		var y;
		try {
			y = sessionStorage.getItem( 'binderyScrollY' );
			sessionStorage.removeItem( 'binderyScrollY' );
		} catch ( e ) {
			return;
		}
		if ( null === y ) {
			return;
		}
		var target = parseInt( y, 10 ) || 0;
		window.scrollTo( 0, target );
		window.addEventListener( 'load', function () {
			window.scrollTo( 0, target );
		} );
	} )();
} )();
