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
	getFormData,
	getSettingsFields,
	getSettingsFormLayout,
	mergeFormData,
} from './fields';
import { ImportProgress } from './import-progress';
import { TaxonomySection } from './taxonomy-section';
import { useSettings } from './use-settings';

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
		discard,
		reload,
	} = useSettings();

	const fields = useMemo( () => getSettingsFields(), [] );
	const form = useMemo( () => getSettingsFormLayout(), [] );
	const formData = useMemo(
		() => getFormData( settings ),
		[ settings ]
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
			<HStack className="bei-settings-title-row" alignment="center">
				<h1>{ __( 'Bulk Event Importer Settings', 'bulk-event-importer' ) }</h1>
				<ImportProgress nonce={ nonce } />
			</HStack>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<DataForm
				data={ formData }
				fields={ fields }
				form={ form }
				onChange={ ( edits ) => {
					updateSettings( ( prev ) =>
						mergeFormData( prev, edits )
					);
				} }
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

			<div className="bei-settings-savebar">
				<span className="bei-settings-savebar-state">
					{ isDirty
						? __( 'Unsaved changes', 'bulk-event-importer' )
						: __( 'All changes saved', 'bulk-event-importer' ) }
				</span>
				<HStack spacing={ 2 }>
					<Button
						variant="secondary"
						onClick={ discard }
						disabled={ ! isDirty || isSaving }
					>
						{ __( 'Discard changes', 'bulk-event-importer' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ save }
						disabled={ ! isDirty || isSaving }
						isBusy={ isSaving }
					>
						{ __( 'Save Settings', 'bulk-event-importer' ) }
					</Button>
				</HStack>
			</div>
		</div>
	);
}
