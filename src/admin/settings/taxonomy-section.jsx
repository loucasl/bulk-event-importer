import {
	Button,
	Flex,
	FlexBlock,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { AdvancedDisclosure } from './advanced-disclosure';
import { KeywordChips } from './keyword-chips';
import { RemoveButton } from './remove-button';
import { genGroupKey } from './utils';

function TaxonomyBlock( {
	taxonomy,
	onChange,
	onRemove,
	onAddGroup,
	showAddGroup,
	showHeading,
} ) {
	const groups = taxonomy.groups || [];

	return (
		<div className="bei-taxonomy-block">
			{ showHeading && (
				<h3 className="bei-taxonomy-block-title">
					{ taxonomy.label ||
						__( 'New category group', 'bulk-event-importer' ) }
				</h3>
			) }

			<table className="widefat fixed striped bei-group-table">
				<thead>
					<tr>
						<th style={ { width: '22%' } }>
							{ __( 'Category', 'bulk-event-importer' ) }
						</th>
						<th>{ __( 'Keywords', 'bulk-event-importer' ) }</th>
						<th style={ { width: '100px' } } />
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
									addLabel={ __(
										'Add keyword',
										'bulk-event-importer'
									) }
								/>
							</td>
							<td className="bei-row-actions">
								<RemoveButton
									confirmMessage={ __(
										'Remove this category and its keywords?',
										'bulk-event-importer'
									) }
									onConfirm={ () =>
										onChange( {
											...taxonomy,
											groups: groups.filter(
												( _, i ) => i !== index
											),
										} )
									}
								/>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>

			<div className="bei-taxonomy-actions">
				<Button
					type="button"
					variant="secondary"
					className="bei-inline-button"
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
				<div className="bei-taxonomy-actions-end">
					{ showAddGroup && (
						<Button
							type="button"
							variant="secondary"
							className="bei-inline-button"
							onClick={ onAddGroup }
						>
							{ __(
								'Add another category group',
								'bulk-event-importer'
							) }
						</Button>
					) }
					<RemoveButton
						confirmMessage={ __(
							'Remove this category group and all of its categories?',
							'bulk-event-importer'
						) }
						onConfirm={ onRemove }
					>
						{ __( 'Remove this group', 'bulk-event-importer' ) }
					</RemoveButton>
				</div>
			</div>

			{ showAddGroup && (
				<p className="description bei-taxonomy-group-hint">
					{ __(
						'Use “Add another category group” only if events are also tagged another way, such as Audience.',
						'bulk-event-importer'
					) }
				</p>
			) }

			<div className="bei-taxonomy-advanced">
				<TextControl
					label={ __(
						'Default category (optional)',
						'bulk-event-importer'
					) }
					value={ taxonomy.default_term }
					onChange={ ( default_term ) =>
						onChange( { ...taxonomy, default_term } )
					}
					help={ __(
						'Used when no keywords match.',
						'bulk-event-importer'
					) }
					__next40pxDefaultSize
				/>

				<AdvancedDisclosure
					label={ __(
						'Show WordPress taxonomy settings',
						'bulk-event-importer'
					) }
				>
					<Flex gap={ 4 } wrap className="bei-taxonomy-meta">
						<FlexBlock>
							<TextControl
								label={ __(
									'WordPress taxonomy',
									'bulk-event-importer'
								) }
								value={ taxonomy.slug }
								onChange={ ( slug ) =>
									onChange( { ...taxonomy, slug } )
								}
								help={ __(
									'Must match the JetEngine taxonomy slug on this site.',
									'bulk-event-importer'
								) }
								__next40pxDefaultSize
							/>
						</FlexBlock>
						<FlexBlock>
							<TextControl
								label={ __(
									'Name on this page',
									'bulk-event-importer'
								) }
								value={ taxonomy.label }
								onChange={ ( label ) =>
									onChange( { ...taxonomy, label } )
								}
								help={ __(
									'Shown as the heading for this category group.',
									'bulk-event-importer'
								) }
								__next40pxDefaultSize
							/>
						</FlexBlock>
					</Flex>
				</AdvancedDisclosure>
			</div>
		</div>
	);
}

function emptyTaxonomy() {
	return {
		slug: '',
		label: '',
		default_term: '',
		groups: [],
	};
}

export function TaxonomySection( { taxonomies, onChange } ) {
	const list = taxonomies || [];
	const heading =
		list.length === 1 && list[ 0 ].label
			? list[ 0 ].label
			: __( 'Event Categories', 'bulk-event-importer' );

	const addGroup = () => onChange( [ ...list, emptyTaxonomy() ] );

	return (
		<section className="bei-settings-section bei-settings-taxonomies">
			<h2>{ heading }</h2>
			<p className="description">
				{ __(
					'Choose which categories events are sorted into based on keywords found in the event title. Each site sets up its own categories here.',
					'bulk-event-importer'
				) }
			</p>

			<div className="bei-taxonomy-list">
				{ list.map( ( tax, index ) => (
					<TaxonomyBlock
						key={ `tax-${ index }-${ tax.slug || 'new' }` }
						taxonomy={ tax }
						showHeading={ list.length > 1 }
						onChange={ ( updated ) => {
							const next = [ ...list ];
							next[ index ] = updated;
							onChange( next );
						} }
						onRemove={ () => {
							onChange( list.filter( ( _, i ) => i !== index ) );
						} }
						onAddGroup={ addGroup }
						showAddGroup={ index === list.length - 1 }
					/>
				) ) }

				{ list.length === 0 && (
					<>
						<Button
							type="button"
							variant="secondary"
							className="bei-inline-button"
							onClick={ addGroup }
						>
							{ __(
								'Add another category group',
								'bulk-event-importer'
							) }
						</Button>
						<p className="description bei-taxonomy-group-hint">
							{ __(
								'Use “Add another category group” only if events are also tagged another way, such as Audience.',
								'bulk-event-importer'
							) }
						</p>
					</>
				) }
			</div>
		</section>
	);
}
