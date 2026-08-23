export function genGroupKey() {
	return (
		'grp_' +
		Math.random().toString( 36 ).slice( 2, 8 ) +
		Date.now().toString( 36 ).slice( -4 )
	);
}

export function cloneSettings( data ) {
	return JSON.parse( JSON.stringify( data ) );
}

export function settingsEqual( a, b ) {
	return JSON.stringify( a ) === JSON.stringify( b );
}

/**
 * Count configured feed lines (ignores blanks and # comments).
 *
 * @param {string} raw
 * @return {number}
 */
export function countFeedUrls( raw ) {
	return String( raw || '' )
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line && ! line.startsWith( '#' ) ).length;
}
