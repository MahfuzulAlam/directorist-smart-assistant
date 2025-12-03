/**
 * WordPress dependencies
 */
import { createRoot,useState, useRef, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';


/**
 * Styles
 */
import './index.css';

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
		<div className="directorist-smart-assistant-chat-widget">
			{isOpen && (
				<div className="directorist-smart-assistant-chat-window">
					<div className="directorist-smart-assistant-chat-header">
						<h3>Smart Assistant</h3>
						<button
							className="directorist-smart-assistant-chat-close"
							onClick={() => setIsOpen(false)}
							aria-label="Close chat"
						>
							×
						</button>
					</div>

					<div className="directorist-smart-assistant-chat-messages">
						{messages.length === 0 && (
							<div className="directorist-smart-assistant-chat-welcome">
								<p>Hello! I'm your AI assistant. How can I help you today?</p>
							</div>
						)}

						{messages.map((message, index) => (
							<div
								key={index}
								className={`directorist-smart-assistant-chat-message directorist-smart-assistant-chat-message--${message.role}`}
							>
								<div className="directorist-smart-assistant-chat-message-content">
									{message.content}
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
							onKeyPress={handleKeyPress}
							placeholder="Type your message..."
							rows={2}
							disabled={loading}
						/>
						<button
							className="directorist-smart-assistant-chat-send"
							onClick={handleSend}
							disabled={!inputValue.trim() || loading}
						>
							Send
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
    // Mount only if the target element exists
    const container = document.getElementById('directorist-smart-assistant-chat-root');
    if (container) {
        const root = createRoot(container);
        root.render(<ChatWidget />);
    }
});
