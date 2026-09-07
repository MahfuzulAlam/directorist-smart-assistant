/**
 * WordPress dependencies
 */
import { createRoot,useState, useRef, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { renderWidgetIcon, DEFAULT_WIDGET_ICON } from '../shared/widget-icons';


/**
 * Styles
 */
import './index.css';

/**
 * Convert markdown-like text to HTML
 * Converts markdown links [text](url) to HTML links
 * Converts bold text **text** to <strong>
 * Converts bullet points (- item) to HTML lists
 * Converts numbered lists to HTML ordered lists
 *
 * @param {string} text The text to convert
 * @return {string} HTML string
 */
function formatMessageContent(text) {
	if (!text) return '';

	// Escape HTML to prevent XSS
	const escapeHtml = (str) => {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	};

	// Split text into lines for better processing
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

	// Process each line
	for (let i = 0; i < lines.length; i++) {
		let line = lines[i];
		const trimmedLine = line.trim();
		
		// Check if line is a numbered list item (1. text or 1) text)
		const listMatch = trimmedLine.match(/^(\d+)[\.\)]\s+(.+)$/);
		
		// Check if line is a bullet point (- text or * text)
		const bulletMatch = trimmedLine.match(/^[-*]\s+(.+)$/);
		
		if (listMatch) {
			// Flush bullet list if we were in one
			if (inBulletList) {
				flushBulletList();
			}

			const itemContent = listMatch[2];
			let processedContent = processInlineMarkdown(itemContent);
			listItems.push(`<li>${processedContent}</li>`);
			inList = true;
		} else if (bulletMatch) {
			// Flush numbered list if we were in one
			if (inList) {
				flushList();
			}

			const itemContent = bulletMatch[1];
			let processedContent = processInlineMarkdown(itemContent);
			bulletItems.push(`<li>${processedContent}</li>`);
			inBulletList = true;
		} else {
			// Flush lists if we were in one
			if (inList) {
				flushList();
			}
			if (inBulletList) {
				flushBulletList();
			}
			
			// Process regular line
			if (trimmedLine) {
				let processedLine = processInlineMarkdown(trimmedLine);
				processedLines.push(processedLine);
			} else {
				processedLines.push('');
			}
		}
	}

	// Flush any remaining lists
	flushList();
	flushBulletList();

	// Join lines with <br> tags
	let joinedLines = processedLines.map(line => line || '<br>').join('<br>');

	// Replace multiple consecutive <br> tags with a single <br>
	const result = joinedLines.replace(/(<br>\s*){2,}/g, '<br>');
	return result;
}

/**
 * Process inline markdown (bold, links) in text
 *
 * @param {string} text The text to process
 * @return {string} HTML string
 */
function processInlineMarkdown(text) {
	if (!text) return '';

	// Escape HTML to prevent XSS
	const escapeHtml = (str) => {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	};

	// Replace markdown links with placeholders first
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

	// Replace bold text **text** with placeholders
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

	// Escape HTML
	processedText = escapeHtml(processedText);

	// Restore bold text
	boldPlaceholders.forEach(({ placeholder, boldText }) => {
		const boldHtml = `<strong>${escapeHtml(boldText)}</strong>`;
		processedText = processedText.replace(escapeHtml(placeholder), boldHtml);
	});

	// Restore links as HTML
	linkPlaceholders.forEach(({ placeholder, linkText, url }) => {
		const linkHtml = `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="directorist-smart-assistant-chat-link">${escapeHtml(linkText)}</a>`;
		processedText = processedText.replace(escapeHtml(placeholder), linkHtml);
	});

	return processedText;
}

/**
 * Chat Widget Component
 */
