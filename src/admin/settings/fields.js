import { __, sprintf } from '@wordpress/i18n';
import {
	BlockedKeywordsEdit,
	IntegerFieldEdit,
	KeywordChipsEdit,
	SelectFieldEdit,
	TextareaFieldEdit,
	TextFieldEdit,
	ToggleFieldEdit,
} from './form-controls';

const MODULE_FIELD_IDS = [
	'allowlist_enabled',
	'allowed_keywords',
	'geocoding_enabled',
	'geocoding_address_metas',
	'geocoding_country_suffix',
	'static_meta_enabled',
];

function getSettingsFields() {
	return [
		{
			id: 'feed_urls',
			label: __( 'List of URLs', 'bulk-event-importer' ),
			type: 'text',
			description: __(
				'Enter one feed URL per line in the following format: Feed Label | Feed URL | Feed Type (ics or rss, optional). Add a # before each line if you\'d like that feed ignored during imports.',
				'bulk-event-importer'
			),
			Edit: TextareaFieldEdit,
		},
		{
			id: 'default_feed_type',
			label: __( 'Default feed type', 'bulk-event-importer' ),
			type: 'text',
			elements: [
				{
					value: 'ics',
					label: __( 'ICS (Calendar Feed)', 'bulk-event-importer' ),
				},
				{
					value: 'rss',
					label: __(
						'RSS (Event / News Feed)',
						'bulk-event-importer'
					),
				},
			],
			description: __(
				'If you’re not sure, leave ICS (Calendar Feed). You can also put ics or rss at the end of a feed line to override this for that feed.',
				'bulk-event-importer'
			),
			Edit: SelectFieldEdit,
		},
		{
			id: 'past_days',
			label: __( 'Include events up to this many days in the past', 'bulk-event-importer' ),
			type: 'integer',
			Edit: IntegerFieldEdit,
		},
		{
			id: 'future_months',
			label: __( 'Include events up to this many months in the future', 'bulk-event-importer' ),
			type: 'integer',
			Edit: IntegerFieldEdit,
		},
		{
			id: 'trash_after_days',
			label: __( 'Trash events this many days after they end', 'bulk-event-importer' ),
			type: 'integer',
			Edit: IntegerFieldEdit,
		},
		{
			id: 'default_post_status',
			label: __( 'Status for newly imported events', 'bulk-event-importer' ),
			type: 'text',
			elements: [
				{ value: 'publish', label: __( 'Publish', 'bulk-event-importer' ) },
				{
					value: 'pending',
					label: __( 'Pending review', 'bulk-event-importer' ),
				},
				{ value: 'draft', label: __( 'Draft', 'bulk-event-importer' ) },
			],
			Edit: SelectFieldEdit,
		},
		{
			id: 'cron_interval',
			label: __( 'How often to run automatic imports', 'bulk-event-importer' ),
			type: 'text',
			elements: [
				{ value: 'hourly', label: __( 'Every hour', 'bulk-event-importer' ) },
				{
					value: 'twicedaily',
					label: __( 'Twice a day', 'bulk-event-importer' ),
				},
				{ value: 'daily', label: __( 'Once a day', 'bulk-event-importer' ) },
			],
			Edit: SelectFieldEdit,
		},
		{
			id: 'blocked_keywords',
			label: '',
			type: 'text',
			description: __(
				'Events with any of these words in the title will be skipped during import. If an event is already live on your site and matches, it will be removed.',
				'bulk-event-importer'
			),
			Edit: BlockedKeywordsEdit,
		},
		{
			id: 'allowlist_enabled',
			label: __(
				'Only import events with a required keyword',
				'bulk-event-importer'
			),
			type: 'integer',
			description: __(
				'When this is on, an event is imported only if its title, location, or link contains one of the keywords below.',
				'bulk-event-importer'
			),
			Edit: ToggleFieldEdit,
		},
		{
			id: 'geocoding_enabled',
			label: __( 'Geocoding', 'bulk-event-importer' ),
			type: 'integer',
			description: __(
				'When enabled, the importer will look up map coordinates for event locations. Requires a Google Geocoding API key defined as LL_GOOGLE_GEOCODE_KEY in wp-config.php.',
				'bulk-event-importer'
			),
			Edit: ToggleFieldEdit,
		},
		{
			id: 'allowed_keywords',
			label: __( 'Required keywords', 'bulk-event-importer' ),
			type: 'text',
			description: __(
				'Events must match at least one of these keywords in the title, location, or link.',
				'bulk-event-importer'
			),
			Edit: KeywordChipsEdit,
			isVisible: ( item ) => !! item.allowlist_enabled,
		},
		{
			id: 'geocoding_address_metas',
			label: __( 'Address field(s)', 'bulk-event-importer' ),
			type: 'text',
			description: __(
				'Which JetEngine field holds the address. Use a comma between names if the address is split across multiple fields.',
				'bulk-event-importer'
			),
			isVisible: ( item ) => !! item.geocoding_enabled,
			Edit: TextFieldEdit,
		},
		{
			id: 'geocoding_country_suffix',
			label: __( 'Country to append to addresses (optional)', 'bulk-event-importer' ),
			type: 'text',
			description: __(
				'Added to the end of the address when looking up coordinates, for example Canada.',
				'bulk-event-importer'
			),
			isVisible: ( item ) => !! item.geocoding_enabled,
			Edit: TextFieldEdit,
		},
		{
			id: 'static_meta_enabled',
			label: __( 'Fixed event fields', 'bulk-event-importer' ),
			type: 'integer',
			description: __(
				'When enabled, the importer can set the same field value on every imported event — for example a default button label. Most sites can leave this off.',
				'bulk-event-importer'
			),
			Edit: ToggleFieldEdit,
		},
	];
}

