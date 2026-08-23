import { useState } from '@wordpress/element';
import { CheckboxControl } from '@wordpress/components';

export function AdvancedDisclosure( { label, children, className = '' } ) {
	const [ isOpen, setIsOpen ] = useState( false );

	return (
		<div className={ `bei-advanced-disclosure ${ className }`.trim() }>
			<CheckboxControl
				label={ label }
				checked={ isOpen }
				onChange={ setIsOpen }
				__nextHasNoMarginBottom
			/>
			{ isOpen && (
				<div className="bei-advanced-disclosure-body">{ children }</div>
			) }
		</div>
	);
}
