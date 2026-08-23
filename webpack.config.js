const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

/**
 * wp-scripts bundles @wordpress/dataviews by default (private-API safety).
 * This plugin already requires WordPress 6.9+, which ships DataForm as
 * wp-dataviews, so we load core's copy and keep settings layout in sync.
 */
function isDataViewsRequest( request ) {
	return (
		request === '@wordpress/dataviews' ||
		request === '@wordpress/dataviews/wp'
	);
}

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				if ( isDataViewsRequest( request ) ) {
					return [ 'wp', 'dataviews' ];
				}
			},
			requestToHandle( request ) {
				if ( isDataViewsRequest( request ) ) {
					return 'wp-dataviews';
				}
			},
		} ),
	],
};
