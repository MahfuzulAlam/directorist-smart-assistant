/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { EyeIcon, EyeOffIcon, InfoIcon, CheckIcon, AlertIcon } from './Icons';

/**
 * Shared building blocks for the admin panel.
 *
 * Every tab is composed from these so spacing, typography and controls stay
 * identical across the whole screen.
 */

/**
 * A single settings card.
 */
export function Card( { children } ) {
	return <section className="dsa-card">{ children }</section>;
}

/**
 * Heading for the section the sidebar has selected.
 *
 * @param {Object} props
 * @param {string} props.title       Section title.
 * @param {string} props.description One-line purpose of the section.
 * @param {Object} props.badge       Optional status pill.
 */
export function SectionHeader( { title, description, badge } ) {
	return (
		<header className="dsa-section">
			<div className="dsa-section__text">
				<h2 className="dsa-section__title">{ title }</h2>
				{ description && <p className="dsa-section__description">{ description }</p> }
			</div>
			{ badge && <div className="dsa-section__badge">{ badge }</div> }
		</header>
	);
}

/**
 * Card body — holds the fields of the visible section.
 */
export function CardBody( { children, id, labelledBy } ) {
	return (
		<div
			className="dsa-card__body"
			id={ id }
			role={ id ? 'tabpanel' : undefined }
			aria-labelledby={ labelledBy }
		>
			{ children }
		</div>
	);
}

/**
 * Field wrapper. `emphasis` renders the highlighted treatment used for
 * credentials and other primary inputs.
 */
export function Field( { label, htmlFor, children, help, emphasis = false, className = '' } ) {
	const classes = [ 'dsa-field', emphasis ? 'dsa-field--emphasis' : '', className ]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ classes }>
			{ label && (
				<label className="dsa-field__label" htmlFor={ htmlFor }>
					{ label }
				</label>
			) }
			{ children }
			{ help && <HelpText>{ help }</HelpText> }
		</div>
	);
}

/**
 * Inline help copy with a leading info glyph.
 */
export function HelpText( { children, tone = 'neutral' } ) {
	return (
		<p className={ `dsa-help dsa-help--${ tone }` }>
			<InfoIcon />
			<span>{ children }</span>
		</p>
	);
}

/**
 * Two column responsive grid.
 */
export function FieldGrid( { children, columns = 2 } ) {
	return <div className={ `dsa-grid dsa-grid--${ columns }` }>{ children }</div>;
}

/**
 * Small status pill — used to surface whether a key or connection is set up.
 */
export function StatusPill( { status = 'neutral', children } ) {
	const glyph = status === 'success' ? <CheckIcon /> : status === 'warning' ? <AlertIcon /> : null;

	return (
		<span className={ `dsa-pill dsa-pill--${ status }` }>
			{ glyph }
			{ children }
		</span>
	);
}

/**
 * Masked credential input with a show / hide toggle.
 */
export function SecretField( { label, value, onChange, placeholder, help, id } ) {
	const [ visible, setVisible ] = useState( false );

	return (
		<Field label={ label } htmlFor={ id } help={ help } emphasis>
			<div className="dsa-secret">
				<TextControl
					id={ id }
					className="dsa-secret__input"
					type={ visible ? 'text' : 'password' }
					value={ value || '' }
					onChange={ onChange }
					placeholder={ placeholder }
					autoComplete="off"
					__nextHasNoMarginBottom
				/>
				<Button
					className="dsa-secret__toggle"
					variant="tertiary"
					onClick={ () => setVisible( ! visible ) }
					aria-label={
						visible
							? __( 'Hide value', 'directorist-smart-assistant' )
							: __( 'Show value', 'directorist-smart-assistant' )
					}
				>
					{ visible ? <EyeOffIcon /> : <EyeIcon /> }
					<span>
						{ visible
							? __( 'Hide', 'directorist-smart-assistant' )
							: __( 'Show', 'directorist-smart-assistant' ) }
					</span>
				</Button>
			</div>
		</Field>
	);
}

/**
 * Scrollable list of checkboxes with a selection counter.
 */
export function OptionList( { children, empty = false, loading = false, loadingLabel, emptyLabel } ) {
	if ( loading ) {
		return (
			<div className="dsa-optionlist dsa-optionlist--placeholder">
				<span className="dsa-spinner" aria-hidden="true" />
				{ loadingLabel }
			</div>
		);
	}

	if ( empty ) {
		return <div className="dsa-optionlist dsa-optionlist--placeholder">{ emptyLabel }</div>;
	}

	return <div className="dsa-optionlist">{ children }</div>;
}

/**
 * Sticky action bar pinned to the bottom of the settings column.
 *
 * Always rendered and the button always enabled — hiding or disabling a save
 * control costs keyboard users their landmark. It changes register instead:
 * quiet while nothing has changed, prominent with a Discard action once
 * something has. ⌘S / Ctrl+S saves, and leaving the page with unsaved edits
 * asks first.
 */
export function SaveBar( { onSave, onDiscard, saving, dirty, children } ) {
	useEffect( () => {
		const onKey = ( event ) => {
			if ( ( event.metaKey || event.ctrlKey ) && 's' === event.key.toLowerCase() ) {
				event.preventDefault();

				if ( ! saving ) {
					onSave();
				}
			}
		};

		const onLeave = ( event ) => {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};

		window.addEventListener( 'keydown', onKey );
		window.addEventListener( 'beforeunload', onLeave );

		return () => {
			window.removeEventListener( 'keydown', onKey );
			window.removeEventListener( 'beforeunload', onLeave );
		};
	}, [ dirty, saving, onSave ] );

	return (
		<div className={ `dsa-savebar${ dirty ? ' is-dirty' : '' }` } role="region" aria-label={ __( 'Save changes', 'directorist-smart-assistant' ) }>
			<div className="dsa-savebar__status" aria-live="polite">
				{ dirty ? (
					<>
						<span className="dsa-savebar__dot" aria-hidden="true" />
						{ __( 'You have unsaved changes', 'directorist-smart-assistant' ) }
					</>
				) : (
					<>
						<CheckIcon />
						{ __( 'All changes saved', 'directorist-smart-assistant' ) }
					</>
				) }
			</div>
			<div className="dsa-savebar__actions">
				{ children }
				{ dirty && onDiscard && (
					<Button className="dsa-button dsa-button--quiet" variant="tertiary" onClick={ onDiscard } disabled={ saving }>
						{ __( 'Discard', 'directorist-smart-assistant' ) }
					</Button>
				) }
				<Button
					className="dsa-button dsa-button--primary"
					variant="primary"
					onClick={ onSave }
					isBusy={ saving }
					aria-disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'directorist-smart-assistant' )
						: __( 'Save changes', 'directorist-smart-assistant' ) }
					<kbd className="dsa-savebar__kbd" aria-hidden="true">⌘S</kbd>
				</Button>
			</div>
		</div>
	);
}
