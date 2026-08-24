import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { AdvancedDisclosure } from './advanced-disclosure';

export function GeocodingAdvancedFields( { settings, onChange } ) {
	const setField = ( key, value ) => onChange( { [ key ]: value } );

	return (
		<AdvancedDisclosure
			className="bei-geocoding-advanced"
			label={ __( 'Show map field names', 'bulk-event-importer' ) }
		>
			<p className="description">
				{ __(
					'These must match this site’s JetEngine map fields. Change them only if maps are writing to the wrong place.',
					'bulk-event-importer'
				) }
			</p>
			<TextControl
				label={ __( 'Latitude field', 'bulk-event-importer' ) }
				value={ settings.geocoding_lat_meta || '' }
				onChange={ ( v ) => setField( 'geocoding_lat_meta', v ) }
				__next40pxDefaultSize
			/>
			<TextControl
				label={ __( 'Longitude field', 'bulk-event-importer' ) }
				value={ settings.geocoding_lng_meta || '' }
				onChange={ ( v ) => setField( 'geocoding_lng_meta', v ) }
				__next40pxDefaultSize
			/>
			<TextControl
				label={ __( 'Location hash field (optional)', 'bulk-event-importer' ) }
				value={ settings.geocoding_hash_meta || '' }
				onChange={ ( v ) => setField( 'geocoding_hash_meta', v ) }
				__next40pxDefaultSize
			/>
		</AdvancedDisclosure>
	);
}
