/**
 * WordPress dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { localise, nodeOptions } from '../conversation/engine';
import { SendIcon, SearchIcon } from '../icons';

const TYPING_MS = 380;

/**
 * Guided conversation.
 *
 * Renders whatever node the engine says comes next; the engine owns routing,
 * scoring and when to stop. Each node type gets the control it needs — chips
 * for a single choice, a checklist plus Continue for a multi-select, a 1–10
 * row for a scale, an input for numbers and free text.
 */
export default function GuidedChat( { engine, state, onAnswer, onFinish, onRestart, submitting } ) {
	const [ typing, setTyping ] = useState( false );
	const [ input, setInput ] = useState( '' );
	const [ picked, setPicked ] = useState( [] );
	const logRef = useRef( null );
	const timerRef = useRef( null );

	const locale = engine.locale;
	const node = engine.node( state.nodeId );
	const done = ! node || 'terminal' === node.type;
	const options = nodeOptions( node, state.locationOptions );
	const askedNode = useRef( null );

	/*
	 * The transcript is derived from the answered history rather than stored,
	 * so re-opening an earlier answer trims the conversation automatically.
	 */
	const transcript = [];

	state.history.forEach( ( nodeId ) => {
		transcript.push( { who: 'bot', text: engine.promptText( nodeId ), key: `q-${ nodeId }` } );
		transcript.push( { who: 'user', text: engine.answerText( state, nodeId ), key: `a-${ nodeId }` } );
	} );

	// Pause before the current question, then let it appear.
	useEffect( () => {
		if ( done || askedNode.current === state.nodeId ) {
			return undefined;
		}

		askedNode.current = state.nodeId;
		setPicked( [] );
		setInput( '' );
		setTyping( true );

		timerRef.current = window.setTimeout( () => setTyping( false ), TYPING_MS );

		return () => window.clearTimeout( timerRef.current );
	}, [ state.nodeId, done ] );

	useEffect( () => {
		if ( logRef.current ) {
			logRef.current.scrollTop = logRef.current.scrollHeight;
		}
	}, [ transcript, typing ] );

	/**
	 * Record an answer and echo it into the transcript.
	 *
	 * @param {Array|string} values Chosen values.
	 * @param {string}       echo   What to show as the visitor's reply.
	 */
	const submit = ( values ) => {
		if ( typing ) {
			return;
		}

		setPicked( [] );
		setInput( '' );
		onAnswer( state.nodeId, values );
	};

	const togglePick = ( value ) => {
		setPicked( ( prev ) =>
			prev.includes( value ) ? prev.filter( ( item ) => item !== value ) : [ ...prev, value ]
		);
	};

	const restart = () => {
		setPicked( [] );
		setInput( '' );
		askedNode.current = null;
		onRestart();
	};

	const budget = engine.budget( state );

	return (
		<div className="dsa-ss__panel">
			<div className="dsa-ss__log" ref={ logRef } role="log" aria-live="polite">
				{ transcript.map( ( line ) => (
					<div key={ line.key } className={ `dsa-ss__bubble dsa-ss__bubble--${ line.who }` }>
						{ line.text }
					</div>
				) ) }

				{ ! done && typing && (
					<div className="dsa-ss__bubble dsa-ss__bubble--bot dsa-ss__bubble--typing">
						<span />
						<span />
						<span />
					</div>
				) }

				{ ! done && ! typing && (
					<div className="dsa-ss__bubble dsa-ss__bubble--bot">{ engine.promptText( state.nodeId ) }</div>
				) }

				{ done && ! typing && (
					<div className="dsa-ss__bubble dsa-ss__bubble--bot">
						{ __(
							'Thanks — I have enough to point you in the right direction.',
							'directorist-smart-assistant'
						) }
					</div>
				) }
			</div>

			{ ! done && ! typing && (
				<div className="dsa-ss__reply">
					{ /* Single choice — one tap answers and advances. */ }
					{ ( 'single_select' === node.type || 'body_map' === node.type || 'location' === node.type ) && (
						<div className="dsa-ss__chips">
							{ options.map( ( option ) => (
								<button
									key={ option.value }
									type="button"
									className="dsa-ss__chip"
									onClick={ () => submit( option.value ) }
								>
									{ localise( option.label, locale ) }
								</button>
							) ) }
						</div>
					) }

					{ /* Several may apply — collect, then confirm. */ }
					{ 'multi_select' === node.type && (
						<>
							<div className="dsa-ss__chips">
								{ options.map( ( option ) => (
									<button
										key={ option.value }
										type="button"
										aria-pressed={ picked.includes( option.value ) }
										className={ `dsa-ss__chip${
											picked.includes( option.value ) ? ' is-on' : ''
										}` }
										onClick={ () => togglePick( option.value ) }
									>
										{ localise( option.label, locale ) }
									</button>
								) ) }
							</div>

							<div className="dsa-ss__multi-actions">
								<button
									type="button"
									className="dsa-ss__btn dsa-ss__btn--accent"
									disabled={ ! picked.length }
									onClick={ () => submit( picked ) }
								>
									{ picked.length
										? sprintf(
												/* translators: %d: number of selected answers. */
												__( 'Continue with %d', 'directorist-smart-assistant' ),
												picked.length
										  )
										: __( 'Continue', 'directorist-smart-assistant' ) }
								</button>

								{ node.allow_none && (
									<button
										type="button"
										className="dsa-ss__linkbtn"
										onClick={ () => submit( [] ) }
									>
										{ __( 'None of these', 'directorist-smart-assistant' ) }
									</button>
								) }
							</div>
						</>
					) }

					{ /* 1–10 severity. */ }
					{ 'scale' === node.type && (
						<div className="dsa-ss__scale">
							{ Array.from(
								{ length: ( node.max || 10 ) - ( node.min || 1 ) + 1 },
								( item, index ) => ( node.min || 1 ) + index
							).map( ( value ) => (
								<button
									key={ value }
									type="button"
									className="dsa-ss__scale-step"
									style={ { '--dsa-ss-step': ( value - 1 ) / 9 } }
									onClick={ () => submit( value ) }
								>
									{ value }
								</button>
							) ) }
						</div>
					) }

					{ /* Age, and free text such as the allergy detail. */ }
					{ ( 'number' === node.type || 'text' === node.type ) && (
						<form
							className="dsa-ss__form"
							onSubmit={ ( event ) => {
								event.preventDefault();

								const value = input.trim();

								if ( value ) {
									submit( value );
								}
							} }
						>
							<label className="screen-reader-text" htmlFor="dsa-ss-answer">
								{ localise( node.prompt, locale ) }
							</label>
							<input
								id="dsa-ss-answer"
								type={ 'number' === node.type ? 'number' : 'text' }
								className="dsa-ss__input"
								value={ input }
								min={ node.min }
								max={ node.max }
								maxLength={ node.max_length }
								onChange={ ( event ) => setInput( event.target.value ) }
								autoComplete="off"
							/>
							<button
								type="submit"
								className="dsa-ss__iconbtn"
								aria-label={ __( 'Send answer', 'directorist-smart-assistant' ) }
							>
								<SendIcon />
							</button>
						</form>
					) }

					<p className="dsa-ss__progress">
						{ budget > 0
							? sprintf(
									/* translators: %d: number of remaining questions. */
									_n(
										'%d question left at most',
										'%d questions left at most',
										budget,
										'directorist-smart-assistant'
									),
									budget
							  )
							: __( 'Last question', 'directorist-smart-assistant' ) }
					</p>
				</div>
			) }

			{ done && (
				<div className="dsa-ss__done">
					<button
						type="button"
						className="dsa-ss__btn dsa-ss__btn--accent"
						onClick={ onFinish }
						disabled={ submitting }
					>
						<SearchIcon />
						<span>
							{ submitting
								? __( 'Checking…', 'directorist-smart-assistant' )
								: __( 'Get my recommendation', 'directorist-smart-assistant' ) }
						</span>
					</button>
					<button type="button" className="dsa-ss__linkbtn" onClick={ restart }>
						{ __( 'Start over', 'directorist-smart-assistant' ) }
					</button>
				</div>
			) }
		</div>
	);
}
