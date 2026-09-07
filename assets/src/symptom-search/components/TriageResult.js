/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { StethoscopeIcon, ClockIcon, AlertIcon, AwardIcon, SearchIcon, RefreshIcon, ShieldIcon } from '../icons';
import { searchUrlFor } from '../utils';
import { pickSpecialty } from '../conversation/specialties';

/**
 * Urgency values the service returns, mapped to a tone and a human label.
 *
 * @param {string} urgency Raw urgency value.
 * @return {Object} { tone, label }
 */
function urgencyMeta( urgency ) {
	const key = ( urgency || '' ).toLowerCase();

	if ( 'emergency' === key || 'immediate' === key || 'urgent' === key ) {
		return { tone: 'critical', label: __( 'Seek care immediately', 'directorist-smart-assistant' ) };
	}

	if ( 'urgent_24h' === key ) {
		return { tone: 'critical', label: __( 'Be seen within 24 hours', 'directorist-smart-assistant' ) };
	}

	if ( 'soon' === key || 'soon_1week' === key ) {
		return { tone: 'warning', label: __( 'See a dentist within a week', 'directorist-smart-assistant' ) };
	}

	if ( 'routine' === key || 'flexible' === key || 'non-urgent' === key ) {
		return { tone: 'calm', label: __( 'Routine — book when convenient', 'directorist-smart-assistant' ) };
	}

	return { tone: 'calm', label: urgency || __( 'No urgency given', 'directorist-smart-assistant' ) };
}

/**
 * Turn an hours figure into a readable window.
 *
 * @param {number} hours Hours until care is advised.
 * @return {string}
 */
function withinLabel( hours ) {
	if ( ! hours ) {
		return '';
	}

	if ( hours <= 24 ) {
		return sprintf(
			/* translators: %d: number of hours. */
			__( 'within %d hours', 'directorist-smart-assistant' ),
			hours
		);
	}

	const days = Math.round( hours / 24 );

	return sprintf(
		/* translators: %d: number of days. */
		_n( 'within %d day', 'within %d days', days, 'directorist-smart-assistant' ),
		days
	);
}

/**
 * The verdict, shown once the triage service answers.
 */
