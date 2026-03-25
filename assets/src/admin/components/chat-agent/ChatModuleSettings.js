/**
 * WordPress dependencies
 */
import {
	TextControl,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Chat Module Settings Component
 */
export default function ChatModuleSettings({ settings, onChange }) {
	const handleChange = (key, value) => {
		onChange({ [key]: value });
	};

	const positionOptions = [
		{ label: __('Bottom Right', 'directorist-ai-agents'), value: 'bottom-right' },
		{ label: __('Bottom Left', 'directorist-ai-agents'), value: 'bottom-left' },
	];

	return (
		<>
			<h2>{__('Chat Widget Configuration', 'directorist-ai-agents')}</h2>
			<p>{__('Customize the appearance and position of the chat widget on your website.', 'directorist-ai-agents')}</p>

			<div className="daia-admin-field">
				<TextControl
					label={__('Chat Agent Name', 'directorist-ai-agents')}
					value={settings.chat_agent_name || ''}
					onChange={(value) => handleChange('chat_agent_name', value)}
					placeholder={__('e.g., Assistant, Helper, Support', 'directorist-ai-agents')}
					help={__('Enter a name for your AI chat agent. This name will be used in the system prompt to help the AI identify itself and provide a personalized experience to users.', 'directorist-ai-agents')}
				/>
			</div>

			<div className="daia-admin-field">
				<SelectControl
					label={__('Chat Widget Position', 'directorist-ai-agents')}
					value={settings.chat_widget_position || 'bottom-right'}
					options={positionOptions}
					onChange={(value) => handleChange('chat_widget_position', value)}
					help={__('Choose where the chat widget button should appear on your website. The widget will be positioned at the selected corner of the screen.', 'directorist-ai-agents')}
				/>
			</div>

			<div className="daia-admin-field">
				<label>{__('Chat Widget Color', 'directorist-ai-agents')}</label>
				<div style={{ display: 'flex', gap: '10px', alignItems: 'center', marginTop: '8px' }}>
					<input
						type="color"
						value={settings.chat_widget_color || '#667eea'}
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
						value={settings.chat_widget_color || '#667eea'}
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
					{__('Select the primary color theme for the chat widget. This color will be used for the chat button, header, and user messages. You can use the color picker or enter a hex color code.', 'directorist-ai-agents')}
				</p>
			</div>

		</>
	);
}

