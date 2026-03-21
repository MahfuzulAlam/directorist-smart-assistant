import { createRoot, useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import Sidebar from './components/Sidebar';
import ChatArea from './components/ChatArea';
import './index.css';

const SESSION_KEY = 'dsa_session_id';

function getSessionId() {
	let id = localStorage.getItem(SESSION_KEY);
	if (!id) {
		id =
			typeof crypto !== 'undefined' && crypto.randomUUID
				? crypto.randomUUID()
				: 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
		localStorage.setItem(SESSION_KEY, id);
	}
	return id;
}

function ChatPageApp() {
	const cfg = window.dsaChatPage?.settings || {};
	const sessionId = getSessionId();

	const [conversations, setConversations] = useState([]);
	const [activeConvId, setActiveConvId] = useState(null);
	const [messages, setMessages] = useState([]);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
	const [initialLoad, setInitialLoad] = useState(true);

	const colorStyle = { '--dsa-primary': cfg.primaryColor || '#667eea' };

	const loadConversations = useCallback(async () => {
		try {
			const res = await apiFetch({
				path: `directorist-smart-assistant/v1/conversations?session_id=${encodeURIComponent(sessionId)}&source=page`,
			});
			setConversations(Array.isArray(res) ? res : []);
		} catch {
			/* silently fail */
		} finally {
			setInitialLoad(false);
		}
	}, [sessionId]);

	useEffect(() => {
		loadConversations();
	}, [loadConversations]);

	const loadMessages = useCallback(
		async (convId) => {
			if (!convId) {
				setMessages([]);
				return;
			}
			try {
				const res = await apiFetch({
					path: `directorist-smart-assistant/v1/conversations/${convId}?session_id=${encodeURIComponent(sessionId)}`,
				});
				setMessages(
					(res.messages || []).map((m) => ({
						role: m.role,
						content: m.content,
					}))
				);
			} catch {
				setMessages([]);
			}
		},
		[sessionId]
	);

	useEffect(() => {
		loadMessages(activeConvId);
	}, [activeConvId, loadMessages]);

	const createConversation = async () => {
		try {
			const res = await apiFetch({
				path: 'directorist-smart-assistant/v1/conversations',
				method: 'POST',
				data: { session_id: sessionId, source: 'page' },
			});
			if (res.success && res.conversation) {
				setConversations((prev) => [res.conversation, ...prev]);
				setActiveConvId(res.conversation.id);
				setMessages([]);
				setError(null);
			}
		} catch (err) {
			setError(err.message || __('Failed to create conversation.', 'directorist-smart-assistant'));
		}
	};

	const handleNewChat = () => {
		createConversation();
	};

	const handleSelectConversation = (id) => {
		setActiveConvId(id);
		setError(null);
	};

	const handleDeleteConversation = async (id) => {
		try {
			await apiFetch({
				path: `directorist-smart-assistant/v1/conversations/${id}?session_id=${encodeURIComponent(sessionId)}`,
				method: 'DELETE',
			});
			setConversations((prev) => prev.filter((c) => Number(c.id) !== Number(id)));
			if (Number(activeConvId) === Number(id)) {
				setActiveConvId(null);
				setMessages([]);
			}
		} catch {
			/* ignore */
		}
	};

	const handleSend = async (text) => {
		setError(null);

		let convId = activeConvId;

		// Auto-create conversation on first message if none is active.
		if (!convId) {
			try {
				const res = await apiFetch({
					path: 'directorist-smart-assistant/v1/conversations',
					method: 'POST',
					data: { session_id: sessionId, source: 'page' },
				});
				if (res.success && res.conversation) {
					convId = res.conversation.id;
					setConversations((prev) => [res.conversation, ...prev]);
					setActiveConvId(convId);
				} else {
					setError(__('Failed to create conversation.', 'directorist-smart-assistant'));
					return;
				}
			} catch (err) {
				setError(err.message);
				return;
			}
		}

		const userMsg = { role: 'user', content: text };
		setMessages((prev) => [...prev, userMsg]);
		setLoading(true);

		try {
			const res = await apiFetch({
				path: `directorist-smart-assistant/v1/conversations/${convId}/messages`,
				method: 'POST',
				data: { session_id: sessionId, message: text },
			});

			if (res.success) {
				setMessages((prev) => [...prev, { role: 'assistant', content: res.response }]);
				// Refresh sidebar to update title/timestamp.
				loadConversations();
			} else {
				setError(res.message || __('Failed to get response.', 'directorist-smart-assistant'));
			}
		} catch (err) {
			setError(err.message || __('An error occurred.', 'directorist-smart-assistant'));
		} finally {
			setLoading(false);
		}
	};

	if (initialLoad) {
		return (
			<div className="dsa-chat-page" style={colorStyle}>
				<div className="dsa-chat-page__loading">
					<div className="dsa-chat-page__spinner" />
				</div>
			</div>
		);
	}

	const showSidebar = cfg.showSidebar !== false;

	return (
		<div className="dsa-chat-page" style={colorStyle}>
			{showSidebar && (
				<Sidebar
					conversations={conversations}
					activeId={activeConvId}
					onSelect={handleSelectConversation}
					onNew={handleNewChat}
					onDelete={handleDeleteConversation}
					collapsed={sidebarCollapsed}
					onToggle={() => setSidebarCollapsed(!sidebarCollapsed)}
				/>
			)}
			<ChatArea
				messages={messages}
				loading={loading}
				error={error}
				onSend={handleSend}
				welcomeMessage={cfg.welcomeMessage}
				placeholder={cfg.placeholder}
				agentName={cfg.agentName}
			/>
		</div>
	);
}

document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('dsa-chat-page-root');
	if (container) {
		const root = createRoot(container);
		root.render(<ChatPageApp />);
	}
});
