/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import {
	TextControl,
	ToggleControl,
	Button,
	CheckboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Vector Storage Setup Component
 */
export default function VectorStorageSetup({ settings, onSave }) {
	const [localSettings, setLocalSettings] = useState(settings);
	const [showSecretKey, setShowSecretKey] = useState(false);
	const [saving, setSaving] = useState(false);
	const [bulkSyncing, setBulkSyncing] = useState(false);
	const [directoryTypes, setDirectoryTypes] = useState([]);
	const [listingStatuses, setListingStatuses] = useState([]);
	const [loadingOptions, setLoadingOptions] = useState(true);

	useEffect(() => {
		loadOptions();
	}, []);

	useEffect(() => {
		setLocalSettings(settings);
	}, [settings]);

	const loadOptions = async () => {
		setLoadingOptions(true);
		try {
			const [typesResponse, statusesResponse] = await Promise.all([
				apiFetch({ path: 'directorist-smart-assistant/v1/directory-types' }),
				apiFetch({ path: 'directorist-smart-assistant/v1/listing-statuses' }),
			]);
			setDirectoryTypes(typesResponse || []);
			setListingStatuses(statusesResponse || []);
		} catch (error) {
			console.error('Error loading options:', error);
		} finally {
			setLoadingOptions(false);
		}
	};

	const handleChange = (key, value) => {
		setLocalSettings((prev) => ({
			...prev,
			[key]: value,
		}));
	};

	const handleCheckboxChange = (key, value, checked) => {
		setLocalSettings((prev) => {
			const currentArray = prev[key] || [];
			let newArray;
			if (checked) {
				newArray = [...currentArray, value];
			} else {
				newArray = currentArray.filter((item) => item !== value);
			}
			return {
				...prev,
				[key]: newArray,
			};
		});
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
		if (!confirm(__('Are you sure you want to sync all listings? This may take a while.', 'directorist-smart-assistant'))) {
			return;
		}

		setBulkSyncing(true);
		try {
			const response = await apiFetch({
				path: 'directorist-smart-assistant/v1/bulk-sync',
				method: 'POST',
				data: {
					post_ids: [], // Empty array means sync all listings based on settings
				},
			});

			if (response.success) {
				const message = response.message || __('Bulk sync completed successfully!', 'directorist-smart-assistant');
				if (response.results && response.results.errors && response.results.errors.length > 0) {
					console.warn('Bulk sync errors:', response.results.errors);
				}
				alert(message);
			} else {
				alert(response.message || __('Bulk sync failed. Please try again.', 'directorist-smart-assistant'));
			}
		} catch (error) {
			console.error('Bulk sync error:', error);
			alert(error.message || __('An error occurred during bulk sync. Please check the console for details.', 'directorist-smart-assistant'));
		} finally {
			setBulkSyncing(false);
		}
	};

	return (
		<div className="vector-storage-setup">
			{/* API Configuration Section */}
			<div className="vector-storage-setup__section">
				<h2>{__('API Configuration', 'directorist-smart-assistant')}</h2>
				<p>{__('Configure your vector storage API connection settings.', 'directorist-smart-assistant')}</p>

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

				<div className="vector-storage-setup__field vector-storage-setup__field-website-id">
					<TextControl
						label={__('Website ID', 'directorist-smart-assistant')}
						value={localSettings.vector_website_id || ''}
						onChange={(value) => handleChange('vector_website_id', value)}
						placeholder="Enter your website ID"
						help={__('Enter your website ID that will be sent as X-Website-ID header in API calls', 'directorist-smart-assistant')}
					/>
				</div>
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

				<div className="vector-storage-setup__field">
					<TextControl
						label={__('Listing Chunk Size', 'directorist-smart-assistant')}
						type="number"
						value={localSettings.vector_listing_chunk_size || 20}
						onChange={(value) => handleChange('vector_listing_chunk_size', parseInt(value, 10))}
						min={1}
						max={100}
						step={1}
						help={__('Number of listings to send per batch during bulk sync', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="vector-storage-setup__field">
					<label>{__('Directory Types', 'directorist-smart-assistant')}</label>
					{loadingOptions ? (
						<p>{__('Loading directory types...', 'directorist-smart-assistant')}</p>
					) : directoryTypes.length > 0 ? (
						<div style={{ marginTop: '8px', maxHeight: '200px', overflowY: 'auto', border: '1px solid #e5e7eb', borderRadius: '8px', padding: '12px' }}>
							{directoryTypes.map((type) => (
								<CheckboxControl
									key={type.id}
									label={type.name}
									checked={(localSettings.vector_sync_directory_types || []).includes(type.id)}
									onChange={(checked) => handleCheckboxChange('vector_sync_directory_types', type.id, checked)}
								/>
							))}
						</div>
					) : (
						<p className="description">{__('No directory types found.', 'directorist-smart-assistant')}</p>
					)}
					<p className="description">
						{__('Select which directory types should be synced to vector storage. Leave empty to sync all types.', 'directorist-smart-assistant')}
					</p>
				</div>

				<div className="vector-storage-setup__field">
					<label>{__('Listing Status', 'directorist-smart-assistant')}</label>
					{loadingOptions ? (
						<p>{__('Loading listing statuses...', 'directorist-smart-assistant')}</p>
					) : listingStatuses.length > 0 ? (
						<div style={{ marginTop: '8px', maxHeight: '200px', overflowY: 'auto', border: '1px solid #e5e7eb', borderRadius: '8px', padding: '12px' }}>
							{listingStatuses.map((status) => (
								<CheckboxControl
									key={status.value}
									label={status.label}
									checked={(localSettings.vector_sync_listing_statuses || []).includes(status.value)}
									onChange={(checked) => handleCheckboxChange('vector_sync_listing_statuses', status.value, checked)}
								/>
							))}
						</div>
					) : (
						<p className="description">{__('No listing statuses found.', 'directorist-smart-assistant')}</p>
					)}
					<p className="description">
						{__('Select which listing statuses should be synced to vector storage. Leave empty to sync all statuses.', 'directorist-smart-assistant')}
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

