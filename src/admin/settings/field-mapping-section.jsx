import {
	Button,
	SelectControl,
	TextControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function FieldMappingSection( { fieldMap, onChange } ) {
	const isSplit = fieldMap.date_mode === 'split';
	const isJe = fieldMap.date_mode === 'je_advanced_date';

	const setField = ( key, value ) => {
		onChange( { ...fieldMap, [ key ]: value } );
	};

	return (
		<section className="bei-settings-section">
			<h2>{ __( 'JetEngine Field Mapping', 'bulk-event-importer' ) }</h2>
			<p className="description">
				{ __(
					'Map internal event data to this site\'s actual JetEngine meta keys. Leave a field blank to skip writing it. Check a field\'s meta key under Custom Fields in JetEngine\'s field group editor.',
					'bulk-event-importer'
				) }
			</p>
			<VStack spacing={ 4 }>
				<SelectControl
					label={ __( 'Date field type', 'bulk-event-importer' ) }
					value={ fieldMap.date_mode }
					onChange={ ( value ) => setField( 'date_mode', value ) }
					options={ [
						{
							label: __(
								'Separate start/end date + time fields',
								'bulk-event-importer'
							),
							value: 'split',
						},
						{
							label: __(
								'JetEngine "Advanced Date" field',
								'bulk-event-importer'
							),
							value: 'je_advanced_date',
						},
					] }
				/>
				{ isSplit && (
					<>
						<TextControl
							label={ __(
								'Start date meta key',
								'bulk-event-importer'
							) }
							value={ fieldMap.start_date_meta }
							onChange={ ( v ) =>
								setField( 'start_date_meta', v )
							}
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __(
								'End date meta key',
								'bulk-event-importer'
							) }
							value={ fieldMap.end_date_meta }
							onChange={ ( v ) => setField( 'end_date_meta', v ) }
							__next40pxDefaultSize
						/>
					</>
				) }
				{ isJe && (
					<TextControl
						label={ __(
							'Advanced Date meta key',
							'bulk-event-importer'
						) }
						value={ fieldMap.je_date_meta }
						onChange={ ( v ) => setField( 'je_date_meta', v ) }
						__next40pxDefaultSize
					/>
				) }
				<TextControl
					label={ __( 'Start time meta key', 'bulk-event-importer' ) }
					value={ fieldMap.start_time_meta }
					onChange={ ( v ) => setField( 'start_time_meta', v ) }
					__next40pxDefaultSize
				/>
				<TextControl
					label={ __( 'End time meta key', 'bulk-event-importer' ) }
					value={ fieldMap.end_time_meta }
					onChange={ ( v ) => setField( 'end_time_meta', v ) }
					__next40pxDefaultSize
				/>
				<TextControl
					label={ __( 'Location meta key', 'bulk-event-importer' ) }
					value={ fieldMap.location_meta }
					onChange={ ( v ) => setField( 'location_meta', v ) }
					__next40pxDefaultSize
				/>
				<TextControl
					label={ __(
						'Long description meta key (optional)',
						'bulk-event-importer'
					) }
					value={ fieldMap.description_long_meta }
					onChange={ ( v ) =>
						setField( 'description_long_meta', v )
					}
					__next40pxDefaultSize
				/>
				<TextControl
					label={ __(
						'Short description meta key (optional)',
						'bulk-event-importer'
					) }
					value={ fieldMap.description_short_meta }
					onChange={ ( v ) =>
						setField( 'description_short_meta', v )
					}
					__next40pxDefaultSize
				/>
				<TextControl
					label={ __(
						'External URL meta key',
						'bulk-event-importer'
					) }
					value={ fieldMap.external_url_meta }
					onChange={ ( v ) => setField( 'external_url_meta', v ) }
					__next40pxDefaultSize
				/>
				<TextControl
					label={ __( 'Source meta key', 'bulk-event-importer' ) }
					value={ fieldMap.source_meta }
					onChange={ ( v ) => setField( 'source_meta', v ) }
					__next40pxDefaultSize
				/>
			</VStack>
		</section>
	);
}

export function StaticMetaSection( { extraStaticMeta, onChange } ) {
	const rows = extraStaticMeta || [];

	return (
		<section className="bei-settings-section">
			<h2>
				{ __(
					'Static meta (optional, written on every create/update)',
					'bulk-event-importer'
				) }
			</h2>
			<div className="bei-extra-meta-rows">
				{ rows.map( ( row, index ) => (
					<div className="bei-extra-row" key={ `extra-${ index }` }>
						<TextControl
							value={ row.key }
							onChange={ ( key ) => {
								const next = [ ...rows ];
								next[ index ] = { ...next[ index ], key };
								onChange( next );
							} }
							placeholder={ __(
								'Meta key',
								'bulk-event-importer'
							) }
							hideLabelFromVision
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<TextControl
							value={ row.value }
							onChange={ ( value ) => {
								const next = [ ...rows ];
								next[ index ] = { ...next[ index ], value };
								onChange( next );
							} }
							placeholder={ __(
								'Value',
								'bulk-event-importer'
							) }
							hideLabelFromVision
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<Button
							type="button"
							variant="link"
							isDestructive
							onClick={ () =>
								onChange(
									rows.filter( ( _, i ) => i !== index )
								)
							}
							aria-label={ __(
								'Remove static meta field',
								'bulk-event-importer'
							) }
						>
							&times;
						</Button>
					</div>
				) ) }
			</div>
			<Button
				type="button"
				variant="secondary"
				onClick={ () =>
					onChange( [ ...rows, { key: '', value: '' } ] )
				}
			>
				{ __( 'Add static meta field', 'bulk-event-importer' ) }
			</Button>
		</section>
	);
}
