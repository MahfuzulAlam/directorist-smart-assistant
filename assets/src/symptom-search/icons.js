/**
 * Inline icons for the symptom search widget.
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

export const ChatIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<path d="M20 12.5a7.5 7.5 0 01-10.9 6.7L4 20.5l1.4-4.6A7.5 7.5 0 1120 12.5z" />
	</svg>
);

export const PenIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<path d="M16.5 3.5l4 4L8 20H4v-4L16.5 3.5z" />
		<path d="M14.5 5.5l4 4" />
	</svg>
);

export const SendIcon = ( { size = 18 } ) => (
	<svg { ...base( size ) }>
		<path d="M21 3L10.5 13.5M21 3l-6.8 18-3.7-7.5L3 9.8 21 3z" />
	</svg>
);

export const SearchIcon = ( { size = 18 } ) => (
	<svg { ...base( size ) }>
		<circle cx="11" cy="11" r="6.5" />
		<path d="M20.5 20.5l-4.7-4.7" />
	</svg>
);

export const StethoscopeIcon = ( { size = 20 } ) => (
	<svg { ...base( size ) }>
		<path d="M5.5 3.5v5a4 4 0 008 0v-5" />
		<path d="M4 3.5h3M11 3.5h3" />
		<path d="M9.5 12.5v2a5 5 0 0010 0v-1" />
		<circle cx="19.5" cy="11" r="2" />
	</svg>
);

export const ClockIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<circle cx="12" cy="12" r="8.5" />
		<path d="M12 7.5V12l3 1.8" />
	</svg>
);

export const AlertIcon = ( { size = 18 } ) => (
	<svg { ...base( size ) }>
		<path d="M12 4l8.5 15h-17L12 4z" />
		<path d="M12 10v3.5M12 16.5h.01" />
	</svg>
);

export const ShieldIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<path d="M12 3l7 2.5v5c0 4.3-2.9 8-7 9.5-4.1-1.5-7-5.2-7-9.5v-5L12 3z" />
		<path d="M9 12l2 2 4-4" />
	</svg>
);

export const AwardIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<circle cx="12" cy="9" r="5.5" />
		<path d="M8.5 13.5L7 21l5-2.5L17 21l-1.5-7.5" />
	</svg>
);

export const RefreshIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<path d="M20 11.5a8 8 0 00-13.7-5.2L3.5 9M4 12.5a8 8 0 0013.7 5.2l2.8-2.7" />
		<path d="M3.5 4.5V9H8M20.5 19.5V15H16" />
	</svg>
);

export const ChevronIcon = ( { size = 16 } ) => (
	<svg { ...base( size ) }>
		<path d="M6 9.5l6 6 6-6" />
	</svg>
);

export const EditIcon = ( { size = 14 } ) => (
	<svg { ...base( size ) }>
		<path d="M15.5 4.5l4 4L9 19H5v-4L15.5 4.5z" />
		<path d="M13.5 6.5l4 4" />
	</svg>
);
