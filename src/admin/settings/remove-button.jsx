import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function confirmRemoval( message ) {
	return window.confirm( message );
}

export function RemoveButton( { onConfirm, children, confirmMessage, ...props } ) {
	return (
		<Button
			type="button"
			variant="secondary"
			isDestructive
			onClick={ () => {
				if ( confirmRemoval( confirmMessage ) ) {
					onConfirm();
				}
			} }
			{ ...props }
		>
			{ children || __( 'Remove', 'bulk-event-importer' ) }
		</Button>
	);
}
