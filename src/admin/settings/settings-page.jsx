import { useMemo } from '@wordpress/element';
import {
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { FieldMappingSection, StaticMetaSection } from './field-mapping-section';
import { GeocodingAdvancedFields } from './geocoding-fields';
import {
	getCoreSettingsFields,
	getOptionalModuleFields,
	getGeocodingModulesFormLayout,
	getSettingsFormLayout,
	getStaticMetaFormLayout,
	mergeFormData,
} from './fields';
import { ImportProgress } from './import-progress';
import { TaxonomySection } from './taxonomy-section';
import { useSettings } from './use-settings';
import { countFeedUrls } from './utils';

function SaveButton( { isDirty, isSaving, onSave } ) {
	return (
		<Button
			variant="secondary"
			onClick={ onSave }
			disabled={ ! isDirty || isSaving }
			isBusy={ isSaving }
		>
			{ __( 'Save Settings', 'bulk-event-importer' ) }
		</Button>
	);
}

export function SettingsPage( { nonce } ) {
	const {
		settings,
		isLoading,
		isSaving,
		isDirty,
		error,
		updateSettings,
		updateFieldMap,
		save,
		reload,
	} = useSettings();

	const coreFields = useMemo( () => getCoreSettingsFields(), [] );
	const moduleFields = useMemo( () => getOptionalModuleFields(), [] );
	const feedUrlCount = useMemo(
		() => countFeedUrls( settings?.feed_urls ),
		[ settings?.feed_urls ]
	);
	const coreForm = useMemo(
		() => getSettingsFormLayout( feedUrlCount ),
		[ feedUrlCount ]
	);
	const geocodingForm = useMemo( () => getGeocodingModulesFormLayout(), [] );
	const staticMetaForm = useMemo( () => getStaticMetaFormLayout(), [] );

	const handleFormChange = ( edits ) => {
		updateSettings( ( prev ) => mergeFormData( prev, edits ) );
	};

	const saveButton = (
		<SaveButton
			isDirty={ isDirty }
			isSaving={ isSaving }
			onSave={ save }
		/>
	);

	if ( isLoading ) {
		return (
			<div className="bei-settings-loading">
				<Spinner />
			</div>
		);
	}

	if ( ! settings ) {
		return (
			<div className="bei-settings-app">
				<h1>{ __( 'Bulk Event Importer Settings', 'bulk-event-importer' ) }</h1>
				<Notice status="error" isDismissible={ false }>
					{ error ||
						__(
							'Failed to load settings. Your saved configuration was not changed.',
							'bulk-event-importer'
						) }
				</Notice>
				<Button variant="secondary" onClick={ reload }>
					{ __( 'Retry', 'bulk-event-importer' ) }
				</Button>
			</div>
		);
	}

	return (
		<div className="bei-settings-app">
			<h1>{ __( 'Bulk Event Importer Settings', 'bulk-event-importer' ) }</h1>

			<ImportProgress nonce={ nonce } toolbar={ saveButton } />

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<DataForm
				data={ settings }
				fields={ coreFields }
				form={ coreForm }
				onChange={ handleFormChange }
			/>

			<TaxonomySection
				taxonomies={ settings.taxonomies }
				onChange={ ( taxonomies ) => updateSettings( { taxonomies } ) }
			/>

			<FieldMappingSection
				fieldMap={ settings.field_map }
				onChange={ ( field_map ) => updateFieldMap( field_map ) }
			/>

			<section className="bei-settings-section bei-settings-modules">
				<h2>{ __( 'Optional Modules', 'bulk-event-importer' ) }</h2>
				<p className="description">
					{ __(
						'Turn on extra features only when your site needs them.',
						'bulk-event-importer'
					) }
				</p>
				<DataForm
					data={ settings }
					fields={ moduleFields }
					form={ geocodingForm }
					onChange={ handleFormChange }
				/>
				{ settings.geocoding_enabled && (
					<GeocodingAdvancedFields
						settings={ settings }
						onChange={ updateSettings }
					/>
				) }
				<DataForm
					data={ settings }
					fields={ moduleFields }
					form={ staticMetaForm }
					onChange={ handleFormChange }
				/>
				{ settings.static_meta_enabled && (
					<StaticMetaSection
						extraStaticMeta={ settings.field_map?.extra_static_meta || [] }
						onChange={ ( extra_static_meta ) =>
							updateFieldMap( { extra_static_meta } )
						}
					/>
				) }
			</section>

			<div className="bei-settings-save-footer">
				{ saveButton }
			</div>
		</div>
	);
}
