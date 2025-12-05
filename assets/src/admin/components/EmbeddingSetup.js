/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	TextControl,
	SelectControl,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Embedding Setup Component
 */
export default function EmbeddingSetup({ settings, onSave }) {
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

	const embeddingService = localSettings.embedding_service || 'openai';
	const isOpenAI = embeddingService === 'openai';

	const serviceOptions = [
		{ label: __('OpenAI Embedding', 'directorist-smart-assistant'), value: 'openai' },
	];

	const openaiModelOptions = [
		{ label: __('text-embedding-ada-002', 'directorist-smart-assistant'), value: 'text-embedding-ada-002' },
		{ label: __('text-embedding-3-small', 'directorist-smart-assistant'), value: 'text-embedding-3-small' },
		{ label: __('text-embedding-3-large', 'directorist-smart-assistant'), value: 'text-embedding-3-large' },
	];

	return (
		<div className="embedding-setup">
			{/* Service Selection */}
			<div className="embedding-setup__section">
				<h2>{__('Embedding Service', 'directorist-smart-assistant')}</h2>
				<p>{__('Select the embedding service you want to use for generating vector embeddings.', 'directorist-smart-assistant')}</p>

				<div className="embedding-setup__field">
					<SelectControl
						label={__('Embedding Service', 'directorist-smart-assistant')}
						value={embeddingService}
						options={serviceOptions}
						onChange={(value) => handleChange('embedding_service', value)}
						help={__('Choose the embedding service for generating vector embeddings from text', 'directorist-smart-assistant')}
					/>
				</div>
			</div>

			{/* OpenAI Configuration */}
			{isOpenAI && (
				<div className="embedding-setup__section">
					<h2>{__('OpenAI Configuration', 'directorist-smart-assistant')}</h2>
					<p>{__('Configure your OpenAI API settings for embedding generation.', 'directorist-smart-assistant')}</p>

					<div className="embedding-setup__field">
						<label>
							{__('API Key', 'directorist-smart-assistant')}
						</label>
						<div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
							<TextControl
								type={showApiKey ? 'text' : 'password'}
								value={localSettings.embedding_openai_api_key || ''}
								onChange={(value) => handleChange('embedding_openai_api_key', value)}
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
							{__('Enter your OpenAI API key for embedding generation', 'directorist-smart-assistant')}
						</p>
					</div>

					<div className="embedding-setup__field">
						<SelectControl
							label={__('Embedding Model', 'directorist-smart-assistant')}
							value={localSettings.embedding_openai_model || 'text-embedding-ada-002'}
							options={openaiModelOptions}
							onChange={(value) => handleChange('embedding_openai_model', value)}
							help={__('Select the OpenAI embedding model to use', 'directorist-smart-assistant')}
						/>
					</div>
				</div>
			)}

			{/* Save Button */}
			<div className="embedding-setup__actions">
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
	);
}

