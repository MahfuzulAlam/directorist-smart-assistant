import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { formatMessageContent } from '../../shared/formatMessage';

export default function ChatArea({
	messages,
	loading,
	error,
	onSend,
	welcomeMessage,
	placeholder,
	agentName,
}) {
	const [input, setInput] = useState('');
	const bottomRef = useRef(null);
	const inputRef = useRef(null);

	useEffect(() => {
		bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
	}, [messages, loading]);

	useEffect(() => {
		inputRef.current?.focus();
	}, [messages]);

	const handleSend = () => {
		const trimmed = input.trim();
		if (!trimmed || loading) return;
		setInput('');
		onSend(trimmed);
	};

	const handleKeyDown = (e) => {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			handleSend();
		}
	};

	const defaultWelcome = agentName && agentName !== 'Smart Assistant'
		? `Hello! I'm ${agentName}. How can I help you today?`
		: "Hello! How can I help you today?";

	return (
		<main className="dsa-chat-area">
			<div className="dsa-chat-area__messages">
				{messages.length === 0 && (
					<div className="dsa-chat-area__welcome">
						<div className="dsa-chat-area__welcome-icon">
							<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
								<path d="M20 2H4a2 2 0 00-2 2v18l4-4h14a2 2 0 002-2V4a2 2 0 00-2-2z" />
							</svg>
						</div>
						<h2>{agentName || 'Smart Assistant'}</h2>
						<p>{welcomeMessage || defaultWelcome}</p>
					</div>
				)}

				{messages.map((msg, i) => (
					<div key={i} className={`dsa-chat-area__message dsa-chat-area__message--${msg.role}`}>
						<div className="dsa-chat-area__message-avatar">
							{msg.role === 'assistant' ? (
								<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4a2 2 0 00-2 2v18l4-4h14a2 2 0 002-2V4a2 2 0 00-2-2z" /></svg>
							) : (
								<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z" /></svg>
							)}
						</div>
						<div className="dsa-chat-area__message-body">
							<div className="dsa-chat-area__message-role">
								{msg.role === 'assistant' ? (agentName || 'Assistant') : __('You', 'directorist-smart-assistant')}
							</div>
							<div
								className="dsa-chat-area__message-content"
								dangerouslySetInnerHTML={
									msg.role === 'assistant'
										? { __html: formatMessageContent(msg.content) }
										: undefined
								}
							>
								{msg.role === 'user' ? msg.content : null}
							</div>
						</div>
					</div>
				))}

				{loading && (
					<div className="dsa-chat-area__message dsa-chat-area__message--assistant">
						<div className="dsa-chat-area__message-avatar">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4a2 2 0 00-2 2v18l4-4h14a2 2 0 002-2V4a2 2 0 00-2-2z" /></svg>
						</div>
						<div className="dsa-chat-area__message-body">
							<div className="dsa-chat-area__message-role">{agentName || 'Assistant'}</div>
							<div className="dsa-chat-area__typing">
								<span /><span /><span />
							</div>
						</div>
					</div>
				)}

				{error && <div className="dsa-chat-area__error">{error}</div>}

				<div ref={bottomRef} />
			</div>

			<div className="dsa-chat-area__input-wrap">
				<div className="dsa-chat-area__input-box">
					<textarea
						ref={inputRef}
						value={input}
						onChange={(e) => setInput(e.target.value)}
						onKeyDown={handleKeyDown}
						placeholder={placeholder || __('Type a message...', 'directorist-smart-assistant')}
						rows={1}
						disabled={loading}
					/>
					<button
						className="dsa-chat-area__send-btn"
						onClick={handleSend}
						disabled={!input.trim() || loading}
						aria-label={__('Send', 'directorist-smart-assistant')}
					>
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
							<path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" />
						</svg>
					</button>
				</div>
				<p className="dsa-chat-area__disclaimer">
					{__('AI-generated responses may not always be accurate.', 'directorist-smart-assistant')}
				</p>
			</div>
		</main>
	);
}
