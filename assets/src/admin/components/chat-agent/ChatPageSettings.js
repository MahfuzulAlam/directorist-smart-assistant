import {
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ChatPageSettings({ settings, onChange }) {
	const set = (key, value) => {
		onChange({ [key]: value });
	};

	return (
		<>
			<h2>{__('Chat Page Settings', 'directorist-ai-agents')}</h2>
			<p>
				{__('Configure the full-page GPT-style chat experience. Use the shortcode', 'directorist-ai-agents')}{' '}
				<code>[directorist_chat_agent]</code>{' '}
				{__('to embed it on any page.', 'directorist-ai-agents')}
			</p>

			<div className="daia-admin-field">
				<ToggleControl
					label={__('Enable Page Chat', 'directorist-ai-agents')}
					checked={!!settings.page_chat_enabled}
					onChange={(v) => set('page_chat_enabled', v)}
					help={__('When disabled, the shortcode will render nothing.', 'directorist-ai-agents')}
				/>
			</div>

			<div className="daia-admin-field">
				<TextControl
					label={__('Page Title', 'directorist-ai-agents')}
					value={settings.page_chat_title || ''}
					onChange={(v) => set('page_chat_title', v)}
					placeholder="AI Agents"
					help={__('Displayed as the heading inside the chat page.', 'directorist-ai-agents')}
				/>
			</div>

			<div className="daia-admin-field">
				<TextareaControl
					label={__('Welcome Message', 'directorist-ai-agents')}
					value={settings.page_chat_welcome_message || ''}
					onChange={(v) => set('page_chat_welcome_message', v)}
					placeholder={__('Hello! How can I help you today?', 'directorist-ai-agents')}
					help={__('Shown when a conversation has no messages yet.', 'directorist-ai-agents')}
				/>
			</div>

			<div className="daia-admin-field">
				<TextControl
					label={__('Input Placeholder', 'directorist-ai-agents')}
					value={settings.page_chat_placeholder || ''}
					onChange={(v) => set('page_chat_placeholder', v)}
					placeholder={__('Type a message...', 'directorist-ai-agents')}
				/>
			</div>

			<div className="daia-admin-field">
				<label>{__('Primary Color', 'directorist-ai-agents')}</label>
				<div style={{ display: 'flex', gap: '10px', alignItems: 'center', marginTop: '8px' }}>
					<input
						type="color"
						value={settings.page_chat_primary_color || '#667eea'}
						onChange={(e) => set('page_chat_primary_color', e.target.value)}
						style={{ width: 60, height: 40, border: '1.5px solid #e5e7eb', borderRadius: 8, cursor: 'pointer' }}
					/>
					<input
						type="text"
						value={settings.page_chat_primary_color || '#667eea'}
						onChange={(e) => set('page_chat_primary_color', e.target.value)}
						placeholder="#667eea"
						style={{ flex: 1, padding: '10px 14px', border: '1.5px solid #e5e7eb', borderRadius: 8, fontSize: 14, fontFamily: 'inherit' }}
					/>
				</div>
				<p className="description">
					{__('Accent color used for buttons, avatars and focus rings.', 'directorist-ai-agents')}
				</p>
			</div>

			<div className="daia-admin-field">
				<ToggleControl
					label={__('Show Conversation Sidebar', 'directorist-ai-agents')}
					checked={settings.page_chat_show_sidebar !== false && settings.page_chat_show_sidebar !== 0}
					onChange={(v) => set('page_chat_show_sidebar', v)}
					help={__('Display the sidebar with conversation history.', 'directorist-ai-agents')}
				/>
			</div>

			<div className="daia-admin-field">
				<ToggleControl
					label={__('Allow Guest Conversations', 'directorist-ai-agents')}
					checked={!!settings.page_chat_guest_enabled}
					onChange={(v) => set('page_chat_guest_enabled', v)}
					help={__('When disabled, only logged-in users can use the page chat.', 'directorist-ai-agents')}
				/>
			</div>
		</>
	);
}
