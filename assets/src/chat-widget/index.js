/**
 * WordPress dependencies
 */
import { createRoot, useState, useRef, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Styles
 */
import './index.css';

const STORAGE_KEY = 'dsa_chat_conversation';

/**
 * Load persisted conversation from sessionStorage.
 *
 * @return {Array} Saved messages or empty array.
 */
function loadConversation() {
	try {
		const raw = sessionStorage.getItem(STORAGE_KEY);
		return raw ? JSON.parse(raw) : [];
	} catch {
		return [];
	}
}

/**
 * Persist conversation to sessionStorage.
 *
 * @param {Array} messages Messages to persist.
 */
function saveConversation(messages) {
	try {
		sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages));
	} catch {
		// Storage full or unavailable — silently ignore.
	}
}

/**
 * Convert markdown-like text to HTML.
 *
 * @param {string} text The text to convert.
 * @return {string} HTML string.
 */
function formatMessageContent(text) {
	if (!text) return '';

	const escapeHtml = (str) => {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	};

	const lines = text.split('\n');
	const processedLines = [];
	let inList = false;
	let listItems = [];
	let inBulletList = false;
	let bulletItems = [];

	const flushList = () => {
		if (listItems.length > 0) {
			processedLines.push(`<ul class="dsa-list-numbered">${listItems.join('')}</ul>`);
			listItems = [];
		}
		inList = false;
	};

	const flushBulletList = () => {
		if (bulletItems.length > 0) {
			processedLines.push(`<ul>${bulletItems.join('')}</ul>`);
			bulletItems = [];
		}
		inBulletList = false;
	};

	for (let i = 0; i < lines.length; i++) {
		const trimmedLine = lines[i].trim();

		const listMatch = trimmedLine.match(/^(\d+)[.)]\s+(.+)$/);
		const bulletMatch = trimmedLine.match(/^[-*]\s+(.+)$/);

		if (listMatch) {
			if (inBulletList) flushBulletList();
			listItems.push(`<li>${processInlineMarkdown(listMatch[2])}</li>`);
			inList = true;
		} else if (bulletMatch) {
			if (inList) flushList();
			bulletItems.push(`<li>${processInlineMarkdown(bulletMatch[1])}</li>`);
			inBulletList = true;
		} else {
			if (inList) flushList();
			if (inBulletList) flushBulletList();

			if (trimmedLine) {
				processedLines.push(processInlineMarkdown(trimmedLine));
			} else {
				processedLines.push('');
			}
		}
	}

	flushList();
	flushBulletList();

	let joinedLines = processedLines.map((line) => line || '<br>').join('<br>');
	return joinedLines.replace(/(<br>\s*){2,}/g, '<br>');
}

/**
 * Process inline markdown (bold, links) in text.
 *
 * @param {string} text The text to process.
 * @return {string} HTML string.
 */
function processInlineMarkdown(text) {
	if (!text) return '';

	const escapeHtml = (str) => {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	};

	const linkPlaceholders = [];
	let linkIndex = 0;
	let processedText = text.replace(
		/\[([^\]]+)\]\(([^)]+)\)/g,
		(match, linkText, url) => {
			const placeholder = `__LINK_${linkIndex}__`;
			linkPlaceholders.push({ placeholder, linkText, url });
			linkIndex++;
			return placeholder;
		}
	);

	const boldPlaceholders = [];
	let boldIndex = 0;
	processedText = processedText.replace(
		/\*\*([^*]+)\*\*/g,
		(match, boldText) => {
			const placeholder = `__BOLD_${boldIndex}__`;
			boldPlaceholders.push({ placeholder, boldText });
			boldIndex++;
			return placeholder;
		}
	);

	processedText = escapeHtml(processedText);

	boldPlaceholders.forEach(({ placeholder, boldText }) => {
		processedText = processedText.replace(
			escapeHtml(placeholder),
			`<strong>${escapeHtml(boldText)}</strong>`
		);
	});

	linkPlaceholders.forEach(({ placeholder, linkText, url }) => {
		processedText = processedText.replace(
			escapeHtml(placeholder),
			`<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="directorist-smart-assistant-chat-link">${escapeHtml(linkText)}</a>`
		);
	});

	return processedText;
}

