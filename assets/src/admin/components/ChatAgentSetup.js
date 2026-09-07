/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import {
	TextControl,
	SelectControl,
	TextareaControl,
	RangeControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	Card,
	CardBody,
	SectionHeader,
	Field,
	FieldGrid,
	SecretField,
	SaveBar,
	StatusPill,
} from './Ui';
import { SparkleIcon, SlidersIcon } from './Icons';

export const SECTIONS = [
	{
		key: 'openai',
		title: __( 'OpenAI Configuration', 'directorist-smart-assistant' ),
		icon: <SparkleIcon size={ 16 } />,
		intro: __(
			'Connect your OpenAI account and choose the model that powers the assistant.',
			'directorist-smart-assistant'
		),
	},
	{
		key: 'tuning',
		title: __( 'Response Tuning', 'directorist-smart-assistant' ),
		icon: <SlidersIcon size={ 16 } />,
		intro: __(
			'Balance creativity against precision and cap the length of each reply.',
			'directorist-smart-assistant'
		),
	},
];

/**
 * Chat Agent Setup Component
 */
export default function ChatAgentSetup( { settings, onSave, section } ) {
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

	const modelOptions = [
		{ label: 'GPT-3.5 Turbo', value: 'gpt-3.5-turbo' },
		{ label: 'GPT-4', value: 'gpt-4' },
		{ label: 'GPT-4 Turbo', value: 'gpt-4-turbo-preview' },
		{ label: 'GPT-4o mini', value: 'gpt-4o-mini' },
		{ label: 'GPT-4o', value: 'gpt-4o' },
		{ label: 'GPT-5 mini', value: 'gpt-5-mini' },
	];

	const hasKey = !! ( localSettings.api_key || '' ).trim();
	const current = SECTIONS.find( ( item ) => item.key === section ) || SECTIONS[ 0 ];

	return (
		<div className="dsa-panel">
			<SectionHeader
				title={ current.title }
				description={ current.intro }
				badge={
					'openai' === section ? (
						hasKey ? (
							<StatusPill status="success">
								{ __( 'Key added', 'directorist-smart-assistant' ) }
							</StatusPill>
						) : (
							<StatusPill status="warning">
								{ __( 'Key required', 'directorist-smart-assistant' ) }
							</StatusPill>
						)
					) : null
				}
			/>

			<Card>
				<CardBody>

					{ 'openai' === section && (
						<>
							<SecretField
								id="dsa-api-key"
								label={ __( 'OpenAI API Key', 'directorist-smart-assistant' ) }
								value={ localSettings.api_key }
								onChange={ ( value ) => handleChange( 'api_key', value ) }
								placeholder="sk-..."
								help={ __(
									'Create a key at platform.openai.com/api-keys. It is stored on your site and never exposed to visitors.',
									'directorist-smart-assistant'
								) }
							/>

							<Field className="dsa-field--control">
								<SelectControl
									label={ __( 'OpenAI Model', 'directorist-smart-assistant' ) }
									value={ localSettings.model || 'gpt-3.5-turbo' }
									options={ modelOptions }
									onChange={ ( value ) => handleChange( 'model', value ) }
									help={ __(
										'Smaller models answer faster and cost less; larger models reason better.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</Field>

							<Field className="dsa-field--control">
								<TextareaControl
									label={ __( 'System Prompt', 'directorist-smart-assistant' ) }
									value={ localSettings.system_prompt || '' }
									onChange={ ( value ) => handleChange( 'system_prompt', value ) }
									rows={ 6 }
									help={ __(
										'Define the assistant’s persona, tone and boundaries.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</Field>
						</>
					) }

					{ 'tuning' === section && (
						<FieldGrid>
							<Field className="dsa-field--control">
								<RangeControl
									label={ __( 'Temperature', 'directorist-smart-assistant' ) }
									value={ localSettings.temperature ?? 0.7 }
									onChange={ ( value ) => handleChange( 'temperature', parseFloat( value ) ) }
									min={ 0 }
									max={ 1 }
									step={ 0.1 }
									help={ __(
										'Lower values stay focused and deterministic, higher values are more creative.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</Field>

							<Field className="dsa-field--control">
								<TextControl
									label={ __( 'Max Tokens', 'directorist-smart-assistant' ) }
									type="number"
									value={ localSettings.max_tokens || 1000 }
									onChange={ ( value ) => handleChange( 'max_tokens', parseInt( value, 10 ) ) }
									min={ 1 }
									help={ __(
										'Upper limit on the length of a single response.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</Field>
						</FieldGrid>
					) }
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
