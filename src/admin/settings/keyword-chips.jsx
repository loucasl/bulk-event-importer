import { useCallback, useState } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function KeywordChips( {
	keywords = [],
	onChange,
	description,
	addLabel,
} ) {
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
			onChange( [ ...keywords, keyword ] );
		},
		[ keywords, onChange ]
	);

	const removeKeyword = useCallback(
		( index ) => {
			onChange( keywords.filter( ( _, i ) => i !== index ) );
		},
		[ keywords, onChange ]
	);

	const commitKeyword = useCallback(
		( event ) => {
			event.preventDefault();
			event.stopPropagation();
			if ( event.nativeEvent ) {
				event.nativeEvent.stopImmediatePropagation?.();
			}
			addKeyword( input );
			setInput( '' );
		},
		[ addKeyword, input ]
	);

	return (
		<div
			className="bei-keyword-chips"
			onKeyDownCapture={ ( event ) => {
				if ( event.key === 'Enter' ) {
					commitKeyword( event );
				}
			} }
		>
			{ description && (
				<p className="description">{ description }</p>
			) }
			<div className="bei-chips">
				{ keywords.map( ( keyword, index ) => (
					<span
						key={ `${ keyword }-${ index }` }
						className="bei-keyword-chip"
					>
						{ keyword }
						<button
							type="button"
							className="bei-remove-chip"
							onClick={ () => removeKeyword( index ) }
							aria-label={ __(
								'Remove keyword',
								'bulk-event-importer'
							) }
						>
							&times;
						</button>
					</span>
				) ) }
			</div>
			<TextControl
				label={
					addLabel || __( 'Add keyword', 'bulk-event-importer' )
				}
				value={ input }
				onChange={ setInput }
				onKeyDown={ ( event ) => {
					if ( event.key === ',' ) {
						commitKeyword( event );
					}
				} }
				placeholder={ __(
					'Type and press Enter',
					'bulk-event-importer'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</div>
	);
}
