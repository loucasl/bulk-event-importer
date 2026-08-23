import { useMemo } from '@wordpress/element';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { getLocalPaginationInfo } from './utils';

export function FieldMappingSection( { fieldMap, onChange } ) {
	const isSplit = fieldMap.date_mode === 'split';
	const isJe = fieldMap.date_mode === 'je_advanced_date';

	const setField = ( key, value ) => {
		onChange( { ...fieldMap, [ key ]: value } );
	};

	return (
		<PanelBody
			title={ __( 'JetEngine Field Mapping', 'bulk-event-importer' ) }
			initialOpen
		>
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
						/>
						<TextControl
							label={ __(
								'End date meta key',
								'bulk-event-importer'
							) }
							value={ fieldMap.end_date_meta }
							onChange={ ( v ) => setField( 'end_date_meta', v ) }
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
					/>
				) }
				<TextControl
					label={ __( 'Start time meta key', 'bulk-event-importer' ) }
					value={ fieldMap.start_time_meta }
					onChange={ ( v ) => setField( 'start_time_meta', v ) }
				/>
				<TextControl
					label={ __( 'End time meta key', 'bulk-event-importer' ) }
					value={ fieldMap.end_time_meta }
					onChange={ ( v ) => setField( 'end_time_meta', v ) }
				/>
				<TextControl
					label={ __( 'Location meta key', 'bulk-event-importer' ) }
					value={ fieldMap.location_meta }
					onChange={ ( v ) => setField( 'location_meta', v ) }
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
				/>
				<TextControl
					label={ __(
						'External URL meta key',
						'bulk-event-importer'
					) }
					value={ fieldMap.external_url_meta }
					onChange={ ( v ) => setField( 'external_url_meta', v ) }
				/>
				<TextControl
					label={ __( 'Source meta key', 'bulk-event-importer' ) }
					value={ fieldMap.source_meta }
					onChange={ ( v ) => setField( 'source_meta', v ) }
				/>
			</VStack>
		</PanelBody>
	);
}

export function StaticMetaSection( { extraStaticMeta, onChange } ) {
	const rows = ( extraStaticMeta || [] ).map( ( row, index ) => ( {
		...row,
		id: `extra-${ index }`,
		_index: index,
	} ) );

	const fields = useMemo(
		() => [
			{
				id: 'key',
				label: __( 'Key', 'bulk-event-importer' ),
				type: 'text',
				enableSorting: false,
				enableHiding: false,
				render: ( { item } ) => (
					<TextControl
						value={ item.key }
						onChange={ ( key ) => {
							const next = [ ...( extraStaticMeta || [] ) ];
							next[ item._index ] = {
								...next[ item._index ],
								key,
							};
							onChange( next );
						} }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'value',
				label: __( 'Value', 'bulk-event-importer' ),
				type: 'text',
				enableSorting: false,
				enableHiding: false,
				render: ( { item } ) => (
					<TextControl
						value={ item.value }
						onChange={ ( value ) => {
							const next = [ ...( extraStaticMeta || [] ) ];
							next[ item._index ] = {
								...next[ item._index ],
								value,
							};
							onChange( next );
						} }
						__nextHasNoMarginBottom
					/>
				),
			},
		],
		[ extraStaticMeta, onChange ]
	);

	const view = useMemo(
		() => ( {
			type: 'table',
			titleField: 'key',
			fields: [ 'key', 'value' ],
			perPage: 100,
			page: 1,
			sort: { field: 'key', direction: 'asc' },
			search: '',
			filters: [],
			layout: { density: 'comfortable' },
		} ),
		[]
	);

	return (
		<PanelBody
			title={ __(
				'Static meta (optional, written on every create/update)',
				'bulk-event-importer'
			) }
			initialOpen
		>
			<DataViews
				data={ rows }
				fields={ fields }
				view={ view }
				onChangeView={ () => {} }
				defaultLayouts={ { table: {} } }
				getItemId={ ( item ) => item.id }
				paginationInfo={ getLocalPaginationInfo(
					rows.length,
					view.perPage
				) }
				actions={ [
					{
						id: 'remove',
						label: __( 'Remove', 'bulk-event-importer' ),
						isPrimary: true,
						callback: ( items ) => {
							const removeIndexes = new Set(
								items.map( ( item ) => item._index )
							);
							onChange(
								( extraStaticMeta || [] ).filter(
									( _, i ) => ! removeIndexes.has( i )
								)
							);
						},
					},
				] }
			/>
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( [
						...( extraStaticMeta || [] ),
						{ key: '', value: '' },
					] )
				}
				style={ { marginTop: '12px' } }
			>
				{ __( 'Add static meta field', 'bulk-event-importer' ) }
			</Button>
		</PanelBody>
	);
}
