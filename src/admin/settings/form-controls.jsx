import { useCallback } from '@wordpress/element';
import {
	SelectControl,
	TextareaControl,
	TextControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { KeywordChips } from './keyword-chips';

export function TextFieldEdit( { data, field, onChange, hideLabelFromVision } ) {
	const { id, label, description, placeholder } = field;
	const value = field.getValue( { item: data } ) ?? '';

	const onChangeControl = useCallback(
		( newValue ) => onChange( { [ id ]: newValue } ),
		[ id, onChange ]
	);

	return (
		<TextControl
			label={ hideLabelFromVision ? undefined : label }
			help={ description }
			placeholder={ placeholder }
			value={ value }
			onChange={ onChangeControl }
			__next40pxDefaultSize
			__nextHasNoMarginBottom
		/>
	);
}

export function SelectFieldEdit( { data, field, onChange, hideLabelFromVision } ) {
	const { id, label, description } = field;
	const value = field.getValue( { item: data } ) ?? '';

	const onChangeControl = useCallback(
		( newValue ) => onChange( { [ id ]: newValue } ),
		[ id, onChange ]
	);

	const options = field?.elements ?? [];

	return (
		<SelectControl
			label={ hideLabelFromVision ? undefined : label }
			help={ description }
			value={ value }
			options={ options }
			onChange={ onChangeControl }
			__next40pxDefaultSize
			__nextHasNoMarginBottom
		/>
	);
}

export function IntegerFieldEdit( { data, field, onChange, hideLabelFromVision } ) {
	const { id, label, description } = field;
	const value = field.getValue( { item: data } ) ?? '';

	const onChangeControl = useCallback(
		( newValue ) =>
			onChange( {
				[ id ]: newValue === undefined || newValue === '' ? '' : Number( newValue ),
			} ),
		[ id, onChange ]
	);

	return (
		<div className="bei-number-field">
			<NumberControl
				label={ hideLabelFromVision ? undefined : label }
				help={ description }
				value={ value }
				onChange={ onChangeControl }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</div>
	);
}

export function TextareaFieldEdit( { data, field, onChange, hideLabelFromVision } ) {
	const { id, label, description } = field;
	const value = field.getValue( { item: data } ) ?? '';

	const onChangeControl = useCallback(
		( newValue ) => onChange( { [ id ]: newValue } ),
		[ id, onChange ]
	);

	return (
		<TextareaControl
			label={ hideLabelFromVision ? undefined : label }
			help={ description }
			value={ value }
			onChange={ onChangeControl }
			rows={ 6 }
			__nextHasNoMarginBottom
		/>
	);
}

export function ToggleFieldEdit( { data, field, onChange, hideLabelFromVision } ) {
	const { id, label, description } = field;
	const checked = !! field.getValue( { item: data } );

	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ hideLabelFromVision ? undefined : label }
			help={ description }
			checked={ checked }
			onChange={ ( newChecked ) => onChange( { [ id ]: newChecked } ) }
		/>
	);
}

export function KeywordChipsEdit( { data, field, onChange, hideLabelFromVision } ) {
	const { id, label, description } = field;
	const keywords = field.getValue( { item: data } ) || [];

	return (
		<div className="bei-keyword-field">
			{ ! hideLabelFromVision && label && (
				<label className="components-base-control__label">
					{ label }
				</label>
			) }
			<KeywordChips
				keywords={ keywords }
				onChange={ ( next ) => onChange( { [ id ]: next } ) }
				description={ description }
				addLabel={
					hideLabelFromVision
						? __( 'Add keyword', 'bulk-event-importer' )
						: undefined
				}
			/>
		</div>
	);
}

export function BlockedKeywordsEdit( props ) {
	return <KeywordChipsEdit { ...props } hideLabelFromVision />;
}