function ChatWidget() {
	const [isOpen, setIsOpen] = useState(false);
	const [messages, setMessages] = useState([]);
	const [inputValue, setInputValue] = useState('');
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const messagesEndRef = useRef(null);
	const inputRef = useRef(null);

	// Get settings from localized data
	const widgetSettings = window.directoristSmartAssistantChat?.settings || {
		position: 'bottom-right',
		color: '#667eea',
		agentName: '',
		icon: DEFAULT_WIDGET_ICON,
		iconUrl: '',
	};

	// A custom image wins over the preset glyph.
	const iconKey = widgetSettings.icon || DEFAULT_WIDGET_ICON;
	const iconUrl = widgetSettings.iconUrl || '';
	const launcherIcon = iconUrl
		? <img className="directorist-smart-assistant-chat-icon-img" src={iconUrl} alt="" />
		: renderWidgetIcon(iconKey, 24);
	const avatarIcon = iconUrl
		? <img className="directorist-smart-assistant-chat-icon-img" src={iconUrl} alt="" />
		: renderWidgetIcon(iconKey, 18);

	// Get agent name or default
	const agentName = widgetSettings.agentName || 'Smart Assistant';

	// Apply position class
	const positionClass = widgetSettings.position === 'bottom-left' ? 'directorist-smart-assistant-chat-widget--left' : '';

	// Generate CSS variables for color
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

	const handleSend = async () => {
		if (!inputValue.trim() || loading) {
			return;
		}

		const userMessage = inputValue.trim();
		setInputValue('');
		setError(null);

		// Add user message
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

	const handleKeyPress = (e) => {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			handleSend();
		}
	};

	return (
		<div className={`directorist-smart-assistant-chat-widget ${positionClass}`} style={colorStyle}>
			{isOpen && (
				<div className="directorist-smart-assistant-chat-window">
					<div className="directorist-smart-assistant-chat-header">
						<span className="directorist-smart-assistant-chat-avatar" aria-hidden="true">
							{avatarIcon}
						</span>
						<div className="directorist-smart-assistant-chat-identity">
							<h3>{agentName}</h3>
							<p className="directorist-smart-assistant-chat-status">
								<span className="directorist-smart-assistant-chat-status-dot" aria-hidden="true" />
								{loading ? 'Typing…' : 'Online'}
							</p>
						</div>
						<button
							className="directorist-smart-assistant-chat-close"
							onClick={() => setIsOpen(false)}
							aria-label="Close chat"
						>
							<svg
								width="18"
								height="18"
								viewBox="0 0 24 24"
								fill="none"
								xmlns="http://www.w3.org/2000/svg"
							>
								<path
									d="M17 7L7 17M7 7l10 10"
									stroke="currentColor"
									strokeWidth="1.9"
									strokeLinecap="round"
									strokeLinejoin="round"
								/>
							</svg>
						</button>
					</div>

					<div className="directorist-smart-assistant-chat-messages">
						{messages.length === 0 && (
							<div className="directorist-smart-assistant-chat-welcome">
								<span className="directorist-smart-assistant-chat-welcome-icon" aria-hidden="true">
									{iconUrl
										? <img className="directorist-smart-assistant-chat-icon-img" src={iconUrl} alt="" />
										: renderWidgetIcon(iconKey, 26)}
								</span>
								<p className="directorist-smart-assistant-chat-welcome-title">
									{agentName && agentName !== 'Smart Assistant'
										? `Hi, I'm ${agentName}`
										: 'Hi there'}
								</p>
								<p className="directorist-smart-assistant-chat-welcome-text">
									Ask me anything about the listings on this site and I'll point you in the right direction.
								</p>
							</div>
						)}

						{messages.length > 0 && (
							<div className="directorist-smart-assistant-chat-spacer" aria-hidden="true" />
						)}

						{messages.map((message, index) => (
							<div
								key={index}
								className={`directorist-smart-assistant-chat-message directorist-smart-assistant-chat-message--${message.role}`}
							>
								{message.role === 'assistant' && (
									<span className="directorist-smart-assistant-chat-message-avatar" aria-hidden="true">
										{avatarIcon}
									</span>
								)}
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
								<span className="directorist-smart-assistant-chat-message-avatar" aria-hidden="true">
									{avatarIcon}
								</span>
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
							onKeyPress={handleKeyPress}
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
				className={`directorist-smart-assistant-chat-button${isOpen ? ' is-open' : ''}`}
				onClick={() => setIsOpen(!isOpen)}
				aria-label={isOpen ? 'Close chat' : 'Open chat'}
				aria-expanded={isOpen}
			>
				{isOpen ? (
					<svg
						width="24"
						height="24"
						viewBox="0 0 24 24"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
					>
						<path
							d="M17 7L7 17M7 7l10 10"
							stroke="currentColor"
							strokeWidth="2"
							strokeLinecap="round"
							strokeLinejoin="round"
						/>
					</svg>
				) : launcherIcon}
			</button>
		</div>
	);
}

document.addEventListener('DOMContentLoaded', () => {
    // Mount only if the target element exists
    const container = document.getElementById('directorist-smart-assistant-chat-root');
    if (container) {
        const root = createRoot(container);
        root.render(<ChatWidget />);
    }
});
