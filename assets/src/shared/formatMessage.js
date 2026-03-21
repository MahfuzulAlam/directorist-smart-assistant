/**
 * Shared message formatting utilities.
 *
 * Used by both the chat-widget and the chat-page React apps.
 */

const escapeHtml = (str) => {
	const div = document.createElement('div');
	div.textContent = str;
	return div.innerHTML;
};

/**
 * Process inline markdown (bold, links).
 *
 * @param {string} text Raw text.
 * @return {string} HTML string.
 */
export function processInlineMarkdown(text) {
	if (!text) return '';

	const linkPlaceholders = [];
	let linkIdx = 0;
	let out = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (_m, t, u) => {
		const ph = `__LINK_${linkIdx}__`;
		linkPlaceholders.push({ ph, t, u });
		linkIdx++;
		return ph;
	});

	const boldPlaceholders = [];
	let boldIdx = 0;
	out = out.replace(/\*\*([^*]+)\*\*/g, (_m, b) => {
		const ph = `__BOLD_${boldIdx}__`;
		boldPlaceholders.push({ ph, b });
		boldIdx++;
		return ph;
	});

	out = escapeHtml(out);

	boldPlaceholders.forEach(({ ph, b }) => {
		out = out.replace(escapeHtml(ph), `<strong>${escapeHtml(b)}</strong>`);
	});

	linkPlaceholders.forEach(({ ph, t, u }) => {
		out = out.replace(
			escapeHtml(ph),
			`<a href="${escapeHtml(u)}" target="_blank" rel="noopener noreferrer" class="dsa-chat-link">${escapeHtml(t)}</a>`
		);
	});

	return out;
}

/**
 * Convert markdown-like text to safe HTML.
 *
 * @param {string} text Raw assistant text.
 * @return {string} HTML string.
 */
export function formatMessageContent(text) {
	if (!text) return '';

	const lines = text.split('\n');
	const processed = [];
	let listItems = [];
	let bulletItems = [];

	const flushList = () => {
		if (listItems.length) {
			processed.push(`<ul class="dsa-list-numbered">${listItems.join('')}</ul>`);
			listItems = [];
		}
	};

	const flushBullets = () => {
		if (bulletItems.length) {
			processed.push(`<ul>${bulletItems.join('')}</ul>`);
			bulletItems = [];
		}
	};

	for (const line of lines) {
		const trimmed = line.trim();
		const listMatch = trimmed.match(/^(\d+)[.)]\s+(.+)$/);
		const bulletMatch = trimmed.match(/^[-*]\s+(.+)$/);

		if (listMatch) {
			flushBullets();
			listItems.push(`<li>${processInlineMarkdown(listMatch[2])}</li>`);
		} else if (bulletMatch) {
			flushList();
			bulletItems.push(`<li>${processInlineMarkdown(bulletMatch[1])}</li>`);
		} else {
			flushList();
			flushBullets();
			processed.push(trimmed ? processInlineMarkdown(trimmed) : '');
		}
	}

	flushList();
	flushBullets();

	return processed
		.map((l) => l || '<br>')
		.join('<br>')
		.replace(/(<br>\s*){2,}/g, '<br>');
}
