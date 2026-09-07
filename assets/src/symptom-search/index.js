/**
 * Symptom based search — [symptom-based-search].
 *
 * Two intake modes fill ONE patient profile:
 *
 *   guided    → one key per answered question
 *   describe  → the whole complaint in the visitor's own words
 *
 * The profile is flattened into a single description, sent to the triage
 * service through the plugin's REST proxy, and the verdict is rendered in
 * place with a link into the directory.
 */

/**
 * WordPress dependencies
 */
import { createRoot, useState, useRef, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Styles
 */
import './index.css';

/**
 * Internal dependencies
 */
import GuidedChat from './components/GuidedChat';
import RedFlagGate from './components/RedFlagGate';
import EmergencyScreen from './components/EmergencyScreen';
import AnswerSummary from './components/AnswerSummary';
import { createEngine, initialState, rewindTo } from './conversation/engine';
import { specialtyPrompt } from './conversation/specialties';
import DescribePanel from './components/DescribePanel';
import TriageResult from './components/TriageResult';
import { ChatIcon, PenIcon, AlertIcon } from './icons';
import { getSessionId } from './utils';

const MODES = [
	{ key: 'guided', label: __( 'Guided', 'directorist-smart-assistant' ), icon: <ChatIcon /> },
	{ key: 'describe', label: __( 'Describe', 'directorist-smart-assistant' ), icon: <PenIcon /> },
];

/**
 * Widget root.
 */
function SymptomSearch( { atts, config } ) {
	const flow = config.flow || {};
	const taxonomy = config.specialties || [];
	const engine = useMemo(
		() => createEngine( flow, config.locale || 'en', taxonomy ),
		[ flow, config.locale, taxonomy ]
	);

	const [ mode, setMode ] = useState( atts.mode || 'guided' );
	const [ gatePassed, setGatePassed ] = useState( false );
	const [ redFlags, setRedFlags ] = useState( [] );
	const [ convo, setConvo ] = useState( () => initialState( engine, config.locationOptions || [] ) );
	const [ profile, setProfile ] = useState( {} );
	const [ status, setStatus ] = useState( 'idle' ); // idle | loading | done | error
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( '' );
	const lastDescription = useRef( '' );

	const gate = flow.red_flags || null;
	const rows = profile.description
		? [
				{
					nodeId: 'description',
					label: __( 'In your words', 'directorist-smart-assistant' ),
					value: profile.description,
				},
		  ]
		: engine.rows( convo );

	// The tier the question flow itself arrived at. The model may raise it, never lower it.
	const codeUrgency = 'guided' === mode && convo.history.length ? engine.urgency( convo ) : '';

	/**
	 * Send one assessment.
	 *
	 * `core` is the clinical text — a guided transcript, a typed complaint, or
	 * either of those plus refinement answers. The specialty vocabulary is
	 * appended here, at the last moment, so every path offers the model the
	 * same list and refinements never land after it.
	 *
	 * @param {string} core Clinical description.
	 */
	const runTriage = async ( core ) => {
		if ( ! core ) {
			return;
		}

		lastDescription.current = core;

		const vocabulary = specialtyPrompt( taxonomy );
		const description = vocabulary ? `${ core } ${ vocabulary }` : core;

		setStatus( 'loading' );
		setError( '' );

		try {
			const response = await apiFetch( {
				url: config.restUrl,
				method: 'POST',
				data: {
					session_id: getSessionId(),
					description,
				},
			} );

			if ( response && response.success ) {
				setResult( response.data );
				setStatus( 'done' );
			} else {
				setError(
					( response && response.message ) ||
						__( 'We could not check your symptoms. Please try again.', 'directorist-smart-assistant' )
				);
				setStatus( 'error' );
			}
		} catch ( requestError ) {
			setError(
				requestError.message ||
					__( 'We could not check your symptoms. Please try again.', 'directorist-smart-assistant' )
			);
			setStatus( 'error' );
		}
	};

	const submitGuided = () => runTriage( engine.describe( convo ) );

	const answerNode = ( nodeId, values ) => setConvo( ( prev ) => engine.answer( prev, nodeId, values ) );

	const editAnswer = ( nodeId ) => setConvo( ( prev ) => rewindTo( prev, nodeId ) );

	const submitDescription = ( text ) => {
		setProfile( { description: text } );
		runTriage( text );
	};

	const refine = ( extra ) => runTriage( `${ lastDescription.current } ${ extra }`.trim() );

	const restart = () => {
		setProfile( {} );
		setResult( null );
		setError( '' );
		setStatus( 'idle' );
		setRedFlags( [] );
		setGatePassed( false );
		setConvo( initialState( engine, config.locationOptions || [] ) );
		lastDescription.current = '';
	};

	const switchMode = ( next ) => {
		setMode( next );
		setProfile( {} );
		setError( '' );
	};

	/**
	 * A ticked red flag ends the conversation in code — the service is not
	 * called, because the answer is already known and must not be softened.
	 */
	const declareEmergency = ( reasons ) => {
		setRedFlags( reasons );
		setGatePassed( true );
		setStatus( 'emergency' );
	};

	const busy = 'loading' === status;

	return (
		<div className="dsa-ss">
			<div className="dsa-ss__frame">
				<header className="dsa-ss__head">
					<p className="dsa-ss__brand">
						<span className="dsa-ss__pulse" aria-hidden="true" />
						<span>{ atts.title }</span>
					</p>

					{ 'done' !== status && (
						<div
							className="dsa-ss__tabs"
							role="tablist"
							aria-label={ __( 'Input mode', 'directorist-smart-assistant' ) }
						>
							{ MODES.map( ( item ) => (
								<button
									key={ item.key }
									type="button"
									role="tab"
									aria-selected={ mode === item.key }
									className={ `dsa-ss__tab${ mode === item.key ? ' is-active' : '' }` }
									onClick={ () => switchMode( item.key ) }
								>
									{ item.icon }
									<span>{ item.label }</span>
								</button>
							) ) }
						</div>
					) }
				</header>

				<div className="dsa-ss__body">
					{ !! atts.subtitle && 'done' !== status && 'emergency' !== status && (
						<p className="dsa-ss__subtitle">{ atts.subtitle }</p>
					) }

					{ ! config.configured && (
						<p className="dsa-ss__notice" role="status">
							<AlertIcon size={ 16 } />
							<span>
								{ __(
									'The symptom checker is not connected yet. Add your API credentials under Directorist → Smart Assistant.',
									'directorist-smart-assistant'
								) }
							</span>
						</p>
					) }

					{ !! error && (
						<p className="dsa-ss__notice dsa-ss__notice--error" role="alert">
							<AlertIcon size={ 16 } />
							<span>{ error }</span>
						</p>
					) }

					{ busy && (
						<div className="dsa-ss__loading" aria-live="polite">
							<span className="dsa-ss__spinner" aria-hidden="true" />
							<span>{ __( 'Checking your symptoms…', 'directorist-smart-assistant' ) }</span>
						</div>
					) }

					{ 'emergency' === status && (
						<EmergencyScreen
							reasons={ redFlags }
							locale={ engine.locale }
							searchUrl={ config.searchUrl }
							onRestart={ restart }
						/>
					) }

					{ 'done' === status && result && (
						<TriageResult
							result={ result }
							profile={ profile }
							rows={ rows }
							searchUrl={ config.searchUrl }
							floorUrgency={ codeUrgency }
							taxonomy={ taxonomy }
							onRefine={ refine }
							onRestart={ restart }
							refining={ busy }
						/>
					) }

					{ 'done' !== status && 'emergency' !== status && ! busy && 'guided' === mode && (
						<>
							{ gate && ! gatePassed ? (
								<RedFlagGate
									gate={ gate }
									locale={ engine.locale }
									onClear={ () => setGatePassed( true ) }
									onEmergency={ declareEmergency }
								/>
							) : (
								<GuidedChat
									engine={ engine }
									state={ convo }
									onAnswer={ answerNode }
									onFinish={ submitGuided }
									onRestart={ restart }
									submitting={ busy }
								/>
							) }
						</>
					) }

					{ 'done' !== status && 'emergency' !== status && ! busy && 'describe' === mode && (
						<DescribePanel
							examples={ config.examples || [] }
							value={ profile.description || '' }
							onChange={ ( text ) => setProfile( { description: text } ) }
							onSubmit={ submitDescription }
							submitting={ busy }
						/>
					) }
				</div>

				{ !! rows.length && 'done' !== status && 'emergency' !== status && (
					<AnswerSummary
						rows={ rows }
						total={ ( flow.config || {} ).max_questions_per_session }
						editable={ 'guided' === mode && ! busy }
						onEdit={ editAnswer }
					/>
				) }
			</div>

			{ !! atts.disclaimer && <p className="dsa-ss__foot-note">{ atts.disclaimer }</p> }
		</div>
	);
}

/**
 * Mount every instance of the shortcode on the page.
 */
function mount() {
	const config = window.directoristSmartAssistantSymptom || {};

	document.querySelectorAll( '[data-dsa-symptom-search]' ).forEach( ( container ) => {
		if ( container.dataset.mounted ) {
			return;
		}

		container.dataset.mounted = '1';

		let atts = {};

		try {
			atts = JSON.parse( container.dataset.config || '{}' );
		} catch ( e ) {
			atts = {};
		}

		createRoot( container ).render( <SymptomSearch atts={ atts } config={ config } /> );
	} );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
