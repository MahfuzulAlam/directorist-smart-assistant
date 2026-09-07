/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SearchIcon } from '../icons';

/**
 * Free-text intake.
 *
 * The same profile object the guided flow fills, written in one go: whatever
 * the visitor types becomes the description sent for triage.
 */
export default function DescribePanel( { examples, value, onChange, onSubmit, submitting } ) {
	const [ text, setText ] = useState( value || '' );

	const update = ( next ) => {
		setText( next );
		onChange( next );
	};

	const tooShort = text.trim().length < 8;

	return (
		<div className="dsa-ss__panel">
			<form
				className="dsa-ss__describe"
				onSubmit={ ( event ) => {
					event.preventDefault();

					if ( ! tooShort ) {
						onSubmit( text.trim() );
					}
				} }
			>
				<label className="dsa-ss__label" htmlFor="dsa-ss-describe">
					{ __( 'Describe what you are feeling', 'directorist-smart-assistant' ) }
				</label>
				<textarea
					id="dsa-ss-describe"
					className="dsa-ss__textarea"
					rows={ 4 }
					value={ text }
					onChange={ ( event ) => update( event.target.value ) }
					placeholder={ __(
						'e.g. My lower left tooth throbs at night and the gum around it is swollen',
						'directorist-smart-assistant'
					) }
				/>

				{ !! examples.length && (
					<div className="dsa-ss__examples">
						<span className="dsa-ss__examples-label">
							{ __( 'Try:', 'directorist-smart-assistant' ) }
						</span>
						{ examples.map( ( example ) => (
							<button
								key={ example }
								type="button"
								className="dsa-ss__chip dsa-ss__chip--ghost"
								onClick={ () => update( example ) }
							>
								{ example }
							</button>
						) ) }
					</div>
				) }

				<button
					type="submit"
					className="dsa-ss__btn dsa-ss__btn--accent dsa-ss__btn--block"
					disabled={ submitting || tooShort }
				>
					<SearchIcon />
					<span>
						{ submitting
							? __( 'Checking…', 'directorist-smart-assistant' )
							: __( 'Get my recommendation', 'directorist-smart-assistant' ) }
					</span>
				</button>
			</form>
		</div>
	);
}
