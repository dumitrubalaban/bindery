/**
 * Front-end carousel for bindery/slider (self-contained viewScript).
 * Auto-advances, prev/next + dots, pause on hover.
 */
( function () {
	function initSlider( root ) {
		const track = root.querySelector( '.bindery-slider__track' );
		const slides = root.querySelectorAll( '.bindery-slide' );
		const dots = root.querySelectorAll( '.bindery-slider__dot' );
		const n = slides.length;
		if ( ! track || n < 2 ) {
			return;
		}

		let idx = 0;
		let timer = null;
		const autoplay = '1' === root.getAttribute( 'data-autoplay' );
		const interval = parseInt( root.getAttribute( 'data-interval' ), 10 ) || 5000;

		function go( i ) {
			idx = ( ( i % n ) + n ) % n;
			track.style.transform = 'translateX(-' + idx * 100 + '%)';
			dots.forEach( ( d, di ) => d.classList.toggle( 'is-active', di === idx ) );
		}
		const next = () => go( idx + 1 );
		const prev = () => go( idx - 1 );
		function stop() {
			if ( timer ) {
				clearInterval( timer );
				timer = null;
			}
		}
		function start() {
			if ( autoplay && ! timer ) {
				timer = setInterval( next, interval );
			}
		}
		const reset = () => {
			stop();
			start();
		};

		const nextBtn = root.querySelector( '.bindery-slider__next' );
		const prevBtn = root.querySelector( '.bindery-slider__prev' );
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', () => {
				next();
				reset();
			} );
		}
		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', () => {
				prev();
				reset();
			} );
		}
		dots.forEach( ( dot ) =>
			dot.addEventListener( 'click', () => {
				go( parseInt( dot.getAttribute( 'data-index' ), 10 ) || 0 );
				reset();
			} )
		);

		root.addEventListener( 'mouseenter', stop );
		root.addEventListener( 'mouseleave', start );

		go( 0 );
		start();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.bindery-slider' ).forEach( initSlider );
	} );
} )();
