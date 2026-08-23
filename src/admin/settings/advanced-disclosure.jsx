import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';

export function AdvancedDisclosure( { label, children, className = '' } ) {
	const [ isOpen, setIsOpen ] = useState( false );

	return (
		<div className={ `bei-advanced-disclosure ${ className }`.trim() }>
			<Button
				type="button"
				variant="link"
				onClick={ () => setIsOpen( ( open ) => ! open ) }
				aria-expanded={ isOpen }
			>
				{ label }
			</Button>
			{ isOpen && (
				<div className="bei-advanced-disclosure-body">{ children }</div>
			) }
		</div>
	);
}
