import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { cloneSettings, settingsEqual } from './utils';

export function useSettings() {
	const [ settings, setSettings ] = useState( null );
	const [ saved, setSaved ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	const load = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		try {
			const data = await apiFetch( {
				path: '/bulk-event-importer/v1/settings',
			} );
			setSettings( data );
			setSaved( cloneSettings( data ) );
		} catch ( err ) {
			setError( err?.message || 'Failed to load settings.' );
			setSettings( null );
			setSaved( null );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const isDirty =
		settings && saved ? ! settingsEqual( settings, saved ) : false;

	const updateSettings = useCallback( ( patch ) => {
		setSettings( ( prev ) => ( {
			...prev,
			...( typeof patch === 'function' ? patch( prev ) : patch ),
		} ) );
	}, [] );

	const updateFieldMap = useCallback( ( patch ) => {
		setSettings( ( prev ) => ( {
			...prev,
			field_map: {
				...prev.field_map,
				...( typeof patch === 'function'
					? patch( prev.field_map )
					: patch ),
			},
		} ) );
	}, [] );

	const save = useCallback( async () => {
		if ( ! settings ) {
			return false;
		}
		setIsSaving( true );
		setError( null );
		try {
			const data = await apiFetch( {
				path: '/bulk-event-importer/v1/settings',
				method: 'PUT',
				data: settings,
			} );
			setSettings( data );
			setSaved( cloneSettings( data ) );
			return true;
		} catch ( err ) {
			setError( err?.message || 'Failed to save settings.' );
			return false;
		} finally {
			setIsSaving( false );
		}
	}, [ settings ] );

	const discard = useCallback( () => {
		if ( saved ) {
			setSettings( cloneSettings( saved ) );
		}
	}, [ saved ] );

	return {
		settings,
		isLoading,
		isSaving,
		isDirty,
		error,
		updateSettings,
		updateFieldMap,
		save,
		discard,
		reload: load,
	};
}
