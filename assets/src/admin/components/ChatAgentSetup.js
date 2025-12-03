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
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Chat Agent Setup Component
 */
export default function ChatAgentSetup({ settings, onSave }) {
	const [localSettings, setLocalSettings] = useState(settings);
	const [showApiKey, setShowApiKey] = useState(false);
	const [saving, setSaving] = useState(false);

	const handleChange = (key, value) => {
		setLocalSettings((prev) => ({
			...prev,
			[key]: value,
		}));
	};

	const handleSave = async () => {
		setSaving(true);
		try {
			await onSave(localSettings);
		} finally {
			setSaving(false);
		}
	};

	const modelOptions = [
		{ label: 'GPT-3.5 Turbo', value: 'gpt-3.5-turbo' },
		{ label: 'GPT-4', value: 'gpt-4' },
		{ label: 'GPT-4 Turbo', value: 'gpt-4-turbo-preview' },
	];

	return (
		<div className="chat-agent-setup">
			<div className="chat-agent-setup__section">
				<h2>{__('OpenAI Configuration', 'directorist-smart-assistant')}</h2>
				<p>{__('Configure your OpenAI API settings to enable the AI chat assistant.', 'directorist-smart-assistant')}</p>

				<div className="chat-agent-setup__field chat-agent-setup__field-api-key">
					<label>
						{__('OpenAI API Key', 'directorist-smart-assistant')}
					</label>
					<div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
						<TextControl
							type={showApiKey ? 'text' : 'password'}
							value={localSettings.api_key || ''}
							onChange={(value) => handleChange('api_key', value)}
							placeholder="sk-..."
							style={{ flex: 1 }}
						/>
						<Button
							variant="secondary"
							onClick={() => setShowApiKey(!showApiKey)}
						>
							{showApiKey ? __('Hide', 'directorist-smart-assistant') : __('Show', 'directorist-smart-assistant')}
						</Button>
					</div>
					<p className="description">
						{__('Enter your OpenAI API key. Get one at https://platform.openai.com/api-keys', 'directorist-smart-assistant')}
					</p>
				</div>

				<div className="chat-agent-setup__field">
					<SelectControl
						label={__('OpenAI Model', 'directorist-smart-assistant')}
						value={localSettings.model || 'gpt-3.5-turbo'}
						options={modelOptions}
						onChange={(value) => handleChange('model', value)}
					/>
				</div>

				<div className="chat-agent-setup__field">
					<TextareaControl
						label={__('System Prompt', 'directorist-smart-assistant')}
						value={localSettings.system_prompt || ''}
						onChange={(value) => handleChange('system_prompt', value)}
						rows={6}
						help={__('Define the AI assistant\'s behavior and instructions', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="chat-agent-setup__field-group">
					<div className="chat-agent-setup__field">
						<RangeControl
							label={__('Temperature', 'directorist-smart-assistant')}
							value={localSettings.temperature || 0.7}
							onChange={(value) => handleChange('temperature', parseFloat(value))}
							min={0}
							max={1}
							step={0.1}
							help={__('Controls randomness. Lower values make responses more focused and deterministic.', 'directorist-smart-assistant')}
						/>
					</div>

					<div className="chat-agent-setup__field">
						<TextControl
							label={__('Max Tokens', 'directorist-smart-assistant')}
							type="number"
							value={localSettings.max_tokens || 1000}
							onChange={(value) => handleChange('max_tokens', parseInt(value, 10))}
							min={1}
							help={__('Maximum number of tokens in the response', 'directorist-smart-assistant')}
						/>
					</div>
				</div>

				<div className="chat-agent-setup__actions">
					<Button
						variant="primary"
						onClick={handleSave}
						isBusy={saving}
						disabled={saving}
					>
						{__('Save Settings', 'directorist-smart-assistant')}
					</Button>
				</div>
			</div>
		</div>
	);
}

