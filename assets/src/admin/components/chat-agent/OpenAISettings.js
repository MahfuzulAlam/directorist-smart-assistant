/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	TextControl,
	SelectControl,
	TextareaControl,
	RangeControl,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * OpenAI Settings Component
 */
export default function OpenAISettings({ settings, onChange }) {
	const [showApiKey, setShowApiKey] = useState(false);
	const handleChange = (key, value) => {
		onChange({ [key]: value });
	};

	const modelOptions = [
		{ label: 'GPT-3.5 Turbo', value: 'gpt-3.5-turbo' },
		{ label: 'GPT-4', value: 'gpt-4' },
		{ label: 'GPT-4 Turbo', value: 'gpt-4-turbo-preview' },
		{ label: 'GPT-4o mini', value: 'gpt-4o-mini' },
		{ label: 'GPT-4o', value: 'gpt-4o' },
		{ label: 'GPT-5 mini', value: 'gpt-5-mini' },
	];

	return (
		<>
			<h2>{__('OpenAI Configuration', 'directorist-ai-agents')}</h2>
			<p>{__('Configure your OpenAI API settings to enable the AI chat assistant.', 'directorist-ai-agents')}</p>

			<div className="daia-admin-field daia-admin-field--api-key">
				<label>{__('OpenAI API Key', 'directorist-ai-agents')}</label>
				<div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
					<TextControl
						type={showApiKey ? 'text' : 'password'}
						value={settings.api_key || ''}
						onChange={(value) => handleChange('api_key', value)}
						placeholder="sk-..."
						style={{ flex: 1 }}
					/>
					<Button variant="secondary" onClick={() => setShowApiKey(!showApiKey)}>
						{showApiKey ? __('Hide', 'directorist-ai-agents') : __('Show', 'directorist-ai-agents')}
					</Button>
				</div>
				<p className="description">
					{__('Enter your OpenAI API key. Get one at https://platform.openai.com/api-keys', 'directorist-ai-agents')}
				</p>
			</div>

			<div className="daia-admin-field">
				<SelectControl
					label={__('OpenAI Model', 'directorist-ai-agents')}
					value={settings.model || 'gpt-3.5-turbo'}
					options={modelOptions}
					onChange={(value) => handleChange('model', value)}
				/>
			</div>

			<div className="daia-admin-field">
				<TextareaControl
					label={__('System Prompt', 'directorist-ai-agents')}
					value={settings.system_prompt || ''}
					onChange={(value) => handleChange('system_prompt', value)}
					rows={6}
					help={__("Define the AI assistant's behavior and instructions", 'directorist-ai-agents')}
				/>
			</div>

			<div className="daia-admin-field-group">
				<div className="daia-admin-field">
					<RangeControl
						label={__('Temperature', 'directorist-ai-agents')}
						value={settings.temperature || 0.7}
						onChange={(value) => handleChange('temperature', parseFloat(value))}
						min={0}
						max={1}
						step={0.1}
						help={__('Controls randomness. Lower values make responses more focused and deterministic.', 'directorist-ai-agents')}
					/>
				</div>

				<div className="daia-admin-field">
					<TextControl
						label={__('Max Tokens', 'directorist-ai-agents')}
						type="number"
						value={settings.max_tokens || 1000}
						onChange={(value) => handleChange('max_tokens', parseInt(value, 10))}
						min={1}
						help={__('Maximum number of tokens in the response', 'directorist-ai-agents')}
					/>
				</div>
			</div>
		</>
	);
}

