/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { localise } from '../conversation/engine';
import { AlertIcon, SearchIcon } from '../icons';

/**
 * Shown the moment a red flag is ticked.
 *
 * The flow document is explicit that this state must not offer normal booking,
 * and the triage service is deliberately not called: the answer is already known.
 */
export default function EmergencyScreen( { reasons, locale, searchUrl, onRestart } ) {
	return (
		<div className="dsa-ss__result dsa-ss__emergency">
			<div className="dsa-ss__flags" role="alert">
				<span className="dsa-ss__flags-icon">
					<AlertIcon />
				</span>
				<div>
					<strong>
						{ __( 'This needs emergency care now', 'directorist-smart-assistant' ) }
					</strong>
					<ul>
						{ reasons.map( ( reason ) => (
							<li key={ reason.value }>{ localise( reason.label, locale ) }</li>
						) ) }
					</ul>
				</div>
			</div>

			{ /*
			   * The tier's `action` in the flow document is an instruction to the
			   * implementer ("Do not offer normal booking"), not something to show
			   * a frightened patient. Keep it for routing; say this instead.
			   */ }
			<p className="dsa-ss__action">
				{ __(
					'Go to an emergency dental service or a hospital emergency department straight away. Do not wait for a routine appointment.',
					'directorist-smart-assistant'
				) }
			</p>

			<ul className="dsa-ss__advice">
				<li>
					{ __(
						'If breathing or swallowing is affected, call emergency services rather than travelling yourself.',
						'directorist-smart-assistant'
					) }
				</li>
				<li>
					{ __(
						'For a tooth knocked out, keep it in milk or saliva — never scrub it — and seek care within the hour.',
						'directorist-smart-assistant'
					) }
				</li>
				<li>
					{ __(
						'For bleeding that will not stop, bite firmly on clean gauze and keep pressure on it while you travel.',
						'directorist-smart-assistant'
					) }
				</li>
			</ul>

			<div className="dsa-ss__cta">
				<a className="dsa-ss__btn dsa-ss__btn--accent" href={ searchUrl }>
					<SearchIcon />
					<span>{ __( 'Find emergency dental care', 'directorist-smart-assistant' ) }</span>
				</a>
				<button type="button" className="dsa-ss__linkbtn" onClick={ onRestart }>
					{ __( 'Start over', 'directorist-smart-assistant' ) }
				</button>
			</div>
		</div>
	);
}
