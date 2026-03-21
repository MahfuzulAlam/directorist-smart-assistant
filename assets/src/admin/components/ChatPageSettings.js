import { useState } from '@wordpress/element';
import {
	TextControl,
	TextareaControl,
	ToggleControl,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ChatPageSettings({ settings, onSave }) {
	const [local, setLocal] = useState(settings);
	const [saving, setSaving] = useState(false);

	const set = (key, value) => setLocal((prev) => ({ ...prev, [key]: value }));

	const handleSave = async () => {
		setSaving(true);
		try {
			await onSave(local);
		} finally {
			setSaving(false);
		}
	};

	return (
		<div className="chat-module-settings">
			<div className="chat-module-settings__section">
				<h2>{__('Chat Page Settings', 'directorist-smart-assistant')}</h2>
				<p>
					{__('Configure the full-page GPT-style chat experience. Use the shortcode', 'directorist-smart-assistant')}{' '}
					<code>[directorist_smart_chat]</code>{' '}
					{__('to embed it on any page.', 'directorist-smart-assistant')}
				</p>

				<div className="chat-module-settings__field">
					<ToggleControl
						label={__('Enable Page Chat', 'directorist-smart-assistant')}
						checked={!!local.page_chat_enabled}
						onChange={(v) => set('page_chat_enabled', v)}
						help={__('When disabled, the shortcode will render nothing.', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="chat-module-settings__field">
					<TextControl
						label={__('Page Title', 'directorist-smart-assistant')}
						value={local.page_chat_title || ''}
						onChange={(v) => set('page_chat_title', v)}
						placeholder="Chat Assistant"
						help={__('Displayed as the heading inside the chat page.', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="chat-module-settings__field">
					<TextareaControl
						label={__('Welcome Message', 'directorist-smart-assistant')}
						value={local.page_chat_welcome_message || ''}
						onChange={(v) => set('page_chat_welcome_message', v)}
						placeholder={__('Hello! How can I help you today?', 'directorist-smart-assistant')}
						help={__('Shown when a conversation has no messages yet.', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="chat-module-settings__field">
					<TextControl
						label={__('Input Placeholder', 'directorist-smart-assistant')}
						value={local.page_chat_placeholder || ''}
						onChange={(v) => set('page_chat_placeholder', v)}
						placeholder={__('Type a message...', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="chat-module-settings__field">
					<label>{__('Primary Color', 'directorist-smart-assistant')}</label>
					<div style={{ display: 'flex', gap: '10px', alignItems: 'center', marginTop: '8px' }}>
						<input
							type="color"
							value={local.page_chat_primary_color || '#667eea'}
							onChange={(e) => set('page_chat_primary_color', e.target.value)}
							style={{ width: 60, height: 40, border: '1.5px solid #e5e7eb', borderRadius: 8, cursor: 'pointer' }}
						/>
						<input
							type="text"
							value={local.page_chat_primary_color || '#667eea'}
							onChange={(e) => set('page_chat_primary_color', e.target.value)}
							placeholder="#667eea"
							style={{ flex: 1, padding: '10px 14px', border: '1.5px solid #e5e7eb', borderRadius: 8, fontSize: 14, fontFamily: 'inherit' }}
						/>
					</div>
					<p className="description">
						{__('Accent color used for buttons, avatars and focus rings.', 'directorist-smart-assistant')}
					</p>
				</div>

				<div className="chat-module-settings__field">
					<ToggleControl
						label={__('Show Conversation Sidebar', 'directorist-smart-assistant')}
						checked={local.page_chat_show_sidebar !== false && local.page_chat_show_sidebar !== 0}
						onChange={(v) => set('page_chat_show_sidebar', v)}
						help={__('Display the sidebar with conversation history.', 'directorist-smart-assistant')}
					/>
				</div>

				<div className="chat-module-settings__field">
					<ToggleControl
						label={__('Allow Guest Conversations', 'directorist-smart-assistant')}
						checked={!!local.page_chat_guest_enabled}
						onChange={(v) => set('page_chat_guest_enabled', v)}
						help={__('When disabled, only logged-in users can use the page chat.', 'directorist-smart-assistant')}
					/>
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
