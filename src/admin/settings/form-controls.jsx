import { useCallback, useState } from '@wordpress/element';
import {
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
	const [ input, setInput ] = useState( '' );

	const addKeyword = useCallback(
		( raw ) => {
			const keyword = String( raw || '' ).trim();
			if ( ! keyword ) {
				return;
			}
			const lower = keywords.map( ( k ) => k.toLowerCase() );
			if ( lower.includes( keyword.toLowerCase() ) ) {
				return;
			}
			onChange( { [ id ]: [ ...keywords, keyword ] } );
		},
		[ id, keywords, onChange ]
	);

	const removeKeyword = useCallback(
		( index ) => {
			onChange( {
				[ id ]: keywords.filter( ( _, i ) => i !== index ),
			} );
		},
		[ id, keywords, onChange ]
	);

	return (
		<div className="bei-keyword-chips">
			{ ! hideLabelFromVision && label && (
				<label className="components-base-control__label">
					{ label }
				</label>
			) }
			{ description && (
				<p className="components-base-control__help">{ description }</p>
			) }
			<div className="bei-chips">
				{ keywords.map( ( keyword, index ) => (
					<span key={ `${ keyword }-${ index }` } className="bei-keyword-chip">
						{ keyword }
						<button
							type="button"
							className="bei-remove-chip"
							onClick={ () => removeKeyword( index ) }
							aria-label={ __( 'Remove keyword', 'bulk-event-importer' ) }
						>
							&times;
						</button>
					</span>
				) ) }
			</div>
			<TextControl
				label={
					hideLabelFromVision
						? undefined
						: __( 'Add keyword', 'bulk-event-importer' )
				}
				value={ input }
				onChange={ setInput }
				onKeyDown={ ( event ) => {
					if ( event.key === 'Enter' || event.key === ',' ) {
						event.preventDefault();
						addKeyword( input );
						setInput( '' );
					}
				} }
				placeholder={ __( 'Type and press Enter', 'bulk-event-importer' ) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</div>
	);
}
