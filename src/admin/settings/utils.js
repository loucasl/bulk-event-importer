export function genGroupKey() {
	return (
		'grp_' +
		Math.random().toString( 36 ).slice( 2, 8 ) +
		Date.now().toString( 36 ).slice( -4 )
	);
}

export function defaultSettings() {
	return {
		feed_urls: '',
		default_feed_type: 'ics',
		past_days: 7,
		future_months: 18,
		trash_after_days: 7,
		default_post_status: 'publish',
		cron_interval: 'hourly',
		blocked_keywords: [],
		allowlist_enabled: false,
		allowed_keywords: [],
		geocoding_enabled: false,
		geocoding_address_metas: '',
		geocoding_lat_meta: '',
		geocoding_lng_meta: '',
		geocoding_hash_meta: '',
		geocoding_country_suffix: '',
		taxonomies: [],
		field_map: {
			date_mode: 'split',
			je_date_meta: '',
			start_date_meta: 'event-start-date',
			start_time_meta: 'event-start-time',
			end_date_meta: 'event-end-date',
			end_time_meta: 'event-end-time',
			location_meta: 'event-location',
			description_long_meta: '',
			description_short_meta: '',
			external_url_meta: 'event-link',
			source_meta: 'event-source',
			extra_static_meta: [],
		},
	};
}

export function cloneSettings( data ) {
	return JSON.parse( JSON.stringify( data ) );
}

export function settingsEqual( a, b ) {
	return JSON.stringify( a ) === JSON.stringify( b );
}

/**
 * paginationInfo required by DataViews since @wordpress/dataviews 4.x.
 *
 * @param {number} itemCount
 * @param {number} perPage
 * @return {{ totalItems: number, totalPages: number }}
 */
export function getLocalPaginationInfo( itemCount, perPage = 10 ) {
	const totalItems = Math.max( 0, itemCount );
	const pageSize = Math.max( 1, perPage );

	return {
		totalItems,
		totalPages: Math.max( 1, Math.ceil( totalItems / pageSize ) ),
	};
}
