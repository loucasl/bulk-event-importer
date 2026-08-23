import { createRoot } from '@wordpress/element';
import { SettingsPage } from './settings-page';
import './style.scss';

const rootEl = document.getElementById( 'bei-settings-root' );
if ( rootEl ) {
	const { nonce } = window.bulkEventImporter || {};
	createRoot( rootEl ).render( <SettingsPage nonce={ nonce } /> );
}
