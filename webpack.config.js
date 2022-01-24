const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

const requestToExternal = ( request ) => {
	// Bundle these packages & components so we can use the latest, independent of WordPress version.
	// Without bundling these specific recent versions, components like LandingPageApp don't render correctly.
	const bundled = [ '@wordpress/components', '@wordpress/compose' ];
	if ( bundled.includes( request ) ) {
		return false;
	}
};

// Replace the default DependencyExtractionWebpackPlugin with the Woo version
// and override to bundle specific newer packages (see requestToExternal above).
const ourPlugins = [
	...defaultConfig.plugins.filter(
		( plugin ) =>
			plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
	),
	new WooCommerceDependencyExtractionWebpackPlugin( {
		injectPolyfill: true, // TBD Confirm this is needed for Pinterest.
		requestToExternal,
	} ),
];

// Webpack config for main admin app - onboarding and settings.
const SetupGuide = {
	...defaultConfig,
	plugins: ourPlugins,
	entry: {
		index: './assets/source/setup-guide/index.js',
	},
	output: {
		...defaultConfig.output,
		filename: '[name].js',
		path: __dirname + '/assets/setup-guide',
	},
};

// Webpack config for script to add our setup task.
const SetupTask = {
	...defaultConfig,
	plugins: ourPlugins,
	entry: {
		index: './assets/source/setup-task/index.js',
	},
	output: {
		filename: '[name].js',
		path: __dirname + '/assets/setup-task',
	},
};

// Webpack config for product-attributes.
const ProductAttributes = {
	...defaultConfig,
	plugins: ourPlugins,
	entry: {
		index: './assets/source/product-attributes/index.js',
	},
	output: {
		filename: '[name].js',
		path: __dirname + '/assets/product-attributes',
	},
};

module.exports = [ SetupGuide, SetupTask, ProductAttributes ];
