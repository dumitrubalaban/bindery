/**
 * Front-end submit handler for bindery/form (self-contained viewScript).
 * Posts the form to the REST endpoint via fetch and shows inline feedback.
 */
( function () {
	function init( form ) {
		const btn = form.querySelector( '.bindery-form__submit' );
		const msg = form.querySelector( '.bindery-form__message' );
		const rest = form.getAttribute( 'data-rest' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! rest ) {
				return;
			}

			const body = {};
			new FormData( form ).forEach( function ( v, k ) {
				body[ k ] = v;
			} );

			if ( btn ) {
				btn.disabled = true;
			}
			if ( msg ) {
				msg.hidden = true;
			}

			const headers = { 'Content-Type': 'application/json' };
			const wpNonce = form.getAttribute( 'data-wpnonce' );
			if ( wpNonce ) {
				// Authenticate cookie-logged-in users so the form nonce verifies
				// against the same user that rendered it.
				headers[ 'X-WP-Nonce' ] = wpNonce;
			}

			fetch( rest, {
				method: 'POST',
				headers,
				body: JSON.stringify( body ),
			} )
				.then( function ( r ) {
					return r.json().then( function ( j ) {
						return { ok: r.ok, data: j };
					} );
				} )
				.then( function ( res ) {
					if ( btn ) {
						btn.disabled = false;
					}
					if ( ! msg ) {
						return;
					}
					msg.hidden = false;
					if ( res.data && res.data.ok ) {
						form.reset();
						msg.className = 'bindery-form__message is-success';
						msg.textContent = form.getAttribute( 'data-success' ) || 'Thanks!';
					} else {
						msg.className = 'bindery-form__message is-error';
						let text = ( res.data && res.data.error ) || '';
						if ( ! text && res.data && res.data.errors ) {
							text = Object.keys( res.data.errors ).map( function ( k ) {
								return res.data.errors[ k ];
							} ).join( ' ' );
						}
						msg.textContent = text || 'Something went wrong.';
					}
				} )
				.catch( function () {
					if ( btn ) {
						btn.disabled = false;
					}
					if ( msg ) {
						msg.hidden = false;
						msg.className = 'bindery-form__message is-error';
						msg.textContent = 'Network error. Please try again.';
					}
				} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( 'form.bindery-form' ).forEach( init );
	} );
} )();
