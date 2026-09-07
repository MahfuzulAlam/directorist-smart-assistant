/**
 * Launcher icons offered to the site owner.
 *
 * Shared by the admin picker and the frontend widget so the preview in
 * wp-admin is the same artwork visitors see. Every icon is a stroked glyph
 * that inherits `currentColor`.
 */

const base = ( size ) => ( {
	width: size,
	height: size,
	viewBox: '0 0 24 24',
	fill: 'none',
	stroke: 'currentColor',
	strokeWidth: 1.9,
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
	'aria-hidden': true,
	focusable: false,
} );

export const WIDGET_ICONS = {
	chat: {
		label: 'Chat bubble',
		render: ( size ) => (
			<svg { ...base( size ) }>
				<path d="M20.5 12a7.5 7.5 0 01-10.9 6.7L4 20.5l1.4-4.6A7.5 7.5 0 1120.5 12z" />
			</svg>
		),
	},
	messages: {
		label: 'Messages',
		render: ( size ) => (
			<svg { ...base( size ) }>
				<path d="M17.5 11.5a6 6 0 01-8.7 5.4L4.5 18l1.1-3.7a6 6 0 1111.9-2.8z" />
				<path d="M13.8 18.6a6 6 0 007.7 1.4l-1-3.3a6 6 0 00-.7-5.7" />
			</svg>
		),
	},
	support: {
		label: 'Support headset',
		render: ( size ) => (
			<svg { ...base( size ) }>
				<path d="M4.5 15v-2.5a7.5 7.5 0 0115 0V15" />
				<path d="M4.5 13.5h1.6c.8 0 1.4.6 1.4 1.4v2.7c0 .8-.6 1.4-1.4 1.4H5.9A1.4 1.4 0 014.5 17.6v-4.1zM19.5 13.5h-1.6c-.8 0-1.4.6-1.4 1.4v2.7c0 .8.6 1.4 1.4 1.4h.2c.8 0 1.4-.6 1.4-1.4v-4.1z" />
				<path d="M19 19v.4a2.6 2.6 0 01-2.6 2.6H13" />
			</svg>
		),
	},
	sparkle: {
		label: 'AI sparkle',
		render: ( size ) => (
			<svg { ...base( size ) }>
				<path d="M12 3.5l2 5.5 5.5 2-5.5 2-2 5.5-2-5.5-5.5-2 5.5-2 2-5.5z" />
				<path d="M18.8 16.6l.7 1.9 1.9.7-1.9.7-.7 1.9-.7-1.9-1.9-.7 1.9-.7.7-1.9z" />
			</svg>
		),
	},
	tooth: {
		label: 'Tooth',
		render: ( size ) => (
			<svg { ...base( size ) }>
				<path d="M7.8 3.5c-2 0-3.3 1.6-3.3 3.8 0 1.6.5 2.7.9 4 .4 1.4.5 2.6.7 4.3.2 1.6.5 4.4 1.9 4.4 1.2 0 1.4-1.7 1.7-3.6.2-1.6.5-2.9 1.3-2.9s1.1 1.3 1.3 2.9c.3 1.9.5 3.6 1.7 3.6 1.4 0 1.7-2.8 1.9-4.4.2-1.7.3-2.9.7-4.3.4-1.3.9-2.4.9-4 0-2.2-1.3-3.8-3.3-3.8-1.4 0-2.1.7-3.2.7s-1.8-.7-3.2-.7z" />
			</svg>
		),
	},
	stethoscope: {
		label: 'Stethoscope',
		render: ( size ) => (
			<svg { ...base( size ) }>
				<path d="M5.5 3.5v5a4 4 0 008 0v-5" />
				<path d="M4 3.5h3M11 3.5h3" />
				<path d="M9.5 12.5v2a5 5 0 0010 0v-1" />
				<circle cx="19.5" cy="11" r="2" />
			</svg>
		),
	},
	help: {
		label: 'Question mark',
		render: ( size ) => (
			<svg { ...base( size ) }>
				<circle cx="12" cy="12" r="8.6" />
				<path d="M9.6 9.6a2.5 2.5 0 114.3 1.9c-.9.8-1.9 1.2-1.9 2.6" />
				<path d="M12 17.2h.01" />
			</svg>
		),
	},
	bot: {
		label: 'Robot',
		render: ( size ) => (
			<svg { ...base( size ) }>
				<rect x="4" y="8" width="16" height="11" rx="3.5" />
				<path d="M12 4.5V8M8.5 13h.01M15.5 13h.01M9.5 16h5" />
				<circle cx="12" cy="3.6" r="1.2" />
			</svg>
		),
	},
};

export const DEFAULT_WIDGET_ICON = 'chat';

/**
 * Render one launcher icon, falling back to the default when a stored key no
 * longer exists.
 *
 * @param {string} key  Icon key.
 * @param {number} size Pixel size.
 * @return {Object} Element.
 */
export function renderWidgetIcon( key, size = 24 ) {
	const icon = WIDGET_ICONS[ key ] || WIDGET_ICONS[ DEFAULT_WIDGET_ICON ];

	return icon.render( size );
}
