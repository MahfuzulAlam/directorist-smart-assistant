/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Card, CardBody, SectionHeader, StatusPill } from './Ui';
import { CoinIcon, ChartIcon, SyncIcon, AlertIcon } from './Icons';

export const SECTIONS = [
	{
		key: 'overview',
		title: __( 'Overview', 'directorist-smart-assistant' ),
		icon: <CoinIcon size={ 16 } />,
		intro: __(
			'What this website has spent on the Smart Assistant service in the selected period.',
			'directorist-smart-assistant'
		),
	},
	{
		key: 'services',
		title: __( 'By service', 'directorist-smart-assistant' ),
		icon: <ChartIcon size={ 16 } />,
		intro: __(
			'Where the spend goes, broken down by the upstream provider each call was billed to.',
			'directorist-smart-assistant'
		),
	},
];

const RANGES = [
	{ days: 7, label: __( 'Last 7 days', 'directorist-smart-assistant' ) },
	{ days: 30, label: __( 'Last 30 days', 'directorist-smart-assistant' ) },
	{ days: 90, label: __( 'Last 90 days', 'directorist-smart-assistant' ) },
	{ days: 0, label: __( 'All time', 'directorist-smart-assistant' ) },
];

const MEASURES = [
	{ key: 'cost', label: __( 'Cost', 'directorist-smart-assistant' ) },
	{ key: 'tokens', label: __( 'Tokens', 'directorist-smart-assistant' ) },
	{ key: 'calls', label: __( 'Calls', 'directorist-smart-assistant' ) },
];

const SERVICE_LABELS = {
	openai: 'OpenAI',
	groq: 'Groq',
	pinecone: 'Pinecone',
	anthropic: 'Anthropic',
	vector_api: __( 'Vector API', 'directorist-smart-assistant' ),
};

/**
 * Readable name for a billed service.
 *
 * @param {string} key Raw service key from the API.
 * @return {string}
 */
function serviceLabel( key ) {
	if ( SERVICE_LABELS[ key ] ) {
		return SERVICE_LABELS[ key ];
	}

	return String( key )
		.replace( /[_-]+/g, ' ' )
		.replace( /\b\w/g, ( char ) => char.toUpperCase() );
}

/**
 * Compact count, e.g. 1,284 / 12.9K / 4.2M.
 *
 * @param {number} value Raw count.
 * @return {string}
 */
function compact( value ) {
	const number = Number( value ) || 0;

	if ( number >= 1000000 ) {
		return `${ ( number / 1000000 ).toFixed( 1 ).replace( /\.0$/, '' ) }M`;
	}

	if ( number >= 10000 ) {
		return `${ ( number / 1000 ).toFixed( 1 ).replace( /\.0$/, '' ) }K`;
	}

	return number.toLocaleString();
}

/**
 * Money, kept precise while the amounts are small.
 *
 * @param {number} value Amount in USD.
 * @return {string}
 */
function money( value ) {
	const number = Number( value ) || 0;

	if ( 0 === number ) {
		return '$0.00';
	}

	// Per-call costs land in the millionths; 2 dp would render them all as $0.00.
	if ( number < 0.0001 ) {
		return `$${ number.toFixed( 6 ) }`;
	}

	if ( number < 0.01 ) {
		return `$${ number.toFixed( 4 ) }`;
	}

	if ( number < 1000 ) {
		return `$${ number.toFixed( 2 ) }`;
	}

	return `$${ number.toLocaleString( undefined, { maximumFractionDigits: 0 } ) }`;
}

/**
 * Format one measure of a service row.
 *
 * @param {Object} row     Service row.
 * @param {string} measure Active measure key.
 * @return {string}
 */
function formatMeasure( row, measure ) {
	if ( 'cost' === measure ) {
		return money( row.cost );
	}

	return compact( row[ measure ] );
}

/**
 * Readable period line.
 *
 * @param {Object} data Usage payload.
 * @return {string}
 */
function periodLabel( data ) {
	const start = data.range_start || '';
	const end = data.range_end || '';

	if ( ! start && ! end ) {
		return __( 'All recorded activity', 'directorist-smart-assistant' );
	}

	const format = ( value ) => {
		const date = new Date( value );

		return isNaN( date )
			? value
			: date.toLocaleDateString( undefined, { day: 'numeric', month: 'short', year: 'numeric' } );
	};

	return `${ format( start ) } – ${ format( end ) }`;
}

