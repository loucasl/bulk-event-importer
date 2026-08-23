import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	ProgressBar,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

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

function ImportStatBlock( { rows } ) {
	return (
		<div className="bei-import-totals">
			{ rows.map( ( [ label, value, hint ] ) => (
				<div
					className="bei-import-totals-stat"
					key={ label }
					title={ hint || undefined }
				>
					<span className="bei-import-totals-label">{ label }</span>
					<span className="bei-import-totals-value">{ value }</span>
				</div>
			) ) }
		</div>
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

function getIssueCopy() {
	return {
		http_403: {
			title: __( 'The calendar host blocked this site', 'bulk-event-importer' ),
			why: __(
				'The feed refused the importer (HTTP 403). This is common when a CDN or calendar only allows browser visits.',
				'bulk-event-importer'
			),
			action: __(
				'Open the URL in a browser. If it loads there, ask the source for a public ICS or RSS link, or put # at the start of that feed line to skip it until it is fixed.',
				'bulk-event-importer'
			),
		},
		http_404: {
			title: __( 'Feed URL was not found', 'bulk-event-importer' ),
			why: __(
				'The address no longer exists or has moved (HTTP 404).',
				'bulk-event-importer'
			),
			action: __(
				'Update or remove that line in Feed URLs.',
				'bulk-event-importer'
			),
		},
		http_401: {
			title: __( 'The feed requires a sign-in', 'bulk-event-importer' ),
			why: __(
				'The host asked for a login (HTTP 401). Private calendar links cannot be imported.',
				'bulk-event-importer'
			),
			action: __(
				'Replace it with a public ICS or RSS URL.',
				'bulk-event-importer'
			),
		},
		http_429: {
			title: __( 'The host asked us to slow down', 'bulk-event-importer' ),
			why: __(
				'Too many requests were sent in a short time (HTTP 429).',
				'bulk-event-importer'
			),
			action: __(
				'Wait and run the import again, or set automatic imports to Once a day.',
				'bulk-event-importer'
			),
		},
		http_5xx: {
			title: __( 'The calendar server had an error', 'bulk-event-importer' ),
			why: __(
				'The remote server returned an error (HTTP 5xx).',
				'bulk-event-importer'
			),
			action: __(
				'Try again later. If it keeps happening, check with the source that their feed is up.',
				'bulk-event-importer'
			),
		},
		http_other: {
			title: __( 'Unexpected response from the feed', 'bulk-event-importer' ),
			why: __(
				'The host returned a status the importer cannot use.',
				'bulk-event-importer'
			),
			action: __(
				'Open the URL and confirm it still publishes a calendar or RSS feed. Update the address if it has changed.',
				'bulk-event-importer'
			),
		},
		parse: {
			title: __( 'The feed could not be read', 'bulk-event-importer' ),
			why: __(
				'The importer expected ICS or RSS but got something else, or the file is malformed.',
				'bulk-event-importer'
			),
			action: __(
				'Confirm Default feed type, or add ics or rss at the end of that feed line. The URL should be a calendar or RSS file, not a normal web page.',
				'bulk-event-importer'
			),
		},
		timeout: {
			title: __( 'The feed took too long to respond', 'bulk-event-importer' ),
			why: __(
				'The request timed out before a complete feed came back.',
				'bulk-event-importer'
			),
			action: __(
				'Try again. If it always times out, the source may be blocking this server or the feed is too large.',
				'bulk-event-importer'
			),
		},
		dns: {
			title: __( 'The hostname could not be found', 'bulk-event-importer' ),
			why: __(
				'This server could not resolve the domain name in the feed URL.',
				'bulk-event-importer'
			),
			action: __(
				'Check the URL for typos. If the address is correct, this server may not be able to reach that domain.',
				'bulk-event-importer'
			),
		},
		ssl: {
			title: __( 'A secure connection could not be made', 'bulk-event-importer' ),
			why: __(
				'HTTPS failed (certificate, TLS, or an outbound SSL block).',
				'bulk-event-importer'
			),
			action: __(
				'Confirm the URL starts with https and the site’s certificate is valid. Hosting firewalls sometimes block outbound HTTPS.',
				'bulk-event-importer'
			),
		},
		empty: {
			title: __( 'The feed returned no content', 'bulk-event-importer' ),
			why: __(
				'The URL responded but the body was empty.',
				'bulk-event-importer'
			),
			action: __(
				'Open the URL. If the page is empty or needs a login, replace it with a public ICS or RSS link.',
				'bulk-event-importer'
			),
		},
		fetch: {
			title: __( 'The feed could not be downloaded', 'bulk-event-importer' ),
			why: __(
				'The importer could not retrieve the calendar after its usual retries.',
				'bulk-event-importer'
			),
			action: __(
				'Open the URL in a browser and compare it with the error below. Update the feed line if the address has changed.',
				'bulk-event-importer'
			),
		},
		event_exception: {
			title: __( 'Some events failed while saving', 'bulk-event-importer' ),
			why: __(
				'The feed downloaded, but one or more events hit an unexpected error.',
				'bulk-event-importer'
			),
			action: __(
				'Check the site’s PHP error log, then run the import again for those feeds.',
				'bulk-event-importer'
			),
		},
	};
}

function getFeedIssueInfo( feed ) {
	const errors = Array.isArray( feed.errors )
		? feed.errors.filter( Boolean )
		: [];
	const debug =
		feed.debug && typeof feed.debug === 'object' ? feed.debug : {};
	const fetchDbg =
		debug.fetch && typeof debug.fetch === 'object' ? debug.fetch : {};
	return {
		source: feed.source || __( 'Unknown', 'bulk-event-importer' ),
		url: feed.url || '',
		errors,
		http: Number( fetchDbg.http_code || 0 ),
		last: String( fetchDbg.last_error || '' ),
		parse: debug.parse_error ? String( debug.parse_error ) : '',
		exceptions: Number( feed.skip_reasons?.event_exception || 0 ),
	};
}

function classifyFeedIssue( info ) {
	const blob = [ info.last, info.parse, ...info.errors ].join( ' ' );
	if ( info.http === 403 || /\b403\b/.test( blob ) ) {
		return 'http_403';
	}
	if ( info.http === 404 || /\b404\b/.test( blob ) ) {
		return 'http_404';
	}
	if ( info.http === 401 || /\b401\b/.test( blob ) ) {
		return 'http_401';
	}
	if ( info.http === 429 || /\b429\b/.test( blob ) ) {
		return 'http_429';
	}
	if ( info.http >= 500 && info.http < 600 ) {
		return 'http_5xx';
	}
	if ( info.http && ( info.http < 200 || info.http >= 300 ) ) {
		return 'http_other';
	}
	if ( info.parse ) {
		return 'parse';
	}
	if ( /timed? out|timeout/i.test( blob ) ) {
		return 'timeout';
	}
	if ( /could not resolve|resolve host|name or service not known|dns/i.test( blob ) ) {
		return 'dns';
	}
	if ( /ssl|certificate|tls|curl error 60|cURL error 35/i.test( blob ) ) {
		return 'ssl';
	}
	if ( /empty response/i.test( blob ) ) {
		return 'empty';
	}
	if ( info.errors.length || info.last ) {
		return 'fetch';
	}
	return null;
}

function collectImportIssues( perFeed ) {
	const copy = getIssueCopy();
	const groups = {};
	const add = ( id, feed ) => {
		if ( ! copy[ id ] ) {
			return;
		}
		if ( ! groups[ id ] ) {
			groups[ id ] = { id, ...copy[ id ], feeds: [] };
		}
		groups[ id ].feeds.push( feed );
	};

	Object.keys( perFeed )
		.map( Number )
		.filter( Number.isFinite )
		.sort( ( a, b ) => a - b )
		.forEach( ( index ) => {
			const feed = perFeed[ index ] || {};
			const info = getFeedIssueInfo( feed );
			const kind = classifyFeedIssue( info );
			if ( kind ) {
				add( kind, {
					source: info.source,
					url: info.url,
					detail:
						info.parse ||
						info.last ||
						info.errors[ 0 ] ||
						( info.http ? `HTTP ${ info.http }` : '' ),
				} );
			}
			if ( info.exceptions > 0 ) {
				add( 'event_exception', {
					source: info.source,
					url: info.url,
					detail: `${ info.exceptions } events`,
				} );
			}
		} );

	return Object.values( groups ).sort(
		( a, b ) => b.feeds.length - a.feeds.length
	);
}

function ImportIssueSummary( { groups } ) {
	if ( ! groups.length ) {
		return null;
	}
	const feedCount = new Set(
		groups.flatMap( ( group ) =>
			group.feeds.map( ( feed ) => feed.url || feed.source )
		)
	).size;
	return (
		<div className="bei-import-issues">
			<strong className="bei-import-issues-title">
				{ feedCount === 1
					? __( '1 feed needs attention', 'bulk-event-importer' )
					: sprintf(
							/* translators: %d: number of feeds with errors */
							__( '%d feeds need attention', 'bulk-event-importer' ),
							feedCount
					  ) }
			</strong>
			{ groups.map( ( group ) => {
				const extra = group.feeds.length - 6;
				return (
					<div className="bei-import-issue" key={ group.id }>
						<div className="bei-import-issue-head">
							{ group.title }
							<span className="bei-import-issue-count">
								{ ' ' }
								({ group.feeds.length })
							</span>
						</div>
						<p className="bei-import-issue-why">{ group.why }</p>
						<p className="bei-import-issue-next">
							<strong>
								{ __( 'Next step:', 'bulk-event-importer' ) }
							</strong>{ ' ' }
							{ group.action }
						</p>
						<ul className="bei-import-issue-feeds">
							{ group.feeds.slice( 0, 6 ).map( ( feed ) => (
								<li key={ `${ feed.source }-${ feed.url }` }>
									<strong>{ feed.source }</strong>
									{ feed.url ? (
										<span className="description">
											{ ' ' }
											{ feed.url }
										</span>
									) : null }
									{ feed.detail ? (
										<div className="description">
											{ feed.detail }
										</div>
									) : null }
								</li>
							) ) }
						</ul>
						{ extra > 0 && (
							<p className="description bei-import-issue-more">
								{ extra === 1
									? __(
											'And 1 more in the feed list below.',
											'bulk-event-importer'
									  )
									: sprintf(
											/* translators: %d: additional feeds not listed */
											__(
												'And %d more in the feed list below.',
												'bulk-event-importer'
											),
											extra
									  ) }
							</p>
						) }
					</div>
				);
			} ) }
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
	const followFeedsRef = useRef( false );
	const feedScrollRef = useRef( null );
	const [ feedTick, setFeedTick ] = useState( 0 );

	const onFeedScroll = useCallback( () => {
		const el = feedScrollRef.current;
		if ( ! el ) {
			return;
		}
		const gap = el.scrollHeight - el.scrollTop - el.clientHeight;
		followFeedsRef.current = gap < 56;
	}, [] );

	useEffect( () => {
		const el = feedScrollRef.current;
		if ( ! el || ! followFeedsRef.current ) {
			return;
		}
		el.scrollTop = el.scrollHeight;
	}, [ feedTick ] );

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
			<div
				className="bei-import-feed-scroll"
				ref={ feedScrollRef }
				onScroll={ onFeedScroll }
			>
				<table className="widefat striped bei-import-feed-table">
				<thead>
					<tr>
						<th>{ __( 'Feed', 'bulk-event-importer' ) }</th>
						<th className="bei-import-num">
							{ __( 'Progress', 'bulk-event-importer' ) }
						</th>
						<th className="bei-import-num">
							{ __( 'Created', 'bulk-event-importer' ) }
						</th>
						<th className="bei-import-num">
							{ __( 'Updated', 'bulk-event-importer' ) }
						</th>
						<th className="bei-import-num">
							{ __( 'Skipped', 'bulk-event-importer' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ keys.map( ( k ) => {
						const feed = perFeed[ k ] || {};
						return (
							<tr key={ k }>
								<td className="bei-import-feed-cell">
									<strong>
										{ __( 'Feed', 'bulk-event-importer' ) }{ ' ' }
										{ k + 1 }: { feed.source || 'Unknown' }
									</strong>
									<span className="description">
										{ feed.url }
									</span>
									<FeedSkipSummary feed={ feed } />
									<FeedDebugDetails feed={ feed } />
								</td>
								<td className="bei-import-num">
									{ Number( feed.done || 0 ) } /{ ' ' }
									{ Number( feed.total || 0 ) }
								</td>
								<td className="bei-import-num">
									{ Number( feed.created || 0 ) }
								</td>
								<td className="bei-import-num">
									{ Number( feed.updated || 0 ) }
								</td>
								<td className="bei-import-num">
									{ Number( feed.skipped || 0 ) }
								</td>
							</tr>
						);
					} ) }
				</tbody>
				</table>
			</div>
		);
	}, [ feedTick, onFeedScroll ] );

	const runImport = useCallback( async () => {
		if ( running ) {
			return;
		}
		setRunning( true );
		cancelRequestedRef.current = false;
		followFeedsRef.current = true;
		setVisible( true );
		setProgress( 0 );
		setFeedTick( 0 );
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
					followFeedsRef.current = false;
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
					setFeedTick( ( n ) => n + 1 );
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
					followFeedsRef.current = false;
					setFeedTick( ( n ) => n + 1 );
					setStatusHtml(
						<>
							<div className="bei-import-heading">
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
							</div>
							<ImportStatBlock
								rows={ [
									[
										__(
											'Feeds processed',
											'bulk-event-importer'
										),
										Object.keys( perFeedRef.current ).length,
									],
									[
										__(
											'Created',
											'bulk-event-importer'
										),
										Number( data.totals?.created || 0 ),
									],
									[
										__(
											'Updated',
											'bulk-event-importer'
										),
										Number( data.totals?.updated || 0 ),
									],
									[
										__(
											'Skipped',
											'bulk-event-importer'
										),
										Number( data.totals?.skipped || 0 ),
									],
									[
										__(
											'Removed',
											'bulk-event-importer'
										),
										Number( data.totals?.deleted || 0 ),
										__(
											'Blocked/filtered events removed',
											'bulk-event-importer'
										),
									],
									[
										__(
											'Trashed',
											'bulk-event-importer'
										),
										Number( data.totals?.old_trashed || 0 ),
										__(
											'Old events moved to trash',
											'bulk-event-importer'
										),
									],
								] }
							/>
							<ImportIssueSummary
								groups={ collectImportIssues(
									perFeedRef.current
								) }
							/>
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
						<div className="bei-import-heading">
							<strong>
								{ __( 'Import started', 'bulk-event-importer' ) }
							</strong>
							<div>
								{ __( 'Feeds:', 'bulk-event-importer' ) }{ ' ' }
								{ feedCount }
							</div>
						</div>
						{ data.totals ? (
							<ImportStatBlock
								rows={ [
									[
										__( 'Created', 'bulk-event-importer' ),
										Number( data.totals.created || 0 ),
									],
									[
										__( 'Updated', 'bulk-event-importer' ),
										Number( data.totals.updated || 0 ),
									],
									[
										__( 'Skipped', 'bulk-event-importer' ),
										Number( data.totals.skipped || 0 ),
									],
									[
										__( 'Deleted', 'bulk-event-importer' ),
										Number( data.totals.deleted || 0 ),
									],
								] }
							/>
						) : null }
					</>
				);

				setTimeout( step, 100 );
			};

			await step();
		} catch ( err ) {
			followFeedsRef.current = false;
			setStatusHtml(
				<p className="bei-import-error">
					<strong>{ __( 'Import failed', 'bulk-event-importer' ) }</strong>
					<br />
					{ escapeHtml( err.message ) }
				</p>
			);
			setRunning( false );
		}
	}, [ running, nonce ] );

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
				<div className="bei-import-progress">
					<div className="bei-import-progress-meter">
						<ProgressBar
							className="bei-import-progress-bar"
							value={ progress }
						/>
						<span className="bei-import-progress-pct">
							{ Math.round( progress ) }%
						</span>
					</div>
					<div className="bei-import-status">{ statusHtml }</div>
					{ renderFeedsTable() }
				</div>
			) }
		</div>
	);
}
