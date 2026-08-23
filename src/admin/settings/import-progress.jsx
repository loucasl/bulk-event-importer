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

const SKIP_REASON_LABELS = {
	blocked_keyword_match: __( 'Blocked keywords', 'bulk-event-importer' ),
	allowlist_no_match: __( 'No allowlist match', 'bulk-event-importer' ),
	missing_start_date: __( 'Missing start date', 'bulk-event-importer' ),
	invalid_start_date: __( 'Invalid start date', 'bulk-event-importer' ),
	date_out_of_import_window: __( 'Out of import window', 'bulk-event-importer' ),
	event_exception: __( 'Unexpected error', 'bulk-event-importer' ),
};

function getTopBlockedKeywords( blockedCounts, max ) {
	return Object.entries( blockedCounts || {} )
		.filter( ( [ keyword, count ] ) => keyword && Number( count ) > 0 )
		.sort( ( a, b ) => Number( b[ 1 ] ) - Number( a[ 1 ] ) )
		.slice( 0, max )
		.map( ( [ keyword, count ] ) => `${ keyword } (${ Number( count ) })` );
}

function getTopReasons( skipReasons, max ) {
	return Object.entries( skipReasons || {} )
		.filter( ( [ , count ] ) => count && Number( count ) > 0 )
		.sort( ( a, b ) => Number( b[ 1 ] ) - Number( a[ 1 ] ) )
		.slice( 0, max )
		.map(
			( [ reason, count ] ) =>
				`${ SKIP_REASON_LABELS[ reason ] || reason }: ${ Number( count ) }`
		);
}

function FeedSkipSummary( { feed } ) {
	const skipReasons = feed.skip_reasons || {};
	const blockedCounts = feed.blocked_keyword_counts || {};
	const blockedTotal = Number( skipReasons.blocked_keyword_match || 0 );

	if ( ! Object.keys( skipReasons ).length ) {
		return null;
	}

	if ( blockedTotal > 0 && Object.keys( blockedCounts ).length ) {
		const blockedList = getTopBlockedKeywords( blockedCounts, 9999 );
		return (
			<div className="bei-skip-callout">
				<div className="bei-skip-title">
					{ blockedTotal }{ ' ' }
					{ __(
						'events skipped due to',
						'bulk-event-importer'
					) }{ ' ' }
					{ Object.keys( blockedCounts ).length }{ ' ' }
					{ __(
						'blocked keywords:',
						'bulk-event-importer'
					) }
				</div>
				<div className="bei-skip-keywords">
					{ blockedList.length ? blockedList.join( ', ' ) : '—' }
				</div>
			</div>
		);
	}

	const topReasons = getTopReasons( skipReasons, 3 );
	if ( ! topReasons.length ) {
		return null;
	}

	return (
		<div className="bei-skip-callout">
			<div className="bei-skip-title">
				{ __( 'Events skipped (breakdown):', 'bulk-event-importer' ) }
			</div>
			<div className="bei-skip-keywords">{ topReasons.join( ', ' ) }</div>
		</div>
	);
}

function FeedDebugDetails( { feed } ) {
	const errors = Array.isArray( feed.errors )
		? feed.errors.filter( Boolean )
		: [];
	const errCount = errors.length;
	const debugObj =
		feed.debug && typeof feed.debug === 'object' ? feed.debug : null;

	if ( ! errCount && ! debugObj ) {
		return null;
	}

	const fetchDbg =
		debugObj?.fetch && typeof debugObj.fetch === 'object'
			? debugObj.fetch
			: null;
	const parseErr = debugObj?.parse_error ? String( debugObj.parse_error ) : '';

	let summaryLine = '';
	if ( fetchDbg ) {
		const attempts = Number( fetchDbg.attempts_total || 0 );
		const ms = Number( fetchDbg.timing_ms || 0 );
		const last = String( fetchDbg.last_error || '' );
		const code = Number( fetchDbg.http_code || 0 );
		summaryLine =
			'Fetch: ' +
			( attempts
				? `${ attempts } attempt${ attempts === 1 ? '' : 's' }`
				: '—' ) +
			( ms ? `, ${ ms }ms` : '' ) +
			( code ? `, HTTP ${ code }` : '' ) +
			( last ? `, last: ${ last }` : '' );
	} else if ( parseErr ) {
		summaryLine = `Parse: ${ parseErr }`;
	}

	const detailsPayload = {};
	if ( fetchDbg ) {
		detailsPayload.fetch = fetchDbg;
	}
	if ( parseErr ) {
		detailsPayload.parse_error = parseErr;
	}

	return (
		<>
			{ summaryLine && (
				<div className="bei-import-debug-summary">
					<strong>{ __( 'Debug:', 'bulk-event-importer' ) }</strong>{ ' ' }
					{ summaryLine }
				</div>
			) }
			{ Object.keys( detailsPayload ).length > 0 && (
				<details className="bei-import-debug-details">
					<summary>
						{ __( 'Debug details', 'bulk-event-importer' ) }
					</summary>
					<pre>{ JSON.stringify( detailsPayload, null, 2 ) }</pre>
				</details>
			) }
			{ errCount > 0 && (
				<details className="bei-import-error-details">
					<summary>
						{ __( 'Errors', 'bulk-event-importer' ) } ({ errCount })
					</summary>
					<ul>
						{ errors.slice( -6 ).map( ( message, index ) => (
							<li key={ index }>{ message }</li>
						) ) }
					</ul>
				</details>
			) }
		</>
	);
}

export function ImportProgress( { nonce, toolbar = null } ) {
	const [ running, setRunning ] = useState( false );
	const cancelRequestedRef = useRef( false );
	const autoStartedRef = useRef( false );
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
									<FeedSkipSummary feed={ feed } />
									<FeedDebugDetails feed={ feed } />
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
								{ __( 'Feeds processed:', 'bulk-event-importer' ) }{ ' ' }
								{ Object.keys( perFeedRef.current ).length }
								<br />
								{ __( 'Events created:', 'bulk-event-importer' ) }{ ' ' }
								{ Number( data.totals?.created || 0 ) }
								<br />
								{ __( 'Events updated:', 'bulk-event-importer' ) }{ ' ' }
								{ Number( data.totals?.updated || 0 ) }
								<br />
								{ __( 'Events skipped:', 'bulk-event-importer' ) }{ ' ' }
								{ Number( data.totals?.skipped || 0 ) }
								<br />
								{ __(
									'Blocked/filtered events removed:',
									'bulk-event-importer'
								) }{ ' ' }
								{ Number( data.totals?.deleted || 0 ) }
								<br />
								{ __(
									'Old events moved to trash:',
									'bulk-event-importer'
								) }{ ' ' }
								{ Number( data.totals?.old_trashed || 0 ) }
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

	const runImportRef = useRef( runImport );
	runImportRef.current = runImport;

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
		if (
			window.location.hash === '#import-progress' &&
			! autoStartedRef.current
		) {
			autoStartedRef.current = true;
			runImportRef.current();
		}
	}, [] );

	return (
		<div className="bei-import-progress-root">
			<div className="bei-settings-toolbar">
				{ toolbar }
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
