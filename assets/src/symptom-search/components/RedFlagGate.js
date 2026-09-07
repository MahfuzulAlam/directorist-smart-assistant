/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { localise } from '../conversation/engine';
import { SearchIcon } from '../icons';

/**
 * Layer 0 — the red flag checklist.
 *
 * Shown as one checklist rather than a sequence of questions, and answered
 * before anything reaches the model: any tick here routes straight to
 * emergency care in code.
 */
export default function RedFlagGate( { gate, locale, onClear, onEmergency } ) {
	const [ selected, setSelected ] = useState( [] );

	const toggle = ( value ) => {
		setSelected( ( prev ) =>
			prev.includes( value ) ? prev.filter( ( item ) => item !== value ) : [ ...prev, value ]
		);
	};

	const options = gate.options || [];

	return (
		<div className="dsa-ss__panel dsa-ss__gate">
			<p className="dsa-ss__gate-prompt">{ localise( gate.prompt, locale ) }</p>

			<div className="dsa-ss__checklist">
				{ options.map( ( option ) => (
					<button
						key={ option.value }
						type="button"
						role="checkbox"
						aria-checked={ selected.includes( option.value ) }
						className={ `dsa-ss__check${ selected.includes( option.value ) ? ' is-on' : '' }` }
						onClick={ () => toggle( option.value ) }
					>
						<span className="dsa-ss__check-box" aria-hidden="true" />
						<span>{ localise( option.label, locale ) }</span>
					</button>
				) ) }
			</div>

			<div className="dsa-ss__gate-actions">
				<button
					type="button"
					className="dsa-ss__btn dsa-ss__btn--accent dsa-ss__btn--block"
					disabled={ ! selected.length }
					onClick={ () => onEmergency( options.filter( ( option ) => selected.includes( option.value ) ) ) }
				>
					<SearchIcon />
					<span>{ __( 'Continue', 'directorist-smart-assistant' ) }</span>
				</button>

				<button type="button" className="dsa-ss__btn dsa-ss__btn--ghost dsa-ss__btn--block" onClick={ onClear }>
					{ localise( gate.none_option?.label, locale ) ||
						__( 'None of these', 'directorist-smart-assistant' ) }
				</button>
			</div>
		</div>
	);
}
