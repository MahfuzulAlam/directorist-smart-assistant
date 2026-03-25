/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import OpenAISettings from './chat-agent/OpenAISettings';
import ChatModuleSettings from './chat-agent/ChatModuleSettings';
import ChatPageSettings from './chat-agent/ChatPageSettings';

/**
 * Chat Agent Setup Component (Container)
 * 
 * Renders all Chat Agent-related settings sections and the unified Save button.
 */
export default function ChatAgentSetup({ settings, onSave }) {
	const [localSettings, setLocalSettings] = useState(settings);
	const [saving, setSaving] = useState(false);

	useEffect(() => {
		setLocalSettings(settings);
	}, [settings]);

	const handleChange = (patch) => {
		setLocalSettings((prev) => ({
			...prev,
			...patch,
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

	return (
		<div className="daia-admin-card">
			<div className="daia-admin-section">
				<OpenAISettings settings={localSettings} onChange={handleChange} />
			</div>
			<div className="daia-admin-section">
				<ChatModuleSettings settings={localSettings} onChange={handleChange} />
			</div>
			<div className="daia-admin-section">
				<ChatPageSettings settings={localSettings} onChange={handleChange} />
			</div>

			<div className="daia-admin-actions">
				<Button
					variant="primary"
					onClick={handleSave}
					isBusy={saving}
					disabled={saving}
				>
					{__('Save Settings', 'directorist-ai-agents')}
				</Button>
			</div>
		</div>
	);
}

