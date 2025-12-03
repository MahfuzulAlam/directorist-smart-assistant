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
 * Chat Module Settings Component
 */
export default function ChatModuleSettings({ settings, onSave }) {
	const [localSettings, setLocalSettings] = useState(settings);
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

	const positionOptions = [
		{ label: __('Bottom Right', 'directorist-smart-assistant'), value: 'bottom-right' },
		{ label: __('Bottom Left', 'directorist-smart-assistant'), value: 'bottom-left' },
	];

	return (
		<div className="chat-module-settings">
			<div className="chat-module-settings__section">
				<h2>{__('Chat Widget Configuration', 'directorist-smart-assistant')}</h2>
				<p>{__('Customize the appearance and position of the chat widget on your website.', 'directorist-smart-assistant')}</p>

				<div className="chat-module-settings__field">
					<TextControl
						label={__('Chat Agent Name', 'directorist-smart-assistant')}
						value={localSettings.chat_agent_name || ''}
						onChange={(value) => handleChange('chat_agent_name', value)}
						placeholder={__('e.g., Assistant, Helper, Support', 'directorist-smart-assistant')}
						help={__('Enter a name for your AI chat agent. This name will be used in the system prompt to help the AI identify itself and provide a personalized experience to users.', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="chat-module-settings__field">
					<SelectControl
						label={__('Chat Widget Position', 'directorist-smart-assistant')}
						value={localSettings.chat_widget_position || 'bottom-right'}
						options={positionOptions}
						onChange={(value) => handleChange('chat_widget_position', value)}
						help={__('Choose where the chat widget button should appear on your website. The widget will be positioned at the selected corner of the screen.', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="chat-module-settings__field">
					<label>
						{__('Chat Widget Color', 'directorist-smart-assistant')}
					</label>
					<div style={{ display: 'flex', gap: '10px', alignItems: 'center', marginTop: '8px' }}>
						<input
							type="color"
							value={localSettings.chat_widget_color || '#667eea'}
							onChange={(e) => handleChange('chat_widget_color', e.target.value)}
							style={{
								width: '60px',
								height: '40px',
								border: '1.5px solid #e5e7eb',
								borderRadius: '8px',
								cursor: 'pointer',
							}}
						/>
						<input
							type="text"
							value={localSettings.chat_widget_color || '#667eea'}
							onChange={(e) => handleChange('chat_widget_color', e.target.value)}
							placeholder="#667eea"
							style={{
								flex: 1,
								padding: '10px 14px',
								border: '1.5px solid #e5e7eb',
								borderRadius: '8px',
								fontSize: '14px',
								fontFamily: 'inherit',
							}}
						/>
					</div>
					<p className="description">
						{__('Select the primary color theme for the chat widget. This color will be used for the chat button, header, and user messages. You can use the color picker or enter a hex color code.', 'directorist-smart-assistant')}
					</p>
				</div>

				<div className="chat-module-settings__actions">
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

