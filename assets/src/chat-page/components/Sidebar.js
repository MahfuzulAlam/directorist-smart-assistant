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
			<div className="dsa-sidebar__group" key={label}>
				<div className="dsa-sidebar__group-label">{label}</div>
				{items.map((c) => (
					<div
						key={c.id}
						className={`dsa-sidebar__item ${Number(c.id) === Number(activeId) ? 'dsa-sidebar__item--active' : ''}`}
						onClick={() => onSelect(c.id)}
						role="button"
						tabIndex={0}
						onKeyDown={(e) => e.key === 'Enter' && onSelect(c.id)}
					>
						<span className="dsa-sidebar__item-title">
							{c.title || __('New Chat', 'directorist-smart-assistant')}
						</span>
						<button
							className="dsa-sidebar__item-delete"
							onClick={(e) => {
								e.stopPropagation();
								onDelete(c.id);
							}}
							aria-label={__('Delete', 'directorist-smart-assistant')}
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
		<aside className={`dsa-sidebar ${collapsed ? 'dsa-sidebar--collapsed' : ''}`}>
			<div className="dsa-sidebar__header">
				<button className="dsa-sidebar__new-btn" onClick={onNew}>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
						<path d="M12 5v14M5 12h14" />
					</svg>
					{!collapsed && __('New Chat', 'directorist-smart-assistant')}
				</button>
				<button className="dsa-sidebar__toggle" onClick={onToggle} aria-label="Toggle sidebar">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
						<path d="M3 12h18M3 6h18M3 18h18" />
					</svg>
				</button>
			</div>

			{!collapsed && (
				<div className="dsa-sidebar__list">
					{conversations.length === 0 && (
						<div className="dsa-sidebar__empty">
							{__('No conversations yet', 'directorist-smart-assistant')}
						</div>
					)}
					{renderGroup(__('Today', 'directorist-smart-assistant'), groups.today)}
					{renderGroup(__('Yesterday', 'directorist-smart-assistant'), groups.yesterday)}
					{renderGroup(__('Previous 7 Days', 'directorist-smart-assistant'), groups.week)}
					{renderGroup(__('Older', 'directorist-smart-assistant'), groups.older)}
				</div>
			)}
		</aside>
	);
}