/**
 * Usage & cost dashboard.
 */
export default function UsageDashboard( { section } ) {
	const [ days, setDays ] = useState( 30 );
	const [ measure, setMeasure ] = useState( 'cost' );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ waking, setWaking ] = useState( false );
	const [ error, setError ] = useState( null );

	const load = useCallback( async ( range, refresh = false, isRetry = false ) => {
		setLoading( true );
		setError( null );

		try {
			const response = await apiFetch( {
				path: `directorist-smart-assistant/v1/usage?days=${ range }${ refresh ? '&refresh=1' : '' }`,
				method: 'GET',
			} );

			setData( response.data );
			setWaking( false );
		} catch ( requestError ) {
			const code = requestError.code || '';

			// A host that spins down when idle loses the first request while it
			// boots. Give it one more go before showing a failure.
			if ( ! isRetry && ( 'usage_timeout' === code || 'usage_request_failed' === code ) ) {
				setWaking( true );
				window.setTimeout( () => load( range, true, true ), 2000 );
				return;
			}

			setWaking( false );
			setError( {
				code,
				message:
					requestError.message ||
					__( 'Could not load usage data.', 'directorist-smart-assistant' ),
			} );
			setData( null );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load( days );
	}, [ days, load ] );

	const current = SECTIONS.find( ( item ) => item.key === section ) || SECTIONS[ 0 ];
	const services = ( data && data.services ) || [];
	const max = services.reduce( ( carry, row ) => Math.max( carry, Number( row[ measure ] ) || 0 ), 0 );

	return (
		<div className="dsa-panel">
			<SectionHeader
				title={ current.title }
				description={ current.intro }
				badge={
					data && data.website_id ? (
						<StatusPill>
							{ sprintf(
								/* translators: %s: shortened website identifier. */
								__( 'Website %s', 'directorist-smart-assistant' ),
								`${ data.website_id.slice( 0, 8 ) }…`
							) }
						</StatusPill>
					) : null
				}
			/>

			{ /* Filters scope everything below them, in one row above the card. */ }
			<div className="dsa-filters">
				<div className="dsa-segmented" role="group" aria-label={ __( 'Date range', 'directorist-smart-assistant' ) }>
					{ RANGES.map( ( range ) => (
						<button
							key={ range.days }
							type="button"
							className={ `dsa-segmented__item${ days === range.days ? ' is-active' : '' }` }
							aria-pressed={ days === range.days }
							onClick={ () => setDays( range.days ) }
						>
							{ range.label }
						</button>
					) ) }
				</div>

				<Button
					className="dsa-button dsa-button--ghost dsa-filters__refresh"
					variant="secondary"
					onClick={ () => load( days, true ) }
					disabled={ loading || waking }
				>
					<SyncIcon size={ 15 } />
					<span>{ __( 'Refresh', 'directorist-smart-assistant' ) }</span>
				</Button>
			</div>

			<Card>
				<CardBody>

					{ ( loading || waking ) && (
						<div className="dsa-usage__loading">
							<span className="dsa-spinner" aria-hidden="true" />
							{ waking
								? __( 'Waking the usage service…', 'directorist-smart-assistant' )
								: __( 'Loading usage…', 'directorist-smart-assistant' ) }
						</div>
					) }

					{ ! loading && ! waking && error && (
						<div className="dsa-usage__empty">
							<span className="dsa-usage__empty-icon">
								<AlertIcon size={ 20 } />
							</span>
							<p className="dsa-usage__empty-title">
								{ 'usage_endpoint_missing' === error.code
									? __( 'No usage endpoint on this service yet', 'directorist-smart-assistant' )
									: 'usage_timeout' === error.code
									? __( 'The service is still waking up', 'directorist-smart-assistant' )
									: __( 'Usage is unavailable', 'directorist-smart-assistant' ) }
							</p>
							<p className="dsa-usage__empty-text">{ error.message }</p>
							{ 'usage_endpoint_missing' === error.code && (
								<code className="dsa-usage__endpoint">GET /api/v1/usage/summary</code>
							) }
							{ 'usage_endpoint_missing' !== error.code && (
								<Button
									className="dsa-button dsa-button--ghost dsa-usage__retry"
									variant="secondary"
									onClick={ () => load( days, true ) }
								>
									<SyncIcon size={ 15 } />
									<span>{ __( 'Try again', 'directorist-smart-assistant' ) }</span>
								</Button>
							) }
						</div>
					) }

					{ ! loading && ! waking && ! error && data && 'overview' === section && (
						<>
							<div className="dsa-hero">
								<p className="dsa-hero__label">
									{ __( 'Total cost', 'directorist-smart-assistant' ) }
								</p>
								<p className="dsa-hero__value">{ money( data.total_cost_usd ) }</p>
								<p className="dsa-hero__meta">
									{ periodLabel( data ) }
									{ data.cached && (
										<span className="dsa-hero__cached">
											{ __( '· cached', 'directorist-smart-assistant' ) }
										</span>
									) }
								</p>
							</div>

							<div className="dsa-tiles">
								<div className="dsa-tile">
									<p className="dsa-tile__label">
										{ __( 'API calls', 'directorist-smart-assistant' ) }
									</p>
									<p className="dsa-tile__value">{ compact( data.total_calls ) }</p>
								</div>
								<div className="dsa-tile">
									<p className="dsa-tile__label">
										{ __( 'Tokens used', 'directorist-smart-assistant' ) }
									</p>
									<p className="dsa-tile__value">{ compact( data.total_tokens ) }</p>
								</div>
								<div className="dsa-tile">
									<p className="dsa-tile__label">
										{ __( 'Cost per call', 'directorist-smart-assistant' ) }
									</p>
									<p className="dsa-tile__value">
										{ data.total_calls
											? money( data.total_cost_usd / data.total_calls )
											: '—' }
									</p>
								</div>
							</div>

							{ ! data.total_calls && (
								<p className="dsa-usage__note">
									{ __(
										'No calls recorded in this period. Try a wider range.',
										'directorist-smart-assistant'
									) }
								</p>
							) }
						</>
					) }

					{ ! loading && ! waking && ! error && data && 'services' === section && (
						<>
							<div className="dsa-measures" role="group" aria-label={ __( 'Measure', 'directorist-smart-assistant' ) }>
								{ MEASURES.map( ( item ) => (
									<button
										key={ item.key }
										type="button"
										className={ `dsa-measures__item${
											measure === item.key ? ' is-active' : ''
										}` }
										aria-pressed={ measure === item.key }
										onClick={ () => setMeasure( item.key ) }
									>
										{ item.label }
									</button>
								) ) }
							</div>

							{ services.length ? (
								<ul className="dsa-bars">
									{ services.map( ( row ) => {
										const value = Number( row[ measure ] ) || 0;
										const width = max > 0 ? Math.max( ( value / max ) * 100, value > 0 ? 1.5 : 0 ) : 0;

										return (
											<li key={ row.service } className="dsa-bars__row" tabIndex={ 0 }>
												<span className="dsa-bars__name">
													{ serviceLabel( row.service ) }
												</span>
												<span className="dsa-bars__track">
													<span
														className="dsa-bars__fill"
														style={ { width: `${ width }%` } }
													/>
												</span>
												<span className="dsa-bars__value">
													{ formatMeasure( row, measure ) }
												</span>

												<span className="dsa-bars__tip" role="tooltip">
													<strong>{ serviceLabel( row.service ) }</strong>
													<span>
														{ sprintf(
															/* translators: %s: number of calls. */
															__( '%s calls', 'directorist-smart-assistant' ),
															compact( row.calls )
														) }
													</span>
													<span>
														{ sprintf(
															/* translators: %s: number of tokens. */
															__( '%s tokens', 'directorist-smart-assistant' ),
															compact( row.tokens )
														) }
													</span>
													<span>{ money( row.cost ) }</span>
												</span>
											</li>
										);
									} ) }
								</ul>
							) : (
								<p className="dsa-usage__note">
									{ __(
										'Nothing recorded for this period yet.',
										'directorist-smart-assistant'
									) }
								</p>
							) }
						</>
					) }
				</CardBody>
			</Card>
		</div>
	);
}
