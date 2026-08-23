import { useMemo } from '@wordpress/element';
import {
	Button,
	Notice,
	Spinner,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import {
	FieldMappingSection,
	StaticMetaSection,
} from './field-mapping-section';
import {
	getCoreSettingsFields,
	getFormData,
	getOptionalModuleFields,
	getOptionalModulesFormLayout,
	getSettingsFormLayout,
	mergeFormData,
} from './fields';
import { ImportProgress } from './import-progress';
import { TaxonomySection } from './taxonomy-section';
import { useSettings } from './use-settings';
import { countFeedUrls } from './utils';

function SaveButton( { isDirty, isSaving, onSave } ) {
	return (
		<Button
			variant="primary"
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
	const modulesForm = useMemo( () => getOptionalModulesFormLayout(), [] );
	const formData = useMemo(
		() => getFormData( settings ),
		[ settings ]
	);

	const handleFormChange = ( edits ) => {
		updateSettings( ( prev ) => mergeFormData( prev, edits ) );
	};

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
			<HStack className="bei-settings-title-row" alignment="center">
				<h1>{ __( 'Bulk Event Importer Settings', 'bulk-event-importer' ) }</h1>
				<HStack className="bei-settings-header-actions" spacing={ 2 }>
					<SaveButton
						isDirty={ isDirty }
						isSaving={ isSaving }
						onSave={ save }
					/>
					<ImportProgress nonce={ nonce } />
				</HStack>
			</HStack>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<DataForm
				data={ formData }
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

			<StaticMetaSection
				extraStaticMeta={ settings.field_map?.extra_static_meta || [] }
				onChange={ ( extra_static_meta ) =>
					updateFieldMap( { extra_static_meta } )
				}
			/>

			<section className="bei-settings-section bei-settings-modules">
				<h2>{ __( 'Optional Modules', 'bulk-event-importer' ) }</h2>
				<DataForm
					data={ formData }
					fields={ moduleFields }
					form={ modulesForm }
					onChange={ handleFormChange }
				/>
			</section>

			<div className="bei-settings-save-footer">
				<SaveButton
					isDirty={ isDirty }
					isSaving={ isSaving }
					onSave={ save }
				/>
			</div>
		</div>
	);
}