export function getCoreSettingsFields() {
	return getSettingsFields().filter(
		( field ) => ! MODULE_FIELD_IDS.includes( field.id )
	);
}

export function getOptionalModuleFields() {
	return getSettingsFields().filter( ( field ) =>
		MODULE_FIELD_IDS.includes( field.id )
	);
}

export function getSettingsFormLayout( feedUrlCount = 0 ) {
	const feedSectionLabel =
		feedUrlCount > 0
			? sprintf(
					/* translators: %d: number of configured feed URLs */
					__( 'Feed URLs (%d)', 'bulk-event-importer' ),
					feedUrlCount
			  )
			: __( 'Feed URLs', 'bulk-event-importer' );

	return {
		type: 'regular',
		labelPosition: 'top',
		layout: {
			type: 'regular',
			labelPosition: 'top',
		},
		fields: [
			{
				id: 'section_feeds',
				label: feedSectionLabel,
				children: [ 'feed_urls', 'default_feed_type' ],
			},
			{
				id: 'section_window',
				label: __( 'Import window & status', 'bulk-event-importer' ),
				children: [
					'past_days',
					'future_months',
					'trash_after_days',
					'default_post_status',
					'cron_interval',
				],
			},
			{
				id: 'section_blocked',
				label: __( 'Blocked Keywords', 'bulk-event-importer' ),
				children: [ 'blocked_keywords' ],
			},
		],
	};
}

function getOptionalModulesFormLayout( fieldIds = MODULE_FIELD_IDS ) {
	return {
		type: 'regular',
		labelPosition: 'top',
		layout: {
			type: 'regular',
			labelPosition: 'top',
		},
		fields: fieldIds,
	};
}

export function getGeocodingModulesFormLayout() {
	return getOptionalModulesFormLayout(
		MODULE_FIELD_IDS.filter( ( id ) => id !== 'static_meta_enabled' )
	);
}

export function getStaticMetaFormLayout() {
	return getOptionalModulesFormLayout( [ 'static_meta_enabled' ] );
}

export function mergeFormData( settings, formData ) {
	const next = {
		...settings,
		...formData,
	};

	[ 'allowlist_enabled', 'geocoding_enabled', 'static_meta_enabled' ].forEach(
		( key ) => {
			if ( Object.prototype.hasOwnProperty.call( formData, key ) ) {
				next[ key ] = !! formData[ key ];
			}
		}
	);

	return next;
}
