/**
 * Build config for Bindery.
 *
 * Extends @wordpress/scripts' default config with explicit entries (the three
 * blocks' editor scripts + view scripts, and the standalone editor runtime).
 * The DependencyExtractionWebpackPlugin (from the default config) emits an
 * *.asset.php next to each bundle with the correct wp-* dependencies. Static
 * block files (block.json, render.php, style.css) are copied into build/ by
 * tools/copy-static.js after the build.
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'blocks/editable-text/index': path.resolve( __dirname, 'src-js/blocks/editable-text/index.js' ),
		'blocks/editable-text/view': path.resolve( __dirname, 'src-js/blocks/editable-text/view.js' ),
		'blocks/cards/index': path.resolve( __dirname, 'src-js/blocks/cards/index.js' ),
		'blocks/slider/index': path.resolve( __dirname, 'src-js/blocks/slider/index.js' ),
		'blocks/slider/view': path.resolve( __dirname, 'src-js/blocks/slider/view.js' ),
		'blocks/image/index': path.resolve( __dirname, 'src-js/blocks/image/index.js' ),
		'blocks/button/index': path.resolve( __dirname, 'src-js/blocks/button/index.js' ),
		'blocks/icon/index': path.resolve( __dirname, 'src-js/blocks/icon/index.js' ),
		'blocks/section/index': path.resolve( __dirname, 'src-js/blocks/section/index.js' ),
		'blocks/form/index': path.resolve( __dirname, 'src-js/blocks/form/index.js' ),
		'blocks/form/view': path.resolve( __dirname, 'src-js/blocks/form/view.js' ),
		'editor/index': path.resolve( __dirname, 'src-js/editor/index.js' ),
		'settings/index': path.resolve( __dirname, 'src-js/settings/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
