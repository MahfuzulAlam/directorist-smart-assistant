/**
 * Inline SVG icon set for the Smart Assistant admin panel.
 *
 * Icons are stroke based so they inherit `currentColor` and stay crisp at
 * any size. Pass a `size` to override the default 20px square.
 */

const base = ( size ) => ( {
	width: size,
	height: size,
	viewBox: '0 0 24 24',
	fill: 'none',
	stroke: 'currentColor',
	strokeWidth: 1.8,
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
	'aria-hidden': true,
	focusable: false,
} );

export const SparkleIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z" />
		<path d="M18.5 15.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2z" />
	</svg>
);

export const DatabaseIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<ellipse cx="12" cy="5.5" rx="7.5" ry="3" />
		<path d="M4.5 5.5v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3v-6" />
		<path d="M4.5 11.5v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3v-6" />
	</svg>
);

export const ChatIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M20 12.5a7.5 7.5 0 01-10.9 6.7L4 20.5l1.4-4.6A7.5 7.5 0 1120 12.5z" />
		<path d="M9 11.5h6M9 14.5h3.5" />
	</svg>
);

export const KeyIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<circle cx="8" cy="15" r="4" />
		<path d="M10.9 12.1L20 3m-3 3l2.2 2.2M14.4 8.6l2.2 2.2" />
	</svg>
);

export const LinkIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M10.5 13.5a4 4 0 006 .4l2.2-2.2a4 4 0 10-5.7-5.7l-1.3 1.3" />
		<path d="M13.5 10.5a4 4 0 00-6-.4l-2.2 2.2a4 4 0 105.7 5.7l1.3-1.3" />
	</svg>
);

export const SlidersIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M5 20v-7M5 9V4M12 20v-9M12 7V4M19 20v-4M19 12V4" />
		<path d="M2.5 13h5M9.5 11h5M16.5 16h5" />
	</svg>
);

export const SyncIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M20 11.5a8 8 0 00-13.7-5.2L3.5 9" />
		<path d="M4 12.5a8 8 0 0013.7 5.2l2.8-2.7" />
		<path d="M3.5 4.5V9H8M20.5 19.5V15H16" />
	</svg>
);

export const EyeIcon = ( { size = 18 } ) => (
	<svg { ...base( size ) }>
		<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" />
		<circle cx="12" cy="12" r="3" />
	</svg>
);

export const EyeOffIcon = ( { size = 18 } ) => (
	<svg { ...base( size ) }>
		<path d="M9.9 5.8A9.6 9.6 0 0112 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 01-3.3 4.1M6.4 7.6A16 16 0 002.5 12S6 18.5 12 18.5c1 0 1.9-.2 2.7-.5" />
		<path d="M10 10a3 3 0 004.2 4.2M3.5 3.5l17 17" />
	</svg>
);

export const CheckIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<path d="M4.5 12.5l4.5 4.5 10.5-10.5" />
	</svg>
);

export const AlertIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<circle cx="12" cy="12" r="8.5" />
		<path d="M12 8v5M12 16h.01" />
	</svg>
);

export const InfoIcon = ( { size = 15 } ) => (
	<svg { ...base( size ) }>
		<circle cx="12" cy="12" r="8.5" />
		<path d="M12 11.5V16M12 8h.01" />
	</svg>
);

export const BoltIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M13 2.5L4.5 13.5H11l-.5 8L19 10.5h-6.5l.5-8z" />
	</svg>
);

export const PaletteIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M12 3.5c-4.7 0-8.5 3.6-8.5 8s3.8 8 8.5 8c1.2 0 2-.8 2-1.8 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-1 .8-1.8 1.8-1.8h1.4c2.6 0 4.3-1.7 4.3-4.3 0-3.2-3.4-5.7-8.5-5.7z" />
		<circle cx="8" cy="10" r="1" />
		<circle cx="12" cy="7.5" r="1" />
		<circle cx="16" cy="10" r="1" />
	</svg>
);

export const CoinIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<circle cx="12" cy="12" r="8.5" />
		<path d="M12 6.8v10.4" />
		<path d="M14.6 9.4c0-1-1.2-1.7-2.6-1.7s-2.6.7-2.6 1.7 1 1.5 2.6 1.8 2.8.8 2.8 1.9-1.3 1.8-2.8 1.8-2.8-.8-2.8-1.8" />
	</svg>
);

export const ChartIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M4 20h16" />
		<path d="M6.5 20V12M11.5 20V6M16.5 20v-5" />
	</svg>
);
