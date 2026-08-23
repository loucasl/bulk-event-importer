import { __ } from '@wordpress/i18n';

export function getSettingsFields() {
	return [
		{
			id: 'feed_urls',
			label: __( 'Feed URLs', 'bulk-event-importer' ),
			type: 'text',
			description: __(
				'One feed per line: Label | URL or Label | URL | type (type is ics or rss, optional). Lines starting with # are ignored.',
				'bulk-event-importer'
			),
			Edit: 'textarea',
		},
		{
			id: 'default_feed_type',
			label: __( 'Default type when not specified', 'bulk-event-importer' ),
			type: 'text',
			elements: [
				{ value: 'ics', label: 'ICS' },
				{ value: 'rss', label: 'RSS' },
			],
		},
		{
			id: 'past_days',
			label: __(
				'Import events starting up to (days in the past)',
				'bulk-event-importer'
			),
			type: 'integer',
		},
		{
			id: 'future_months',
			label: __(
				'Import events starting up to (months in the future)',
				'bulk-event-importer'
			),
			type: 'integer',
		},
		{
			id: 'trash_after_days',
			label: __(
				'Move past events to trash after (days)',
				'bulk-event-importer'
			),
			type: 'integer',
		},
		{
			id: 'default_post_status',
			label: __( 'New event post status', 'bulk-event-importer' ),
			type: 'text',
			elements: [
				{ value: 'publish', label: __( 'Publish', 'bulk-event-importer' ) },
				{
					value: 'pending',
					label: __( 'Pending review', 'bulk-event-importer' ),
				},
				{ value: 'draft', label: __( 'Draft', 'bulk-event-importer' ) },
			],
		},
		{
			id: 'cron_interval',
			label: __( 'Automatic import runs', 'bulk-event-importer' ),
			type: 'text',
			elements: [
				{ value: 'hourly', label: __( 'Every hour', 'bulk-event-importer' ) },
				{
					value: 'twicedaily',
					label: __( 'Twice a day', 'bulk-event-importer' ),
				},
				{ value: 'daily', label: __( 'Once a day', 'bulk-event-importer' ) },
			],
		},
		{
			id: 'blocked_keywords',
			label: __( 'Keywords', 'bulk-event-importer' ),
			type: 'array',
			description: __(
				'Events whose title contains any of these are skipped on import and removed if already published.',
				'bulk-event-importer'
			),
		},
		{
			id: 'allowlist_enabled',
			label: __( 'Allowlist Filter', 'bulk-event-importer' ),
			type: 'boolean',
			description: __(
				'Only import events that match at least one allowed keyword (title, location, or link).',
				'bulk-event-importer'
			),
		},
		{
			id: 'geocoding_enabled',
			label: __( 'Geocoding', 'bulk-event-importer' ),
			type: 'boolean',
			description: __(
				'Look up coordinates for event locations. Requires LL_GOOGLE_GEOCODE_KEY in wp-config.php.',
				'bulk-event-importer'
			),
		},
		{
			id: 'allowed_keywords',
			label: __( 'Allowed keywords', 'bulk-event-importer' ),
			type: 'array',
			description: __(
				'Only import events that match at least one of these keywords (checked against title, location, and link).',
				'bulk-event-importer'
			),
			isVisible: ( item ) => !! item.allowlist_enabled,
		},
		{
			id: 'geocoding_address_metas',
			label: __( 'Address source meta key(s)', 'bulk-event-importer' ),
			type: 'text',
			description: __(
				'Comma-separated if the address is built from more than one field.',
				'bulk-event-importer'
			),
			isVisible: ( item ) => !! item.geocoding_enabled,
		},
		{
			id: 'geocoding_lat_meta',
			label: __( 'Latitude meta key', 'bulk-event-importer' ),
			type: 'text',
			isVisible: ( item ) => !! item.geocoding_enabled,
		},
		{
			id: 'geocoding_lng_meta',
			label: __( 'Longitude meta key', 'bulk-event-importer' ),
			type: 'text',
			isVisible: ( item ) => !! item.geocoding_enabled,
		},
		{
			id: 'geocoding_hash_meta',
			label: __( 'Hash meta key (optional)', 'bulk-event-importer' ),
			type: 'text',
			isVisible: ( item ) => !! item.geocoding_enabled,
		},
		{
			id: 'geocoding_country_suffix',
			label: __( 'Country suffix (optional)', 'bulk-event-importer' ),
			type: 'text',
			isVisible: ( item ) => !! item.geocoding_enabled,
		},
	];
}

export function getSettingsFormLayout() {
	return {
		type: 'panel',
		fields: [
			{
				type: 'card',
				label: __( 'Feed URLs', 'bulk-event-importer' ),
				isOpened: true,
				fields: [ 'feed_urls', 'default_feed_type' ],
			},
			{
				type: 'card',
				label: __( 'Import Window & Status', 'bulk-event-importer' ),
				isOpened: true,
				fields: [
					{
						type: 'row',
						fields: [
							'past_days',
							'future_months',
							'trash_after_days',
						],
					},
					{
						type: 'row',
						fields: [ 'default_post_status', 'cron_interval' ],
					},
				],
			},
			{
				type: 'card',
				label: __( 'Blocked Keywords', 'bulk-event-importer' ),
				isOpened: true,
				fields: [ 'blocked_keywords' ],
			},
			{
				type: 'card',
				label: __( 'Optional Modules', 'bulk-event-importer' ),
				description: __(
					'Enable a module to show its settings below. Disabled modules are left out of import behaviour.',
					'bulk-event-importer'
				),
				isOpened: true,
				fields: [ 'allowlist_enabled', 'geocoding_enabled' ],
			},
			{
				type: 'card',
				label: __( 'Allowlist Filter', 'bulk-event-importer' ),
				isOpened: true,
				fields: [ 'allowed_keywords' ],
			},
			{
				type: 'card',
				label: __( 'Geocoding', 'bulk-event-importer' ),
				description: __(
					'Requires LL_GOOGLE_GEOCODE_KEY defined in wp-config.php. Meta keys must match this site\'s JetEngine map field.',
					'bulk-event-importer'
				),
				isOpened: true,
				fields: [
					'geocoding_address_metas',
					{
						type: 'row',
						fields: [
							'geocoding_lat_meta',
							'geocoding_lng_meta',
						],
					},
					{
						type: 'row',
						fields: [
							'geocoding_hash_meta',
							'geocoding_country_suffix',
						],
					},
				],
			},
		],
	};
}

export function getFormData( settings ) {
	if ( ! settings ) {
		return {};
	}
	return {
		feed_urls: settings.feed_urls,
		default_feed_type: settings.default_feed_type,
		past_days: settings.past_days,
		future_months: settings.future_months,
		trash_after_days: settings.trash_after_days,
		default_post_status: settings.default_post_status,
		cron_interval: settings.cron_interval,
		blocked_keywords: settings.blocked_keywords || [],
		allowlist_enabled: !! settings.allowlist_enabled,
		allowed_keywords: settings.allowed_keywords || [],
		geocoding_enabled: !! settings.geocoding_enabled,
		geocoding_address_metas: settings.geocoding_address_metas,
		geocoding_lat_meta: settings.geocoding_lat_meta,
		geocoding_lng_meta: settings.geocoding_lng_meta,
		geocoding_hash_meta: settings.geocoding_hash_meta,
		geocoding_country_suffix: settings.geocoding_country_suffix,
	};
}

export function mergeFormData( settings, formData ) {
	return {
		...settings,
		...formData,
		allowlist_enabled: !! formData.allowlist_enabled,
		geocoding_enabled: !! formData.geocoding_enabled,
	};
}