/**
 * Chat Widget Component
 */
function ChatWidget() {
	const [isOpen, setIsOpen] = useState(false);
	const [messages, setMessages] = useState(() => loadConversation());
	const [inputValue, setInputValue] = useState('');
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const messagesEndRef = useRef(null);
	const inputRef = useRef(null);

	const widgetSettings = window.directoristSmartAssistantChat?.settings || {
		position: 'bottom-right',
		color: '#667eea',
		agentName: '',
	};

	const agentName = widgetSettings.agentName || 'Smart Assistant';
	const positionClass =
		widgetSettings.position === 'bottom-left'
			? 'directorist-smart-assistant-chat-widget--left'
			: '';

	const colorStyle = {
		'--chat-primary-color': widgetSettings.color,
	};

	const scrollToBottom = () => {
		messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
	};

	useEffect(() => {
		scrollToBottom();
	}, [messages]);

	useEffect(() => {
		if (isOpen && inputRef.current) {
			inputRef.current.focus();
		}
	}, [isOpen]);

	// Persist conversation whenever messages change.
	useEffect(() => {
		saveConversation(messages);
	}, [messages]);

	const handleSend = async () => {
		if (!inputValue.trim() || loading) {
			return;
		}

		const userMessage = inputValue.trim();
		setInputValue('');
		setError(null);

		const newMessages = [
			...messages,
			{ role: 'user', content: userMessage },
		];
		setMessages(newMessages);
		setLoading(true);

		try {
			const response = await apiFetch({
				path: 'directorist-smart-assistant/v1/chat',
				method: 'POST',
				data: {
					message: userMessage,
					conversation: messages,
				},
			});

			if (response.success) {
				setMessages([
					...newMessages,
					{ role: 'assistant', content: response.response },
				]);
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
							<svg
								width="16"
								height="16"
								viewBox="0 0 16 16"
								fill="none"
								xmlns="http://www.w3.org/2000/svg"
							>
								<path
									d="M12 4L4 12M4 4L12 12"
									stroke="currentColor"
									strokeWidth="2"
									strokeLinecap="round"
									strokeLinejoin="round"
								/>
							</svg>
						</button>
					</div>

					<div className="directorist-smart-assistant-chat-messages">
						{messages.length === 0 && (
							<div className="directorist-smart-assistant-chat-welcome">
								<p>
									{agentName && agentName !== 'Smart Assistant'
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
											? {
													__html: formatMessageContent(
														message.content
													),
												}
											: undefined
									}
								>
									{message.role === 'user'
										? message.content
										: null}
								</div>
							</div>
						))}

						{loading && (
							<div className="directorist-smart-assistant-chat-message directorist-smart-assistant-chat-message--assistant">
								<div className="directorist-smart-assistant-chat-message-content">
									<div className="directorist-smart-assistant-chat-loading">
										<span></span>
										<span></span>
										<span></span>
									</div>
								</div>
							</div>
						)}

						{error && (
							<div className="directorist-smart-assistant-chat-error">
								{error}
							</div>
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
							<svg
								width="20"
								height="20"
								viewBox="0 0 20 20"
								fill="none"
								xmlns="http://www.w3.org/2000/svg"
							>
								<path
									d="M18 2L9 11M18 2L12 18L9 11M18 2L2 8L9 11"
									stroke="currentColor"
									strokeWidth="2"
									strokeLinecap="round"
									strokeLinejoin="round"
								/>
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
				<svg
					width="24"
					height="24"
					viewBox="0 0 24 24"
					fill="none"
					xmlns="http://www.w3.org/2000/svg"
				>
					<path
						d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z"
						fill="currentColor"
					/>
				</svg>
			</button>
		</div>
	);
}

document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById(
		'directorist-smart-assistant-chat-root'
	);
	if (container) {
		const root = createRoot(container);
		root.render(<ChatWidget />);
	}
});
