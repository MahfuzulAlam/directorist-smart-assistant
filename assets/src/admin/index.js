/**
 * WordPress dependencies
 */
import { createRoot, useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Styles
 */
import './index.css';

/**
 * Internal dependencies
 */
import ChatAgentSetup, { SECTIONS as AGENT_SECTIONS } from './components/ChatAgentSetup';
import VectorStorageSetup, { SECTIONS as VECTOR_SECTIONS } from './components/VectorStorageSetup';
import ChatModuleSettings, { SECTIONS as WIDGET_SECTIONS } from './components/ChatModuleSettings';
import UsageDashboard, { SECTIONS as USAGE_SECTIONS } from './components/UsageDashboard';
import { SparkleIcon, DatabaseIcon, ChatIcon, CheckIcon, AlertIcon, BoltIcon, CoinIcon } from './components/Icons';

/**
 * The settings map. One group per area, one leaf per section — the sidebar
 * renders this tree, and the URL hash (#group/section) mirrors it so a
 * section can be linked to and survives a reload.
 */
const GROUPS = [
	{
		name: 'chat-agent',
		title: __( 'Chat Agent', 'directorist-smart-assistant' ),
		icon: <SparkleIcon size={ 16 } />,
		sections: AGENT_SECTIONS,
		component: ChatAgentSetup,
	},
	{
		name: 'vector-storage',
		title: __( 'Vector Storage', 'directorist-smart-assistant' ),
		icon: <DatabaseIcon size={ 16 } />,
		sections: VECTOR_SECTIONS,
		component: VectorStorageSetup,
	},
	{
		name: 'chat-module',
		title: __( 'Chat Widget', 'directorist-smart-assistant' ),
		icon: <ChatIcon size={ 16 } />,
		sections: WIDGET_SECTIONS,
		component: ChatModuleSettings,
	},
	{
		name: 'usage',
		title: __( 'Usage & Cost', 'directorist-smart-assistant' ),
		icon: <CoinIcon size={ 16 } />,
		sections: USAGE_SECTIONS,
		component: UsageDashboard,
	},
];

const DEFAULT_SETTINGS = {
	api_key: '',
	model: 'gpt-3.5-turbo',
	system_prompt: '',
	temperature: 0.7,
	max_tokens: 1000,
	// Vector storage settings
	vector_api_base_url: '',
	vector_api_secret_key: '',
	vector_website_id: '',
	vector_auto_sync: false,
	vector_listing_chunk_size: 20,
	vector_sync_directory_types: [],
	vector_sync_listing_statuses: [],
	vector_chunk_size: 500,
	vector_chunk_overlap: 50,
	vector_embedding_model: 'text-embedding-ada-002',
	vector_index_name: 'directorist-listings',
	vector_namespace: '',
	// Chat module settings
	chat_agent_name: '',
	chat_widget_position: 'bottom-right',
	chat_widget_color: '#667eea',
	chat_widget_icon: 'chat',
	chat_widget_icon_url: '',
};

/**
 * Resolve a "#group/section" hash to a valid location, defaulting sensibly.
 *
 * @param {string} hash Location hash.
 * @return {Object} { group, section }
 */
function locationFromHash( hash ) {
	const [ groupName, sectionKey ] = ( hash || '' ).replace( /^#/, '' ).split( '/' );
	const group = GROUPS.find( ( item ) => item.name === groupName ) || GROUPS[ 0 ];
	const section = group.sections.find( ( item ) => item.key === sectionKey ) || group.sections[ 0 ];

	return { group: group.name, section: section.key };
}

/**
 * Compact page header: identity on the left, readiness on the right.
 */
function PageHeader( { settings } ) {
	const ready = !! ( settings.api_key || '' ).trim();
	const vectorReady = !! (
		( settings.vector_api_base_url || '' ).trim() && ( settings.vector_api_secret_key || '' ).trim()
	);

	return (
		<header className="dsa-header">
			<div className="dsa-header__brand">
				<span className="dsa-header__mark">
					<SparkleIcon size={ 20 } />
				</span>
				<div>
					<h1 className="dsa-header__title">{ __( 'Smart Assistant', 'directorist-smart-assistant' ) }</h1>
					<p className="dsa-header__subtitle">
						{ __( 'AI chat for your Directorist listings.', 'directorist-smart-assistant' ) }
					</p>
				</div>
			</div>

			<div className="dsa-header__status">
				<span className={ `dsa-status ${ ready ? 'is-ready' : 'is-pending' }` }>
					{ ready ? <CheckIcon /> : <AlertIcon /> }
					{ ready
						? __( 'Assistant connected', 'directorist-smart-assistant' )
						: __( 'API key needed', 'directorist-smart-assistant' ) }
				</span>
				<span className={ `dsa-status ${ vectorReady ? 'is-ready' : 'is-pending' }` }>
					{ vectorReady ? <CheckIcon /> : <BoltIcon size={ 16 } /> }
					{ vectorReady
						? __( 'Vector storage ready', 'directorist-smart-assistant' )
						: __( 'Vector storage off', 'directorist-smart-assistant' ) }
				</span>
			</div>
		</header>
	);
}

/**
 * Sidebar navigation on wide screens; a grouped select on narrow ones.
 */
function SettingsNav( { active, onChange, attention = {} } ) {
	const value = `${ active.group }/${ active.section }`;

	return (
		<>
			<nav className="dsa-nav" aria-label={ __( 'Settings sections', 'directorist-smart-assistant' ) }>
				{ GROUPS.map( ( group ) => (
					<div
						key={ group.name }
						className={ `dsa-nav__group${ active.group === group.name ? ' is-current' : '' }` }
					>
						<p className="dsa-nav__head">
							<span className="dsa-nav__head-icon">{ group.icon }</span>
							<span className="dsa-nav__head-text">{ group.title }</span>
							{ attention[ group.name ] && (
								<span className="dsa-nav__attention" title={ __( 'Needs setup', 'directorist-smart-assistant' ) }>
									<span className="screen-reader-text">
										{ __( 'Needs setup', 'directorist-smart-assistant' ) }
									</span>
								</span>
							) }
						</p>
						<ul className="dsa-nav__list">
							{ group.sections.map( ( section ) => {
								const isActive = active.group === group.name && active.section === section.key;

								return (
									<li key={ section.key }>
										<button
											type="button"
											className={ `dsa-nav__item${ isActive ? ' is-active' : '' }` }
											aria-current={ isActive ? 'page' : undefined }
											onClick={ () => onChange( { group: group.name, section: section.key } ) }
										>
											{ section.title }
										</button>
									</li>
								);
							} ) }
						</ul>
					</div>
				) ) }
			</nav>

			<label className="dsa-nav-select">
				<span className="dsa-nav-select__group">
					{ ( GROUPS.find( ( item ) => item.name === active.group ) || GROUPS[ 0 ] ).title }
				</span>
				<span className="screen-reader-text">{ __( 'Settings section', 'directorist-smart-assistant' ) }</span>
				<select
					value={ value }
					onChange={ ( event ) => {
						const [ group, section ] = event.target.value.split( '/' );
						onChange( { group, section } );
					} }
				>
					{ GROUPS.map( ( group ) => (
						<optgroup key={ group.name } label={ group.title }>
							{ group.sections.map( ( section ) => (
								<option key={ section.key } value={ `${ group.name }/${ section.key }` }>
									{ section.title }
								</option>
							) ) }
						</optgroup>
					) ) }
				</select>
			</label>
		</>
	);
}

/**
 * Loading skeleton in the shape of the real layout.
 */
function LoadingState() {
	return (
		<div className="dsa-shell dsa-shell--loading" aria-busy="true" aria-live="polite">
			<span className="screen-reader-text">{ __( 'Loading settings…', 'directorist-smart-assistant' ) }</span>
			<div className="dsa-skeleton__nav">
				<div className="dsa-skeleton__line dsa-skeleton__line--title" />
				<div className="dsa-skeleton__line" />
				<div className="dsa-skeleton__line" />
				<div className="dsa-skeleton__line dsa-skeleton__line--title" />
				<div className="dsa-skeleton__line" />
			</div>
			<div className="dsa-skeleton__card">
				<div className="dsa-skeleton__line dsa-skeleton__line--title" />
				<div className="dsa-skeleton__line" />
				<div className="dsa-skeleton__line dsa-skeleton__line--wide" />
				<div className="dsa-skeleton__line" />
			</div>
		</div>
	);
}

/**
 * Admin App Component
 */
function AdminApp() {
	const [ settings, setSettings ] = useState( DEFAULT_SETTINGS );
	const [ active, setActive ] = useState( () => locationFromHash( window.location.hash ) );
	const [ loading, setLoading ] = useState( true );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		loadSettings();
	}, [] );

	// Keep the hash in step with the sidebar, and follow it when it changes.
	useEffect( () => {
		const next = `#${ active.group }/${ active.section }`;

		if ( window.location.hash !== next ) {
			window.history.replaceState( null, '', next );
		}
	}, [ active ] );

	useEffect( () => {
		const onHash = () => setActive( locationFromHash( window.location.hash ) );

		window.addEventListener( 'hashchange', onHash );

		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );

	const showNotice = useCallback( ( type, message ) => {
		setNotice( { type, message, key: Date.now() } );
		setTimeout( () => setNotice( null ), 5000 );
	}, [] );

	const loadSettings = async () => {
		try {
			const response = await apiFetch( {
				path: 'directorist-smart-assistant/v1/settings',
				method: 'GET',
			} );
			setSettings( response );
		} catch ( error ) {
			showNotice( 'error', error.message || __( 'Failed to load settings', 'directorist-smart-assistant' ) );
		} finally {
			setLoading( false );
		}
	};

	const handleSave = async ( updatedSettings ) => {
		try {
			const response = await apiFetch( {
				path: 'directorist-smart-assistant/v1/settings',
				method: 'POST',
				data: updatedSettings,
			} );

			if ( response.success ) {
				setSettings( updatedSettings );
				showNotice(
					'success',
					response.message || __( 'Settings saved successfully', 'directorist-smart-assistant' )
				);
			} else {
				showNotice(
					'error',
					response.message || __( 'Failed to save settings', 'directorist-smart-assistant' )
				);
			}
		} catch ( error ) {
			showNotice( 'error', error.message || __( 'Failed to save settings', 'directorist-smart-assistant' ) );
		}
	};

	const group = useMemo( () => GROUPS.find( ( item ) => item.name === active.group ) || GROUPS[ 0 ], [ active.group ] );
	const Panel = group.component;

	const attention = {
		'chat-agent': ! ( settings.api_key || '' ).trim(),
		'vector-storage': ! (
			( settings.vector_api_base_url || '' ).trim() && ( settings.vector_api_secret_key || '' ).trim()
		),
	};

	return (
		<div className="directorist-smart-assistant-admin">
			<PageHeader settings={ settings } />

			{ notice && (
				<div
					key={ notice.key }
					className={ `dsa-toast dsa-toast--${ notice.type }` }
					role={ notice.type === 'error' ? 'alert' : 'status' }
				>
					<span className="dsa-toast__icon">
						{ notice.type === 'success' ? <CheckIcon /> : <AlertIcon /> }
					</span>
					<span className="dsa-toast__message">{ notice.message }</span>
					<button
						type="button"
						className="dsa-toast__dismiss"
						onClick={ () => setNotice( null ) }
						aria-label={ __( 'Dismiss notice', 'directorist-smart-assistant' ) }
					>
						&times;
					</button>
				</div>
			) }

			{ loading ? (
				<LoadingState />
			) : (
				<div className="dsa-shell">
					<SettingsNav active={ active } onChange={ setActive } attention={ attention } />

					<main className="dsa-content" id={ `dsa-panel-${ group.name }` }>
						{ /* Remount per group so a group's local edits reset when leaving it. */ }
						<Panel
							key={ group.name }
							settings={ settings }
							onSave={ handleSave }
							onNotice={ showNotice }
							section={ active.section }
						/>
					</main>
				</div>
			) }
		</div>
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	// Mount only if the target element exists
	const container = document.getElementById( 'directorist-smart-assistant-admin-root' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <AdminApp /> );
	}
} );
