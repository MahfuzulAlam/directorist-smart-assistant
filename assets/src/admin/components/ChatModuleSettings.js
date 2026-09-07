/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { TextControl, SelectControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Card, CardBody, SectionHeader, Field, SaveBar } from './Ui';
import { ChatIcon, PaletteIcon } from './Icons';
import { WIDGET_ICONS, DEFAULT_WIDGET_ICON, renderWidgetIcon } from '../../shared/widget-icons';

const COLOR_PRESETS = [ '#667eea', '#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#111827' ];

export const SECTIONS = [
	{
		key: 'identity',
		title: __( 'Identity & Placement', 'directorist-smart-assistant' ),
		icon: <ChatIcon size={ 16 } />,
		intro: __(
			'Name your assistant and choose where the launcher sits on the page.',
			'directorist-smart-assistant'
		),
	},
	{
		key: 'appearance',
		title: __( 'Appearance', 'directorist-smart-assistant' ),
		icon: <PaletteIcon size={ 16 } />,
		intro: __(
			'Match the widget to your brand: pick the launcher icon and the accent colour.',
			'directorist-smart-assistant'
		),
	},
];

/**
 * Chat Module Settings Component
 */
export default function ChatModuleSettings( { settings, onSave, section } ) {
	const [ localSettings, setLocalSettings ] = useState( settings );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		setLocalSettings( settings );
	}, [ settings ] );

	const dirty = JSON.stringify( localSettings ) !== JSON.stringify( settings );

	const handleChange = ( key, value ) => {
		setLocalSettings( ( prev ) => ( {
			...prev,
			[ key ]: value,
		} ) );
	};

	const handleSave = async () => {
		setSaving( true );
		try {
			await onSave( localSettings );
		} finally {
			setSaving( false );
		}
	};

	const positionOptions = [
		{ label: __( 'Bottom Right', 'directorist-smart-assistant' ), value: 'bottom-right' },
		{ label: __( 'Bottom Left', 'directorist-smart-assistant' ), value: 'bottom-left' },
	];

	const iconKey = localSettings.chat_widget_icon || DEFAULT_WIDGET_ICON;
	const iconUrl = localSettings.chat_widget_icon_url || '';

	/**
	 * Open the WordPress media library to pick a custom launcher image.
	 */
	const pickImage = () => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		const frame = window.wp.media( {
			title: __( 'Choose a launcher icon', 'directorist-smart-assistant' ),
			button: { text: __( 'Use this image', 'directorist-smart-assistant' ) },
			library: { type: 'image' },
			multiple: false,
		} );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			// Prefer a small size when the library has one — the launcher is 56px.
			const url =
				( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) ||
				attachment.url;

			handleChange( 'chat_widget_icon_url', url );
		} );

		frame.open();
	};

	const color = localSettings.chat_widget_color || '#667eea';
	const position = localSettings.chat_widget_position || 'bottom-right';
	const agentName =
		( localSettings.chat_agent_name || '' ).trim() || __( 'Assistant', 'directorist-smart-assistant' );
	const current = SECTIONS.find( ( item ) => item.key === section ) || SECTIONS[ 0 ];

	return (
		<div className="dsa-panel">
			<SectionHeader title={ current.title } description={ current.intro } />

			<Card>
				<CardBody>
					<div className="dsa-split">
						<div className="dsa-split__main">

							{ 'identity' === section && (
								<>
									<Field className="dsa-field--control">
										<TextControl
											label={ __( 'Chat Agent Name', 'directorist-smart-assistant' ) }
											value={ localSettings.chat_agent_name || '' }
											onChange={ ( value ) => handleChange( 'chat_agent_name', value ) }
											placeholder={ __(
												'e.g. Assistant, Helper, Support',
												'directorist-smart-assistant'
											) }
											help={ __(
												'Used in the system prompt and shown in the widget header.',
												'directorist-smart-assistant'
											) }
											__nextHasNoMarginBottom
										/>
									</Field>

									<Field className="dsa-field--control">
										<SelectControl
											label={ __( 'Widget Position', 'directorist-smart-assistant' ) }
											value={ position }
											options={ positionOptions }
											onChange={ ( value ) =>
												handleChange( 'chat_widget_position', value )
											}
											help={ __(
												'Corner of the screen where the launcher button sits.',
												'directorist-smart-assistant'
											) }
											__nextHasNoMarginBottom
										/>
									</Field>
								</>
							) }

							{ 'appearance' === section && (
								<>
								<Field
									label={ __( 'Launcher Icon', 'directorist-smart-assistant' ) }
									help={ __(
										'Shown on the floating button and beside the assistant’s replies.',
										'directorist-smart-assistant'
									) }
								>
									<div
										className="dsa-iconpicker"
										role="radiogroup"
										aria-label={ __( 'Launcher icon', 'directorist-smart-assistant' ) }
									>
										{ Object.keys( WIDGET_ICONS ).map( ( key ) => (
											<button
												key={ key }
												type="button"
												role="radio"
												aria-checked={ ! iconUrl && iconKey === key }
												aria-label={ WIDGET_ICONS[ key ].label }
												title={ WIDGET_ICONS[ key ].label }
												className={ `dsa-iconpicker__item${
													! iconUrl && iconKey === key ? ' is-selected' : ''
												}` }
												style={ { '--dsa-icon-accent': color } }
												onClick={ () => {
													handleChange( 'chat_widget_icon', key );
													handleChange( 'chat_widget_icon_url', '' );
												} }
											>
												{ renderWidgetIcon( key, 22 ) }
											</button>
										) ) }
									</div>

									<div className="dsa-iconpicker__custom">
										{ iconUrl ? (
											<>
												<span className="dsa-iconpicker__preview">
													<img src={ iconUrl } alt="" />
												</span>
												<span className="dsa-iconpicker__custom-label">
													{ __( 'Custom image in use', 'directorist-smart-assistant' ) }
												</span>
												<Button variant="tertiary" onClick={ pickImage }>
													{ __( 'Replace', 'directorist-smart-assistant' ) }
												</Button>
												<Button
													variant="tertiary"
													isDestructive
													onClick={ () => handleChange( 'chat_widget_icon_url', '' ) }
												>
													{ __( 'Remove', 'directorist-smart-assistant' ) }
												</Button>
											</>
										) : (
											<Button
												className="dsa-button dsa-button--ghost"
												variant="secondary"
												onClick={ pickImage }
											>
												{ __( 'Upload a custom image', 'directorist-smart-assistant' ) }
											</Button>
										) }
									</div>
								</Field>

								<Field
									label={ __( 'Accent Color', 'directorist-smart-assistant' ) }
									htmlFor="dsa-widget-color"
									help={ __(
										'Applied to the launcher, the widget header and outgoing messages.',
										'directorist-smart-assistant'
									) }
								>
									<div className="dsa-color">
										<input
											id="dsa-widget-color"
											className="dsa-color__picker"
											type="color"
											value={ color }
											onChange={ ( e ) =>
												handleChange( 'chat_widget_color', e.target.value )
											}
										/>
										<input
											className="dsa-color__value"
											type="text"
											value={ color }
											onChange={ ( e ) =>
												handleChange( 'chat_widget_color', e.target.value )
											}
											placeholder="#667eea"
											spellCheck="false"
										/>
									</div>
									<div className="dsa-swatches">
										{ COLOR_PRESETS.map( ( preset ) => (
											<button
												key={ preset }
												type="button"
												className={ `dsa-swatch${
													preset.toLowerCase() === color.toLowerCase()
														? ' is-selected'
														: ''
												}` }
												style={ { '--dsa-swatch': preset } }
												onClick={ () => handleChange( 'chat_widget_color', preset ) }
												aria-label={ preset }
												title={ preset }
											/>
										) ) }
									</div>
								</Field>
								</>
							) }
						</div>

						<div className="dsa-split__aside">
							<div className="dsa-preview" style={ { '--dsa-accent-preview': color } }>
								<span className="dsa-preview__label">
									{ __( 'Live preview', 'directorist-smart-assistant' ) }
								</span>
								<div className={ `dsa-preview__stage dsa-preview__stage--${ position }` }>
									<div className="dsa-preview__window">
										<div className="dsa-preview__header">
											<span className="dsa-preview__avatar">
												{ iconUrl ? (
													<img className="dsa-preview__launcher-img" src={ iconUrl } alt="" />
												) : (
													renderWidgetIcon( iconKey, 13 )
												) }
											</span>
											<span className="dsa-preview__name">{ agentName }</span>
										</div>
										<div className="dsa-preview__messages">
											<span className="dsa-preview__bubble dsa-preview__bubble--in" />
											<span className="dsa-preview__bubble dsa-preview__bubble--in dsa-preview__bubble--short" />
											<span className="dsa-preview__bubble dsa-preview__bubble--out" />
										</div>
									</div>
									<span className="dsa-preview__launcher">
										{ iconUrl ? (
											<img className="dsa-preview__launcher-img" src={ iconUrl } alt="" />
										) : (
											renderWidgetIcon( iconKey, 18 )
										) }
									</span>
								</div>
							</div>
						</div>
					</div>
				</CardBody>
			</Card>

			<SaveBar
				onSave={ handleSave }
				onDiscard={ () => setLocalSettings( settings ) }
				saving={ saving }
				dirty={ dirty }
			/>
		</div>
	);
}