export default function TriageResult( {
	result,
	profile,
	rows,
	searchUrl,
	floorUrgency,
	taxonomy,
	onRefine,
	onRestart,
	refining,
} ) {
	const [ answers, setAnswers ] = useState( {} );

	// Safety rule from the flow document: the model may raise urgency, never
	// lower it below the tier the question flow already established.
	const RANK = { routine: 0, soon_1week: 1, urgent_24h: 2, emergency: 3 };
	const fromService = ( result.urgency || '' ).toLowerCase();
	const serviceRank = RANK[ fromService ] ?? -1;
	const floorRank = RANK[ floorUrgency ] ?? -1;
	const effectiveUrgency = floorRank > serviceRank ? floorUrgency : result.urgency;
	const raisedByFloor = floorRank > serviceRank && floorRank >= 0;

	const urgency = urgencyMeta( effectiveUrgency );
	const within = raisedByFloor ? '' : withinLabel( result.urgency_hours );


	/*
	 * The response carries two different answers: the specific specialty
	 * (`recommended_specialty` / `specialist_type`) and the broader field of
	 * care (`doctor_category`). Resolve them separately so neither is lost —
	 * collapsing them into one line silently dropped whichever did not win.
	 */
	const specific = pickSpecialty( [ result.recommended_specialty, result.specialist_type ], taxonomy );
	const category = pickSpecialty( [ result.doctor_category ], taxonomy );

	// Lead with the specialty when one is called for, otherwise the category.
	const primary = result.specialist_required && specific ? specific : category || specific;
	const other = primary === specific ? category : specific;

	const specialty = primary ? primary.label : '';
	const parentLabel =
		other && other.label !== specialty
			? other.label
			: primary && primary.group && primary.group.label !== specialty
			? primary.group.label
			: '';

	const followUps = result.follow_up_questions || [];
	const answered = Object.keys( answers ).filter( ( key ) => ( answers[ key ] || '' ).trim() );

	const refine = () => {
		const extra = answered
			.map( ( key ) => `${ followUps[ key ] } ${ answers[ key ].trim() }` )
			.join( ' ' );

		if ( extra ) {
			onRefine( extra );
			setAnswers( {} );
		}
	};

	return (
		<div className="dsa-ss__result">
			{ !! ( result.red_flags && result.red_flags.length ) && (
				<div className="dsa-ss__flags" role="alert">
					<span className="dsa-ss__flags-icon">
						<AlertIcon />
					</span>
					<div>
						<strong>{ __( 'Please do not wait', 'directorist-smart-assistant' ) }</strong>
						<ul>
							{ result.red_flags.map( ( flag ) => (
								<li key={ flag }>{ flag }</li>
							) ) }
						</ul>
					</div>
				</div>
			) }

			<div className={ `dsa-ss__verdict dsa-ss__verdict--${ urgency.tone }` }>
				<span className="dsa-ss__verdict-icon">
					<StethoscopeIcon size={ 22 } />
				</span>
				<div className="dsa-ss__verdict-main">
					<p className="dsa-ss__verdict-label">
						{ __( 'You should see', 'directorist-smart-assistant' ) }
					</p>
					<h3 className="dsa-ss__verdict-title">
						{ specialty || __( 'A dentist', 'directorist-smart-assistant' ) }
					</h3>
					{ !! parentLabel && (
						<p className="dsa-ss__verdict-parent">
							{ sprintf(
								/* translators: %s: parent specialty, e.g. "Endodontics". */
								__( '%s specialty', 'directorist-smart-assistant' ),
								parentLabel
							) }
						</p>
					) }
					<div className="dsa-ss__badges">
						<span className={ `dsa-ss__badge dsa-ss__badge--${ urgency.tone }` }>
							<ClockIcon />
							{ within ? `${ urgency.label } · ${ within }` : urgency.label }
						</span>

						{ result.specialist_required && (
							<span className="dsa-ss__badge">
								<ShieldIcon />
								{ __( 'Specialist recommended', 'directorist-smart-assistant' ) }
							</span>
						) }

						{ result.min_experience_years > 0 && (
							<span className="dsa-ss__badge">
								<AwardIcon />
								{ sprintf(
									/* translators: %d: number of years. */
									__( '%d+ years experience', 'directorist-smart-assistant' ),
									result.min_experience_years
								) }
							</span>
						) }
					</div>
				</div>
			</div>

			{ raisedByFloor && (
				<p className="dsa-ss__floor-note">
					{ __(
						'Your answers put this in a higher urgency band than the summary alone would suggest, so the stronger advice applies.',
						'directorist-smart-assistant'
					) }
				</p>
			) }

			{ !! result.recommended_action && (
				<p className="dsa-ss__action">{ result.recommended_action }</p>
			) }

			{ ! ( result.probable_reasons && result.probable_reasons.length ) && (
				<p className="dsa-ss__no-reasons">
					{ __(
						'No likely causes could be narrowed down from your answers — the urgency above still applies, and a dentist can examine you properly.',
						'directorist-smart-assistant'
					) }
				</p>
			) }

			{ !! ( result.probable_reasons && result.probable_reasons.length ) && (
				<section className="dsa-ss__section">
					<h4 className="dsa-ss__section-title">
						{ __( 'What this could be', 'directorist-smart-assistant' ) }
					</h4>
					<ul className="dsa-ss__reasons">
						{ result.probable_reasons.map( ( reason ) => (
							<li key={ reason.condition } className="dsa-ss__reason">
								<div className="dsa-ss__reason-head">
									<span className="dsa-ss__reason-name">{ reason.condition }</span>
									{ null !== reason.confidence && (
										<span className="dsa-ss__reason-score">
											{ Math.round( reason.confidence * 100 ) }%
										</span>
									) }
								</div>
								{ null !== reason.confidence && (
									<span className="dsa-ss__meter" aria-hidden="true">
										<span
											className="dsa-ss__meter-fill"
											style={ { width: `${ Math.round( reason.confidence * 100 ) }%` } }
										/>
									</span>
								) }
								{ !! reason.why && <p className="dsa-ss__reason-why">{ reason.why }</p> }
							</li>
						) ) }
					</ul>
				</section>
			) }

			{ !! rows.length && (
				<section className="dsa-ss__section">
					<h4 className="dsa-ss__section-title">
						{ __( 'What you told us', 'directorist-smart-assistant' ) }
					</h4>
					<ul className="dsa-ss__recap">
						{ rows.map( ( row ) => (
							<li key={ row.nodeId || row.label }>
								<span className="dsa-ss__recap-label">{ row.label }</span>
								<span className="dsa-ss__recap-value">{ row.value }</span>
							</li>
						) ) }
					</ul>
				</section>
			) }

			{ !! followUps.length && (
				<section className="dsa-ss__section dsa-ss__followups">
					<h4 className="dsa-ss__section-title">
						{ __( 'Answer these to sharpen the result', 'directorist-smart-assistant' ) }
					</h4>
					{ followUps.map( ( question, index ) => (
						<div key={ question } className="dsa-ss__followup">
							<label className="dsa-ss__label" htmlFor={ `dsa-ss-followup-${ index }` }>
								{ question }
							</label>
							<input
								id={ `dsa-ss-followup-${ index }` }
								type="text"
								className="dsa-ss__input"
								value={ answers[ index ] || '' }
								onChange={ ( event ) =>
									setAnswers( ( prev ) => ( { ...prev, [ index ]: event.target.value } ) )
								}
								placeholder={ __( 'Your answer…', 'directorist-smart-assistant' ) }
							/>
						</div>
					) ) }
					<button
						type="button"
						className="dsa-ss__btn dsa-ss__btn--ghost"
						onClick={ refine }
						disabled={ refining || ! answered.length }
					>
						<RefreshIcon />
						<span>
							{ refining
								? __( 'Updating…', 'directorist-smart-assistant' )
								: __( 'Update my result', 'directorist-smart-assistant' ) }
						</span>
					</button>
				</section>
			) }

			<div className="dsa-ss__cta">
				<a
					className="dsa-ss__btn dsa-ss__btn--accent"
					href={ searchUrlFor( searchUrl, { ...result, resolved_label: specialty }, profile ) }
				>
					<SearchIcon />
					<span>
						{ specialty
							? sprintf(
									/* translators: %s: doctor category, e.g. "Endodontist". */
									__( 'Browse %s listings near you', 'directorist-smart-assistant' ),
									specialty
							  )
							: __( 'Browse dentists near you', 'directorist-smart-assistant' ) }
					</span>
				</a>
				<button type="button" className="dsa-ss__linkbtn" onClick={ onRestart }>
					{ __( 'Start over', 'directorist-smart-assistant' ) }
				</button>
			</div>

			<footer className="dsa-ss__result-foot">
				{ null !== result.confidence && (
					<span className="dsa-ss__confidence">
						{ sprintf(
							/* translators: %d: confidence percentage. */
							__( 'Overall confidence %d%%', 'directorist-smart-assistant' ),
							Math.round( result.confidence * 100 )
						) }
					</span>
				) }
				{ !! result.disclaimer && <p className="dsa-ss__disclaimer">{ result.disclaimer }</p> }
			</footer>
		</div>
	);
}
