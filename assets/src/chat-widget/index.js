import { createRoot, useState, useRef, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { formatMessageContent } from '../shared/formatMessage';

import './index.css';

const SESSION_KEY = 'daia_session_id';
const CONV_KEY = 'daia_widget_conv_id';

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

function ChatWidget() {
	const [isOpen, setIsOpen] = useState(false);
	const [messages, setMessages] = useState([]);
	const [inputValue, setInputValue] = useState('');
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [conversationId, setConversationId] = useState(() => {
		const stored = sessionStorage.getItem(CONV_KEY);
		return stored ? Number(stored) : null;
	});
	const messagesEndRef = useRef(null);
	const inputRef = useRef(null);

	const sessionId = getSessionId();

	const widgetSettings = window.directoristAIAgentsChat?.settings || {
		position: 'bottom-right',
		color: '#667eea',
		agentName: '',
	};

	const agentName = widgetSettings.agentName || 'AI Agents';
	const positionClass =
		widgetSettings.position === 'bottom-left'
			? 'directorist-smart-assistant-chat-widget--left'
			: '';
	const colorStyle = { '--chat-primary-color': widgetSettings.color };

	const scrollToBottom = () => {
		messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
	};

	useEffect(() => { scrollToBottom(); }, [messages]);

	useEffect(() => {
		if (isOpen && inputRef.current) inputRef.current.focus();
	}, [isOpen]);

	// Load existing conversation from DB when widget opens.
	useEffect(() => {
		if (isOpen && conversationId) {
			apiFetch({
				path: `directorist-ai-agents/v1/conversations/${conversationId}?session_id=${encodeURIComponent(sessionId)}`,
			})
				.then((res) => {
					if (res.messages && res.messages.length) {
						setMessages(
							res.messages
								.filter((m) => m.role !== 'system')
								.map((m) => ({ role: m.role, content: m.content }))
						);
					}
				})
				.catch(() => {
					// Conversation may have been deleted; reset.
					setConversationId(null);
					sessionStorage.removeItem(CONV_KEY);
				});
		}
	}, [isOpen]); // eslint-disable-line react-hooks/exhaustive-deps

	const ensureConversation = async () => {
		if (conversationId) return conversationId;

		const res = await apiFetch({
			path: 'directorist-ai-agents/v1/conversations',
			method: 'POST',
			data: { session_id: sessionId, source: 'widget' },
		});

		if (res.success && res.conversation) {
			const id = Number(res.conversation.id);
			setConversationId(id);
			sessionStorage.setItem(CONV_KEY, id);
			return id;
		}

		throw new Error('Failed to create conversation.');
	};

	const handleSend = async () => {
		if (!inputValue.trim() || loading) return;

		const userMessage = inputValue.trim();
		setInputValue('');
		setError(null);

		const newMessages = [...messages, { role: 'user', content: userMessage }];
		setMessages(newMessages);
		setLoading(true);

		try {
			const convId = await ensureConversation();

			const response = await apiFetch({
				path: `directorist-ai-agents/v1/conversations/${convId}/messages`,
				method: 'POST',
				data: { session_id: sessionId, message: userMessage },
			});

			if (response.success) {
				setMessages([
					...newMessages,
					{ role: 'assistant', content: response.response },
				]);

				// Handle special actions like opening URLs.
				if (response.action === 'open_url' && response.url) {
					setTimeout(() => {
						window.open(response.url, '_blank', 'noopener,noreferrer');
					}, 500);
				}
			} else {
				setError(response.message || 'Failed to get response');
			}
		} catch (err) {
			setError(err.message || 'An error occurred. Please try again.');
		} finally {
			setLoading(false);
		}
	};

	const handleKeyDown = (e) => {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			handleSend();
		}
	};

	return (
		<div
			className={`directorist-smart-assistant-chat-widget ${positionClass}`}
			style={colorStyle}
		>
			{isOpen && (
				<div className="directorist-smart-assistant-chat-window">
					<div className="directorist-smart-assistant-chat-header">
						<h3>{agentName}</h3>
						<button
							className="directorist-smart-assistant-chat-close"
							onClick={() => setIsOpen(false)}
							aria-label="Close chat"
						>
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M12 4L4 12M4 4L12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
							</svg>
						</button>
					</div>

					<div className="directorist-smart-assistant-chat-messages">
						{messages.length === 0 && (
							<div className="directorist-smart-assistant-chat-welcome">
								<p>
									{agentName && agentName !== 'AI Agents'
										? `Hello! I'm ${agentName}, your AI assistant. How can I help you today?`
										: "Hello! I'm your AI assistant. How can I help you today?"}
								</p>
							</div>
						)}

						{messages.map((message, index) => (
							<div
								key={index}
								className={`directorist-smart-assistant-chat-message directorist-smart-assistant-chat-message--${message.role}`}
							>
								<div
									className="directorist-smart-assistant-chat-message-content"
									dangerouslySetInnerHTML={
										message.role === 'assistant'
											? { __html: formatMessageContent(message.content) }
											: undefined
									}
								>
									{message.role === 'user' ? message.content : null}
								</div>
							</div>
						))}

						{loading && (
							<div className="directorist-smart-assistant-chat-message directorist-smart-assistant-chat-message--assistant">
								<div className="directorist-smart-assistant-chat-message-content">
									<div className="directorist-smart-assistant-chat-loading">
										<span /><span /><span />
									</div>
								</div>
							</div>
						)}

						{error && (
							<div className="directorist-smart-assistant-chat-error">{error}</div>
						)}

						<div ref={messagesEndRef} />
					</div>

					<div className="directorist-smart-assistant-chat-input-container">
						<textarea
							ref={inputRef}
							className="directorist-smart-assistant-chat-input"
							value={inputValue}
							onChange={(e) => setInputValue(e.target.value)}
							onKeyDown={handleKeyDown}
							placeholder="Type your message..."
							rows={1}
							disabled={loading}
						/>
						<button
							className="directorist-smart-assistant-chat-send"
							onClick={handleSend}
							disabled={!inputValue.trim() || loading}
							aria-label="Send message"
						>
							<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
							</svg>
						</button>
					</div>
				</div>
			)}

			<button
				className="directorist-smart-assistant-chat-button"
				onClick={() => setIsOpen(!isOpen)}
				aria-label="Open chat"
			>
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="currentColor" />
				</svg>
			</button>
		</div>
	);
}

document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('directorist-ai-agents-chat-root');
	if (container) {
		const root = createRoot(container);
		root.render(<ChatWidget />);
	}
});
