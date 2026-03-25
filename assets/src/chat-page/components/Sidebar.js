import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

function groupByDate(conversations) {
	const now = new Date();
	const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
	const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
	const weekAgo = new Date(today); weekAgo.setDate(today.getDate() - 7);

	const groups = { today: [], yesterday: [], week: [], older: [] };

	for (const c of conversations) {
		const d = new Date(c.updated_at);
		if (d >= today) groups.today.push(c);
		else if (d >= yesterday) groups.yesterday.push(c);
		else if (d >= weekAgo) groups.week.push(c);
		else groups.older.push(c);
	}
	return groups;
}

export default function Sidebar({
	conversations,
	activeId,
	onSelect,
	onNew,
	onDelete,
	collapsed,
	onToggle,
}) {
	const [editingId, setEditingId] = useState(null);
	const groups = groupByDate(conversations);

	const renderGroup = (label, items) => {
		if (!items.length) return null;
		return (
			<div className="daia-sidebar__group" key={label}>
				<div className="daia-sidebar__group-label">{label}</div>
				{items.map((c) => (
					<div
						key={c.id}
						className={`daia-sidebar__item ${Number(c.id) === Number(activeId) ? 'daia-sidebar__item--active' : ''}`}
						onClick={() => onSelect(c.id)}
						role="button"
						tabIndex={0}
						onKeyDown={(e) => e.key === 'Enter' && onSelect(c.id)}
					>
						<span className="daia-sidebar__item-title">
							{c.title || __('New Chat', 'directorist-ai-agents')}
						</span>
						<button
							className="daia-sidebar__item-delete"
							onClick={(e) => {
								e.stopPropagation();
								onDelete(c.id);
							}}
							aria-label={__('Delete', 'directorist-ai-agents')}
						>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
								<path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14" />
							</svg>
						</button>
					</div>
				))}
			</div>
		);
	};

	return (
		<aside className={`daia-sidebar ${collapsed ? 'daia-sidebar--collapsed' : ''}`}>
			<div className="daia-sidebar__header">
				<button className="daia-sidebar__new-btn" onClick={onNew}>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
						<path d="M12 5v14M5 12h14" />
					</svg>
					{!collapsed && __('New Chat', 'directorist-ai-agents')}
				</button>
				<button className="daia-sidebar__toggle" onClick={onToggle} aria-label="Toggle sidebar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
						<path d="M3 12h18M3 6h18M3 18h18" />
					</svg>
				</button>
			</div>

			{!collapsed && (
				<div className="daia-sidebar__list">
					{conversations.length === 0 && (
						<div className="daia-sidebar__empty">
							{__('No conversations yet', 'directorist-ai-agents')}
						</div>
					)}
					{renderGroup(__('Today', 'directorist-ai-agents'), groups.today)}
					{renderGroup(__('Yesterday', 'directorist-ai-agents'), groups.yesterday)}
					{renderGroup(__('Previous 7 Days', 'directorist-ai-agents'), groups.week)}
					{renderGroup(__('Older', 'directorist-ai-agents'), groups.older)}
				</div>
			)}
		</aside>
	);
}
