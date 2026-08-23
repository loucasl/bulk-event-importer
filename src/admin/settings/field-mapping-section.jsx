import {
	Button,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { RemoveButton } from './remove-button';

function MapField( { label, value, onChange, className = '' } ) {
	return (
		<TextControl
			className={ className }
			label={ label }
			value={ value }
			onChange={ onChange }
			__next40pxDefaultSize
			__nextHasNoMarginBottom
		/>
	);
}

function MapGroup( { title, children } ) {
	return (
		<div className="bei-field-map-group">
			<h3>{ title }</h3>
			<div className="bei-field-map-grid">{ children }</div>
		</div>
	);
}

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
					'These names must match the JetEngine custom fields on this site. Leave a box blank to skip it.',
					'bulk-event-importer'
				) }
			</p>

			<MapGroup title={ __( 'Dates', 'bulk-event-importer' ) }>
				<SelectControl
					className="bei-field-map-full"
					label={ __(
						'How are dates stored on this site?',
						'bulk-event-importer'
					) }
					value={ fieldMap.date_mode }
					onChange={ ( value ) => setField( 'date_mode', value ) }
					__next40pxDefaultSize
					options={ [
						{
							label: __(
								'Separate start and end date/time fields',
								'bulk-event-importer'
							),
							value: 'split',
						},
						{
							label: __(
								'One JetEngine Advanced Date field',
								'bulk-event-importer'
							),
							value: 'je_advanced_date',
						},
					] }
				/>
				{ isSplit && (
					<>
						<MapField
							label={ __(
								'Start date field',
								'bulk-event-importer'
							) }
							value={ fieldMap.start_date_meta }
							onChange={ ( v ) =>
								setField( 'start_date_meta', v )
							}
						/>
						<MapField
							label={ __( 'End date field', 'bulk-event-importer' ) }
							value={ fieldMap.end_date_meta }
							onChange={ ( v ) => setField( 'end_date_meta', v ) }
						/>
					</>
				) }
				{ isJe && (
					<MapField
						className="bei-field-map-full"
						label={ __(
							'Advanced Date field',
							'bulk-event-importer'
						) }
						value={ fieldMap.je_date_meta }
						onChange={ ( v ) => setField( 'je_date_meta', v ) }
					/>
				) }
			</MapGroup>

			<MapGroup title={ __( 'Times', 'bulk-event-importer' ) }>
				<MapField
					label={ __( 'Start time field', 'bulk-event-importer' ) }
					value={ fieldMap.start_time_meta }
					onChange={ ( v ) => setField( 'start_time_meta', v ) }
				/>
				<MapField
					label={ __( 'End time field', 'bulk-event-importer' ) }
					value={ fieldMap.end_time_meta }
					onChange={ ( v ) => setField( 'end_time_meta', v ) }
				/>
			</MapGroup>

			<MapGroup title={ __( 'Event details', 'bulk-event-importer' ) }>
				<MapField
					label={ __( 'Location field', 'bulk-event-importer' ) }
					value={ fieldMap.location_meta }
					onChange={ ( v ) => setField( 'location_meta', v ) }
				/>
				<MapField
					label={ __(
						'Long description field (optional)',
						'bulk-event-importer'
					) }
					value={ fieldMap.description_long_meta }
					onChange={ ( v ) =>
						setField( 'description_long_meta', v )
					}
				/>
				<MapField
					label={ __(
						'Short description field (optional)',
						'bulk-event-importer'
					) }
					value={ fieldMap.description_short_meta }
					onChange={ ( v ) =>
						setField( 'description_short_meta', v )
					}
				/>
				<MapField
					label={ __( 'External link field', 'bulk-event-importer' ) }
					value={ fieldMap.external_url_meta }
					onChange={ ( v ) => setField( 'external_url_meta', v ) }
				/>
				<MapField
					label={ __(
						'Source/calendar name field',
						'bulk-event-importer'
					) }
					value={ fieldMap.source_meta }
					onChange={ ( v ) => setField( 'source_meta', v ) }
				/>
			</MapGroup>
		</section>
	);
}

export function StaticMetaSection( { extraStaticMeta, onChange } ) {
	const rows = extraStaticMeta || [];

	return (
		<div className="bei-static-meta-fields">
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
								'Field name',
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
						<RemoveButton
							confirmMessage={ __(
								'Remove this fixed field?',
								'bulk-event-importer'
							) }
							onConfirm={ () =>
								onChange(
									rows.filter( ( _, i ) => i !== index )
								)
							}
							aria-label={ __(
								'Remove fixed field',
								'bulk-event-importer'
							) }
						>
							{ __( 'Remove', 'bulk-event-importer' ) }
						</RemoveButton>
					</div>
				) ) }
			</div>
			<Button
				type="button"
				variant="secondary"
				className="bei-inline-button"
				onClick={ () =>
					onChange( [ ...rows, { key: '', value: '' } ] )
				}
			>
				{ __( 'Add fixed field', 'bulk-event-importer' ) }
			</Button>
		</div>
	);
}
