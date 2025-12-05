/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	TextControl,
	ToggleControl,
	Button,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Vector Storage Setup Component
 */
export default function VectorStorageSetup({ settings, onSave }) {
	const [localSettings, setLocalSettings] = useState(settings);
	const [showSecretKey, setShowSecretKey] = useState(false);
	const [saving, setSaving] = useState(false);
	const [bulkSyncing, setBulkSyncing] = useState(false);

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

	const handleBulkSync = async () => {
		setBulkSyncing(true);
		try {
			// TODO: Implement bulk sync functionality
			// For now, just show a message
			alert(__('Bulk sync functionality will be implemented soon.', 'directorist-smart-assistant'));
		} catch (error) {
			console.error('Bulk sync error:', error);
		} finally {
			setBulkSyncing(false);
		}
	};

	const vectorService = localSettings.vector_service || 'wpxplore';
	const isWpXplore = vectorService === 'wpxplore';
	const isPinecone = vectorService === 'pinecone';

	const serviceOptions = [
		{ label: __('WpXplore', 'directorist-smart-assistant'), value: 'wpxplore' },
		{ label: __('Pinecone', 'directorist-smart-assistant'), value: 'pinecone' },
	];

	return (
		<div className="vector-storage-setup">
			{/* Service Selection */}
			<div className="vector-storage-setup__section">
				<h2>{__('Vector Service', 'directorist-smart-assistant')}</h2>
				<p>{__('Select the vector database service you want to use.', 'directorist-smart-assistant')}</p>

				<div className="vector-storage-setup__field">
					<SelectControl
						label={__('Vector Service', 'directorist-smart-assistant')}
						value={vectorService}
						options={serviceOptions}
						onChange={(value) => handleChange('vector_service', value)}
						help={__('Choose the vector database service for storing and querying your listings', 'directorist-smart-assistant')}
					/>
				</div>
			</div>

			{/* API Configuration Section */}
			<div className="vector-storage-setup__section">
				<h2>{__('API Configuration', 'directorist-smart-assistant')}</h2>
				<p>{__('Configure your vector storage API connection settings.', 'directorist-smart-assistant')}</p>

				{isWpXplore && (
					<>
						<div className="vector-storage-setup__field vector-storage-setup__field-api-url">
							<TextControl
								label={__('API Base URL', 'directorist-smart-assistant')}
								value={localSettings.vector_api_base_url || ''}
								onChange={(value) => handleChange('vector_api_base_url', value)}
								placeholder="https://api.example.com"
								help={__('Enter the base URL for your vector storage API', 'directorist-smart-assistant')}
							/>
						</div>

						<div className="vector-storage-setup__field vector-storage-setup__field-secret-key">
							<label>
								{__('API Secret Key', 'directorist-smart-assistant')}
							</label>
							<div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
								<TextControl
									type={showSecretKey ? 'text' : 'password'}
									value={localSettings.vector_api_secret_key || ''}
									onChange={(value) => handleChange('vector_api_secret_key', value)}
									placeholder="Enter your API secret key"
									style={{ flex: 1 }}
								/>
								<Button
									variant="secondary"
									onClick={() => setShowSecretKey(!showSecretKey)}
								>
									{showSecretKey ? __('Hide', 'directorist-smart-assistant') : __('Show', 'directorist-smart-assistant')}
								</Button>
							</div>
							<p className="description">
								{__('Enter your vector storage API secret key for authentication', 'directorist-smart-assistant')}
							</p>
						</div>
					</>
				)}

				{isPinecone && (
					<>
						<div className="vector-storage-setup__field">
							<label>
								{__('API Key', 'directorist-smart-assistant')}
							</label>
							<div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
								<TextControl
									type={showSecretKey ? 'text' : 'password'}
									value={localSettings.pinecone_api_key || ''}
									onChange={(value) => handleChange('pinecone_api_key', value)}
									placeholder="Enter your Pinecone API key"
									style={{ flex: 1 }}
								/>
								<Button
									variant="secondary"
									onClick={() => setShowSecretKey(!showSecretKey)}
								>
									{showSecretKey ? __('Hide', 'directorist-smart-assistant') : __('Show', 'directorist-smart-assistant')}
								</Button>
							</div>
							<p className="description">
								{__('Enter your Pinecone API key for authentication', 'directorist-smart-assistant')}
							</p>
						</div>

						<div className="vector-storage-setup__field">
							<TextControl
								label={__('Environment', 'directorist-smart-assistant')}
								value={localSettings.pinecone_environment || ''}
								onChange={(value) => handleChange('pinecone_environment', value)}
								placeholder="us-east-1-aws"
								help={__('Your Pinecone environment identifier', 'directorist-smart-assistant')}
							/>
						</div>

						<div className="vector-storage-setup__field">
							<TextControl
								label={__('Index Name', 'directorist-smart-assistant')}
								value={localSettings.pinecone_index_name || localSettings.vector_index_name || ''}
								onChange={(value) => {
									handleChange('pinecone_index_name', value);
									handleChange('vector_index_name', value);
								}}
								placeholder="directorist-listings"
								help={__('Name of your Pinecone index', 'directorist-smart-assistant')}
							/>
						</div>
					</>
				)}
			</div>

			{/* Sync Options Section */}
			<div className="vector-storage-setup__section">
				<h2>{__('Sync Options', 'directorist-smart-assistant')}</h2>
				<p>{__('Configure how and when listings are synced to vector storage.', 'directorist-smart-assistant')}</p>

				<div className="vector-storage-setup__field">
					<ToggleControl
						label={__('Auto-sync on Post Save', 'directorist-smart-assistant')}
						checked={localSettings.vector_auto_sync || false}
						onChange={(value) => handleChange('vector_auto_sync', value)}
						help={__('Automatically sync listings to vector storage when they are saved or updated', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="vector-storage-setup__field">
					<label>{__('Bulk Sync', 'directorist-smart-assistant')}</label>
					<div style={{ marginTop: '8px' }}>
						<Button
							variant="secondary"
							onClick={handleBulkSync}
							isBusy={bulkSyncing}
							disabled={bulkSyncing}
						>
							{bulkSyncing ? __('Syncing...', 'directorist-smart-assistant') : __('Sync All Listings', 'directorist-smart-assistant')}
						</Button>
					</div>
					<p className="description">
						{__('Manually sync all existing listings to vector storage', 'directorist-smart-assistant')}
					</p>
				</div>
			</div>

			{/* Advanced Section */}
			<div className="vector-storage-setup__section">
				<h2>{__('Advanced', 'directorist-smart-assistant')}</h2>
				<p>{__('Advanced configuration options for vector storage.', 'directorist-smart-assistant')}</p>

				<div className="vector-storage-setup__field-group">
					<div className="vector-storage-setup__field">
						<TextControl
							label={__('Chunk Size', 'directorist-smart-assistant')}
							type="number"
							value={localSettings.vector_chunk_size || 500}
							onChange={(value) => handleChange('vector_chunk_size', parseInt(value, 10))}
							min={100}
							max={2000}
							step={100}
							help={__('Number of characters per chunk when splitting content', 'directorist-smart-assistant')}
						/>
					</div>

					<div className="vector-storage-setup__field">
						<TextControl
							label={__('Chunk Overlap', 'directorist-smart-assistant')}
							type="number"
							value={localSettings.vector_chunk_overlap || 50}
							onChange={(value) => handleChange('vector_chunk_overlap', parseInt(value, 10))}
							min={0}
							max={200}
							step={10}
							help={__('Number of characters to overlap between chunks', 'directorist-smart-assistant')}
						/>
					</div>
				</div>

				<div className="vector-storage-setup__field">
					<TextControl
						label={__('Embedding Model', 'directorist-smart-assistant')}
						value={localSettings.vector_embedding_model || 'text-embedding-ada-002'}
						onChange={(value) => handleChange('vector_embedding_model', value)}
						help={__('The embedding model to use for vector generation', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="vector-storage-setup__field">
					<TextControl
						label={__('Index Name', 'directorist-smart-assistant')}
						value={localSettings.vector_index_name || 'directorist-listings'}
						onChange={(value) => handleChange('vector_index_name', value)}
						help={__('Name of the vector index/collection', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="vector-storage-setup__field">
					<TextControl
						label={__('Namespace', 'directorist-smart-assistant')}
						value={localSettings.vector_namespace || ''}
						onChange={(value) => handleChange('vector_namespace', value)}
						help={__('Optional namespace for organizing vectors', 'directorist-smart-assistant')}
					/>
				</div>
			</div>

			{/* Save Button */}
			<div className="vector-storage-setup__actions">
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

