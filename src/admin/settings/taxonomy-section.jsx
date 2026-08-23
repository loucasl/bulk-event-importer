import {
	Button,
	Flex,
	FlexBlock,
	TextControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { KeywordChips } from './keyword-chips';
import { genGroupKey } from './utils';

function TaxonomyBlock( { taxonomy, onChange, onRemove } ) {
	const groups = taxonomy.groups || [];

	return (
		<div className="bei-taxonomy-block">
			<h3 className="bei-taxonomy-block-title">
				{ taxonomy.label || __( 'New taxonomy', 'bulk-event-importer' ) }
			</h3>

			<Flex gap={ 4 } wrap className="bei-taxonomy-meta">
				<FlexBlock>
					<TextControl
						label={ __( 'Taxonomy slug', 'bulk-event-importer' ) }
						value={ taxonomy.slug }
						onChange={ ( slug ) => onChange( { ...taxonomy, slug } ) }
						__next40pxDefaultSize
					/>
				</FlexBlock>
				<FlexBlock>
					<TextControl
						label={ __( 'Display label', 'bulk-event-importer' ) }
						value={ taxonomy.label }
						onChange={ ( label ) => onChange( { ...taxonomy, label } ) }
						__next40pxDefaultSize
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
						__next40pxDefaultSize
					/>
				</FlexBlock>
			</Flex>

			<table className="widefat fixed striped bei-group-table">
				<thead>
					<tr>
						<th style={ { width: '22%' } }>
							{ __( 'Term name', 'bulk-event-importer' ) }
						</th>
						<th>{ __( 'Keywords', 'bulk-event-importer' ) }</th>
						<th style={ { width: '72px' } } />
					</tr>
				</thead>
				<tbody>
					{ groups.map( ( group, index ) => (
						<tr key={ group.key || `row-${ index }` }>
							<td>
								<TextControl
									value={ group.term }
									onChange={ ( term ) => {
										const nextGroups = groups.map( ( g, i ) =>
											i === index ? { ...g, term } : g
										);
										onChange( { ...taxonomy, groups: nextGroups } );
									} }
									hideLabelFromVision
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</td>
							<td>
								<KeywordChips
									keywords={ group.keywords || [] }
									onChange={ ( keywords ) => {
										const nextGroups = groups.map( ( g, i ) =>
											i === index ? { ...g, keywords } : g
										);
										onChange( { ...taxonomy, groups: nextGroups } );
									} }
									addLabel={ __( 'Add keyword', 'bulk-event-importer' ) }
								/>
							</td>
							<td className="bei-row-actions">
								<Button
									type="button"
									variant="link"
									isDestructive
									onClick={ () =>
										onChange( {
											...taxonomy,
											groups: groups.filter(
												( _, i ) => i !== index
											),
										} )
									}
								>
									{ __( 'Remove', 'bulk-event-importer' ) }
								</Button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>

			<div className="bei-taxonomy-actions">
				<Button
					type="button"
					variant="secondary"
					onClick={ () =>
						onChange( {
							...taxonomy,
							groups: [
								...groups,
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
				<Button
					type="button"
					variant="link"
					isDestructive
					onClick={ onRemove }
				>
					{ __( 'Remove taxonomy', 'bulk-event-importer' ) }
				</Button>
			</div>
		</div>
	);
}

export function TaxonomySection( { taxonomies, onChange } ) {
	return (
		<section className="bei-settings-section bei-settings-taxonomies">
			<h2>{ __( 'Categories & Taxonomies', 'bulk-event-importer' ) }</h2>
			<p className="description">
				{ __(
					'Define the taxonomies this site uses and the keyword groups that auto-assign each term. Structure is entirely per-site; nothing here is hardcoded in the plugin.',
					'bulk-event-importer'
				) }
			</p>

			<VStack spacing={ 6 }>
				{ ( taxonomies || [] ).map( ( tax, index ) => (
					<TaxonomyBlock
						key={ `tax-${ index }-${ tax.slug || 'new' }` }
						taxonomy={ tax }
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

				<Button
					type="button"
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
			</VStack>
		</section>
	);
}
