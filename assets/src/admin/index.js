/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Styles
 */
import './index.css';

/**
 * Internal dependencies
 */
import ChatAgentSetup from './components/ChatAgentSetup';
import VectorStorageSetup from './components/VectorStorageSetup';

/**
 * Admin App Component
 */
function AdminApp() {
	const [settings, setSettings] = useState({
		api_key: '',
		model: 'gpt-3.5-turbo',
		system_prompt: '',
		temperature: 0.7,
		max_tokens: 1000,
		// Vector storage settings
		vector_api_base_url: '',
		vector_api_secret_key: '',
		vector_auto_sync: false,
		vector_chunk_size: 500,
		vector_chunk_overlap: 50,
		vector_embedding_model: 'text-embedding-ada-002',
		vector_index_name: 'directorist-listings',
		vector_namespace: '',
	});
	const [loading, setLoading] = useState(true);
	const [notice, setNotice] = useState(null);

	useEffect(() => {
		loadSettings();
	}, []);

	const loadSettings = async () => {
		try {
			const response = await apiFetch({
				path: 'directorist-smart-assistant/v1/settings',
				method: 'GET',
			});
			setSettings(response);
		} catch (error) {
			showNotice('error', error.message || 'Failed to load settings');
		} finally {
			setLoading(false);
		}
	};

	const showNotice = (type, message) => {
		setNotice({ type, message });
		setTimeout(() => setNotice(null), 5000);
	};

	const handleSave = async (updatedSettings) => {
		try {
			const response = await apiFetch({
				path: 'directorist-smart-assistant/v1/settings',
				method: 'POST',
				data: updatedSettings,
			});

			if (response.success) {
				setSettings(updatedSettings);
				showNotice('success', response.message || 'Settings saved successfully');
			} else {
				showNotice('error', response.message || 'Failed to save settings');
			}
		} catch (error) {
			showNotice('error', error.message || 'Failed to save settings');
		}
	};

	if (loading) {
		return <div className="loading-state">{__('Loading settings...', 'directorist-smart-assistant')}</div>;
	}

	return (
		<div className="directorist-smart-assistant-admin">
			{notice && (
				<Notice
					status={notice.type}
					isDismissible={true}
					onRemove={() => setNotice(null)}
				>
					{notice.message}
				</Notice>
			)}

			<TabPanel
				className="directorist-smart-assistant-tabs"
				activeClass="is-active"
				tabs={[
					{
						name: 'chat-agent',
						title: 'Chat Agent Setup',
					},
					{
						name: 'vector-storage',
						title: 'Vector Storage',
					},
				]}
			>
				{(tab) => (
					<div className="directorist-smart-assistant-tab-content">
						{tab.name === 'chat-agent' && (
							<ChatAgentSetup
								settings={settings}
								onSave={handleSave}
							/>
						)}
						{tab.name === 'vector-storage' && (
							<VectorStorageSetup
								settings={settings}
								onSave={handleSave}
							/>
						)}
					</div>
				)}
			</TabPanel>
		</div>
	);
}

// Render the app
// const root = document.getElementById('directorist-smart-assistant-admin-root');
// if (root) {
// 	render(<AdminApp />, root);
// }

document.addEventListener('DOMContentLoaded', () => {
    // Mount only if the target element exists
    const container = document.getElementById('directorist-smart-assistant-admin-root');
    if (container) {
        const root = createRoot(container);
        root.render(<AdminApp />);
    }
});