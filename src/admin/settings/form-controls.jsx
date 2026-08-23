import { useCallback } from '@wordpress/element';
import {
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { KeywordChips } from './keyword-chips';

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
