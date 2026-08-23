import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	Panel,
	ProgressBar,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function escapeHtml( str ) {
	return String( str || '' )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#039;' );
}

export function ImportProgress( { nonce } ) {
	const [ running, setRunning ] = useState( false );
	const cancelRequestedRef = useRef( false );
	const [ progress, setProgress ] = useState( 0 );
	const [ statusHtml, setStatusHtml ] = useState( '' );
	const [ visible, setVisible ] = useState( false );
	const jobIdRef = useRef( '' );
	const perFeedRef = useRef( {} );

	const renderTotals = useCallback( ( totals ) => {
		if ( ! totals ) {
			return '';
		}
		return (
			<>
				<hr />
				<strong>{ __( 'Totals so far', 'bulk-event-importer' ) }</strong>
				<br />
				{ __( 'Created:', 'bulk-event-importer' ) }{ ' ' }
				{ Number( totals.created || 0 ) }
				<br />
				{ __( 'Updated:', 'bulk-event-importer' ) }{ ' ' }
				{ Number( totals.updated || 0 ) }
				<br />
				{ __( 'Skipped:', 'bulk-event-importer' ) }{ ' ' }
				{ Number( totals.skipped || 0 ) }
				<br />
				{ __( 'Deleted:', 'bulk-event-importer' ) }{ ' ' }
				{ Number( totals.deleted || 0 ) }
			</>
		);
	}, [] );

	const renderFeedsTable = useCallback( () => {
		const perFeed = perFeedRef.current;
		const keys = Object.keys( perFeed )
			.map( Number )
			.filter( Number.isFinite )
			.sort( ( a, b ) => a - b );

		if ( ! keys.length ) {
			return null;
		}

		return (
			<table className="widefat striped bei-import-feed-table">
				<thead>
					<tr>
						<th>{ __( 'Feed', 'bulk-event-importer' ) }</th>
						<th>{ __( 'Progress', 'bulk-event-importer' ) }</th>
						<th>{ __( 'Created', 'bulk-event-importer' ) }</th>
						<th>{ __( 'Updated', 'bulk-event-importer' ) }</th>
						<th>{ __( 'Skipped', 'bulk-event-importer' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ keys.map( ( k ) => {
						const feed = perFeed[ k ] || {};
						return (
							<tr key={ k }>
								<td>
									<strong>
										{ __( 'Feed', 'bulk-event-importer' ) }{ ' ' }
										{ k + 1 }: { feed.source || 'Unknown' }
									</strong>
									<br />
									<span className="description">{ feed.url }</span>
								</td>
								<td>
									{ Number( feed.done || 0 ) } /{ ' ' }
									{ Number( feed.total || 0 ) }
								</td>
								<td>{ Number( feed.created || 0 ) }</td>
								<td>{ Number( feed.updated || 0 ) }</td>
								<td>{ Number( feed.skipped || 0 ) }</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		);
	}, [] );

	const runImport = useCallback( async () => {
		if ( running ) {
			return;
		}
		setRunning( true );
		cancelRequestedRef.current = false;
		setVisible( true );
		setProgress( 0 );
		setStatusHtml(
			<p>{ __( 'Starting import…', 'bulk-event-importer' ) }</p>
		);
		perFeedRef.current = {};
		jobIdRef.current = '';

		try {
			const startParams = new URLSearchParams();
			startParams.append( 'action', 'bulk_event_ajax_import_start' );
			startParams.append( '_ajax_nonce', nonce );

			const startRes = await fetch( window.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type':
						'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: startParams.toString(),
			} );
			const start = await startRes.json();
			if ( ! start.success ) {
				throw new Error( start.data?.message || 'Start failed' );
			}

			const jobId = start.data.job_id;
			jobIdRef.current = jobId;
			const feedCount = Number( start.data.feed_count || 0 );

			const step = async () => {
				if ( cancelRequestedRef.current ) {
					setStatusHtml(
						<p>
							<strong>
								{ __( 'Import cancelled.', 'bulk-event-importer' ) }
							</strong>
						</p>
					);
					setRunning( false );
					return;
				}

				const stepParams = new URLSearchParams();
				stepParams.append( 'action', 'bulk_event_ajax_import_step' );
				stepParams.append( '_ajax_nonce', nonce );
				stepParams.append( 'job_id', jobId );

				const stepRes = await fetch( window.ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type':
							'application/x-www-form-urlencoded; charset=UTF-8',
					},
					body: stepParams.toString(),
				} );
				const res = await stepRes.json();
				if ( ! res.success ) {
					throw new Error( res.data?.message || 'Step failed' );
				}

				const data = res.data || {};
				if ( typeof data.feed_i !== 'undefined' && data.feed ) {
					perFeedRef.current[ data.feed_i ] = data.feed;
				}

				if ( data.done ) {
					if ( data.per_feed ) {
						if ( Array.isArray( data.per_feed ) ) {
							data.per_feed.forEach( ( f, idx ) => {
								if ( f ) {
									perFeedRef.current[ idx ] = f;
								}
							} );
						}
					}
					setProgress( 100 );
					setStatusHtml(
						<>
							<p>
								<strong>
									{ data.cancelled
										? __(
												'Import cancelled',
												'bulk-event-importer'
										  )
										: __(
												'Import complete!',
												'bulk-event-importer'
										  ) }
								</strong>
							</p>
							{ renderFeedsTable() }
							<p>
								{ __( 'Events created:', 'bulk-event-importer' ) }{ ' ' }
								{ Number( data.totals?.created || 0 ) }
								<br />
								{ __( 'Events updated:', 'bulk-event-importer' ) }{ ' ' }
								{ Number( data.totals?.updated || 0 ) }
								<br />
								{ __( 'Events skipped:', 'bulk-event-importer' ) }{ ' ' }
								{ Number( data.totals?.skipped || 0 ) }
							</p>
						</>
					);
					setRunning( false );
					return;
				}

				const currentFeed = data.feed || {};
				const i = Number( data.feed_i || 0 );
				const total = Number( currentFeed.total || 0 );
				const done = Number( currentFeed.done || 0 );
				const safeFeedCount = feedCount > 0 ? feedCount : 1;
				const within =
					total > 0 ? Math.min( 1, Math.max( 0, done / total ) ) : 1;
				const pct = Math.max(
					0,
					Math.min( 100, ( ( i + within ) / safeFeedCount ) * 100 )
				);
				setProgress( pct );
				setStatusHtml(
					<>
						<p>
							<strong>
								{ __( 'Import started', 'bulk-event-importer' ) }
							</strong>
							<br />
							{ __( 'Feeds:', 'bulk-event-importer' ) } { feedCount }
						</p>
						{ renderFeedsTable() }
						{ renderTotals( data.totals ) }
					</>
				);

				setTimeout( step, 100 );
			};

			await step();
		} catch ( err ) {
			setStatusHtml(
				<p className="bei-import-error">
					<strong>{ __( 'Import failed', 'bulk-event-importer' ) }</strong>
					<br />
					{ escapeHtml( err.message ) }
				</p>
			);
			setRunning( false );
		}
	}, [ running, nonce, renderFeedsTable, renderTotals ] );

	const requestCancel = useCallback( async () => {
		if ( ! running || cancelRequestedRef.current ) {
			return;
		}
		cancelRequestedRef.current = true;
		if ( ! jobIdRef.current ) {
			return;
		}
		const cancelParams = new URLSearchParams();
		cancelParams.append( 'action', 'bulk_event_ajax_import_cancel' );
		cancelParams.append( '_ajax_nonce', nonce );
		cancelParams.append( 'job_id', jobIdRef.current );
		await fetch( window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type':
					'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: cancelParams.toString(),
		} );
	}, [ running, nonce ] );

	useEffect( () => {
		if ( window.location.hash === '#import-progress' ) {
			runImport();
		}
	}, [ runImport ] );

	return (
		<div className="bei-settings-header">
			<div className="bei-settings-header-actions">
				{ running && (
					<Button variant="secondary" onClick={ requestCancel }>
						{ __( 'Cancel Import', 'bulk-event-importer' ) }
					</Button>
				) }
				<Button
					variant="primary"
					onClick={ runImport }
					disabled={ running }
				>
					{ running
						? __( 'Importing…', 'bulk-event-importer' )
						: __( 'Run Import Now', 'bulk-event-importer' ) }
				</Button>
			</div>
			{ visible && (
				<Panel className="bei-import-progress">
					<ProgressBar value={ progress } />
					<div className="bei-import-status">{ statusHtml }</div>
				</Panel>
			) }
		</div>
	);
}
