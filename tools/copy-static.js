/**
 * Copy each block's static files (block.json, render.php, style.css) from
 * blocks/<name>/ into build/blocks/<name>/ so register_block_type( build/... )
 * finds the manifest, render callback and styles next to the built scripts.
 *
 * Dependency-free (Node fs only) and cross-platform.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const srcBlocks = path.join( root, 'blocks' );
const outBlocks = path.join( root, 'build', 'blocks' );

const STATIC = [ 'block.json', 'render.php', 'style.css', 'icons.json' ];

if ( ! fs.existsSync( srcBlocks ) ) {
	process.exit( 0 );
}

let copied = 0;
for ( const name of fs.readdirSync( srcBlocks ) ) {
	const srcDir = path.join( srcBlocks, name );
	if ( ! fs.statSync( srcDir ).isDirectory() ) {
		continue;
	}
	const outDir = path.join( outBlocks, name );
	fs.mkdirSync( outDir, { recursive: true } );
	for ( const file of STATIC ) {
		const from = path.join( srcDir, file );
		if ( fs.existsSync( from ) ) {
			fs.copyFileSync( from, path.join( outDir, file ) );
			copied++;
		}
	}
}

// eslint-disable-next-line no-console
console.log( `[bindery] copied ${ copied } static block files to build/blocks/` );
