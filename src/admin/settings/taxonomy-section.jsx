import { useMemo, useState } from '@wordpress/element';
import {
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	FormTokenField,
	PanelBody,
	PanelRow,
	TextControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { genGroupKey } from './utils';

function KeywordTokens( { value, onChange } ) {
	return (
		<FormTokenField
			value={ value || [] }
			onChange={ ( tokens ) => onChange( tokens ) }
			__experimentalExpandOnFocus
			__experimentalShowHowTo={ false }
			label={ __( 'Keywords', 'bulk-event-importer' ) }
			hideLabelFromVision
		/>
	);
}

function TaxonomyBlock( { taxonomy, taxIndex, onChange, onRemove, defaultOpen } ) {
	const [ isOpen, setIsOpen ] = useState( defaultOpen );

	const fields = useMemo(
		() => [
			{
				id: 'term',
				label: __( 'Term', 'bulk-event-importer' ),
				type: 'text',
				enableSorting: false,
				enableHiding: false,
				render: ( { item } ) => (
					<TextControl
						value={ item.term }
						onChange={ ( term ) => {
							const groups = taxonomy.groups.map( ( g, i ) =>
								i === item._index ? { ...g, term } : g
							);
							onChange( { ...taxonomy, groups } );
						} }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'keywords',
				label: __( 'Keywords', 'bulk-event-importer' ),
				type: 'text',
				enableSorting: false,
				enableHiding: false,
				render: ( { item } ) => (
					<KeywordTokens
						value={ item.keywords || [] }
						onChange={ ( keywords ) => {
							const groups = taxonomy.groups.map( ( g, i ) =>
								i === item._index ? { ...g, keywords } : g
							);
							onChange( { ...taxonomy, groups } );
						} }
					/>
				),
			},
		],
		[ taxonomy, onChange ]
	);

	const view = useMemo(
		() => ( {
			type: 'table',
			titleField: 'term',
			fields: [ 'term', 'keywords' ],
			perPage: 100,
			page: 1,
			sort: { field: 'term', direction: 'asc' },
			search: '',
			filters: [],
			layout: { density: 'comfortable' },
		} ),
		[]
	);

	const rows = ( taxonomy.groups || [] ).map( ( group, index ) => ( {
		...group,
		id: group.key || `row-${ index }`,
		_index: index,
	} ) );

	const defaultLayouts = useMemo(
		() => ( {
			table: {},
		} ),
		[]
	);

	return (
		<PanelBody
			title={
				taxonomy.label ||
				__( 'New taxonomy', 'bulk-event-importer' )
			}
			initialOpen={ isOpen }
			onToggle={ () => setIsOpen( ( open ) => ! open ) }
		>
			<VStack spacing={ 4 }>
				<Flex gap={ 4 } wrap>
					<FlexBlock>
						<TextControl
							label={ __( 'Taxonomy slug', 'bulk-event-importer' ) }
							value={ taxonomy.slug }
							onChange={ ( slug ) =>
								onChange( { ...taxonomy, slug } )
							}
						/>
					</FlexBlock>
					<FlexBlock>
						<TextControl
							label={ __( 'Display label', 'bulk-event-importer' ) }
							value={ taxonomy.label }
							onChange={ ( label ) =>
								onChange( { ...taxonomy, label } )
							}
						/>
					</FlexBlock>
					<FlexBlock>
						<TextControl
							label={ __(
								'Default term (optional)',
								'bulk-event-importer'
							) }
							value={ taxonomy.default_term }
							onChange={ ( default_term ) =>
								onChange( { ...taxonomy, default_term } )
							}
							help={ __(
								'Applied when nothing matches.',
								'bulk-event-importer'
							) }
						/>
					</FlexBlock>
				</Flex>

				<DataViews
					data={ rows }
					fields={ fields }
					view={ view }
					onChangeView={ () => {} }
					defaultLayouts={ defaultLayouts }
					getItemId={ ( item ) => item.id }
					actions={ [
						{
							id: 'remove',
							label: __( 'Remove', 'bulk-event-importer' ),
							isPrimary: true,
							callback: ( items ) => {
								const removeIds = new Set(
									items.map( ( item ) => item.id )
								);
								onChange( {
									...taxonomy,
									groups: taxonomy.groups.filter(
										( g ) => ! removeIds.has( g.key )
									),
								} );
							},
						},
					] }
				/>

				<Flex>
					<Button
						variant="secondary"
						onClick={ () =>
							onChange( {
								...taxonomy,
								groups: [
									...( taxonomy.groups || [] ),
									{
										key: genGroupKey(),
										term: '',
										keywords: [],
									},
								],
							} )
						}
					>
						{ __( 'Add category', 'bulk-event-importer' ) }
					</Button>
					<FlexItem>
						<Button
							variant="link"
							isDestructive
							onClick={ onRemove }
						>
							{ __( 'Remove taxonomy', 'bulk-event-importer' ) }
						</Button>
					</FlexItem>
				</Flex>
			</VStack>
		</PanelBody>
	);
}

export function TaxonomySection( { taxonomies, onChange } ) {
	return (
		<div className="bei-settings-taxonomies">
			<PanelBody
				title={ __( 'Categories & Taxonomies', 'bulk-event-importer' ) }
				initialOpen
			>
				<p className="description">
					{ __(
						'Define the taxonomies this site uses and the keyword groups that auto-assign each term. Structure is entirely per-site; nothing here is hardcoded in the plugin.',
						'bulk-event-importer'
					) }
				</p>
				{ ( taxonomies || [] ).map( ( tax, index ) => (
					<TaxonomyBlock
						key={ `tax-${ index }-${ tax.slug || 'new' }` }
						taxonomy={ tax }
						taxIndex={ index }
						defaultOpen={ index === 0 }
						onChange={ ( updated ) => {
							const next = [ ...taxonomies ];
							next[ index ] = updated;
							onChange( next );
						} }
						onRemove={ () => {
							onChange(
								taxonomies.filter( ( _, i ) => i !== index )
							);
						} }
					/>
				) ) }
				<PanelRow>
					<Button
						variant="secondary"
						onClick={ () =>
							onChange( [
								...( taxonomies || [] ),
								{
									slug: '',
									label: '',
									default_term: '',
									groups: [],
								},
							] )
						}
					>
						{ __( 'Add taxonomy', 'bulk-event-importer' ) }
					</Button>
				</PanelRow>
			</PanelBody>
		</div>
	);
}
