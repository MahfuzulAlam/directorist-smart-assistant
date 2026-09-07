/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ChevronIcon, EditIcon } from '../icons';

/**
 * Review-and-confirm strip for the guided conversation.
 *
 * Collapsed it is one quiet line — enough to reassure the visitor that the
 * assistant heard them. Expanded it becomes a list where every answer can be
 * re-opened, which is the part that matters: a mis-tapped answer changes the
 * urgency tier, and the only previous remedy was starting over.
 */
export default function AnswerSummary( { rows, total, onEdit, editable = true } ) {
	const [ open, setOpen ] = useState( false );

	if ( ! rows.length ) {
		return null;
	}

	// The gist: the first couple of answers carry the most meaning.
	const gist = rows
		.slice( 0, 2 )
		.map( ( row ) => row.value )
		.join( ' · ' );

	return (
		<section className={ `dsa-ss__summary${ open ? ' is-open' : '' }` }>
			<button
				type="button"
				className="dsa-ss__summary-toggle"
				aria-expanded={ open }
				onClick={ () => setOpen( ! open ) }
			>
				<span className="dsa-ss__summary-head">
					<span className="dsa-ss__summary-title">
						{ __( 'Your answers', 'directorist-smart-assistant' ) }
					</span>
					<span className="dsa-ss__summary-count">
						{ total
							? sprintf(
									/* translators: 1: answers given, 2: maximum questions. */
									__( '%1$d of up to %2$d', 'directorist-smart-assistant' ),
									rows.length,
									total
							  )
							: rows.length }
					</span>
				</span>

				{ ! open && <span className="dsa-ss__summary-gist">{ gist }</span> }

				<span className="dsa-ss__summary-chevron" aria-hidden="true">
					<ChevronIcon />
				</span>
			</button>

			{ open && (
				<ul className="dsa-ss__summary-list">
					{ rows.map( ( row ) => (
						<li key={ row.nodeId } className="dsa-ss__summary-row">
							<span className="dsa-ss__summary-label">{ row.label }</span>
							<span className="dsa-ss__summary-value">{ row.value }</span>

							{ editable && (
								<button
									type="button"
									className="dsa-ss__summary-edit"
									onClick={ () => onEdit( row.nodeId ) }
								>
									<EditIcon />
									<span>{ __( 'Change', 'directorist-smart-assistant' ) }</span>
									<span className="screen-reader-text">
										{ sprintf(
											/* translators: %s: the question being changed. */
											__( '— %s', 'directorist-smart-assistant' ),
											row.label
										) }
									</span>
								</button>
							) }
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}
