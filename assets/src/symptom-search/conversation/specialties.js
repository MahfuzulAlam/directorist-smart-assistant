/**
 * Specialty taxonomy helpers.
 *
 * The list in `speciaties.json` is the vocabulary the model must choose from,
 * and the same list turns whatever it returns back into a human label. Both
 * directions live here so they cannot drift apart.
 */

/**
 * Older ids that predate the taxonomy — the flow document still emits these,
 * and the service has been trained on similar wording.
 *
 * @type {Object}
 */
const ALIASES = {
	general: 'general-dentistry',
	dentist: 'general-dentistry',
	'general-dentist': 'general-dentistry',
	endodontist: 'endodontics',
	periodontist: 'periodontics',
	prosthodontist: 'prosthodontics',
	orthodontist: 'orthodontics',
	pedodontist: 'pediatric-dentistry',
	'pediatric-dentist': 'pediatric-dentistry',
	'paediatric-dentistry': 'pediatric-dentistry',
	'oral-surgeon': 'oral-maxillofacial-surgery',
	'oral-and-maxillofacial-surgeon': 'oral-maxillofacial-surgery',
	'maxillofacial-surgeon': 'oral-maxillofacial-surgery',
	'oral-pathologist': 'oral-maxillofacial-pathology',
	'oral-radiologist': 'oral-maxillofacial-radiology',
	'oral-physician': 'oral-medicine',
	anesthesiologist: 'dental-anesthesiology',
	'public-health-dentist': 'dental-public-health',
};

/**
 * Reduce free-form text to a comparable slug.
 *
 * @param {string} value Raw value.
 * @return {string}
 */
function slug( value ) {
	return String( value || '' )
		.toLowerCase()
		.trim()
		.replace( /[_\s]+/g, '-' )
		.replace( /[^a-z0-9-]/g, '' )
		.replace( /-+/g, '-' )
		.replace( /^-|-$/g, '' );
}

/**
 * Every entry as a flat list, sub-specialties carrying their parent.
 *
 * @param {Array} taxonomy Specialty groups.
 * @return {Object[]} { id, label, group }
 */
export function flattenSpecialties( taxonomy ) {
	const rows = [];

	( taxonomy || [] ).forEach( ( group ) => {
		rows.push( { id: group.id, label: group.label, group: null } );

		( group.sub || [] ).forEach( ( sub ) => {
			rows.push( {
				id: sub.id,
				label: sub.label,
				group: { id: group.id, label: group.label },
			} );
		} );
	} );

	return rows;
}

/**
 * The line handed to the model: every id it may choose from, grouped so the
 * hierarchy is visible.
 *
 * @param {Array} taxonomy Specialty groups.
 * @return {string} Empty when there is no taxonomy to offer.
 */
export function specialtyPrompt( taxonomy ) {
	if ( ! taxonomy || ! taxonomy.length ) {
		return '';
	}

	const grouped = taxonomy
		.map( ( group ) => {
			const subs = ( group.sub || [] ).map( ( sub ) => sub.id ).join( ', ' );

			return subs ? `${ group.id } > ${ subs }` : group.id;
		} )
		.join( '; ' );

	return (
		'Set recommended_specialty to exactly one id from this list, preferring the most ' +
		`specific sub-specialty that fits: ${ grouped }.`
	);
}

/**
 * Turn whatever the service returned into a taxonomy entry.
 *
 * Accepts an exact id, a legacy id, a label, or loose wording such as
 * "Endodontist (root canal)" — falling back to the raw text so the screen
 * never goes blank on an unknown value.
 *
 * @param {string} value    Value from the response.
 * @param {Array}  taxonomy Specialty groups.
 * @return {Object|null} { id, label, group, matched }
 */
export function resolveSpecialty( value, taxonomy ) {
	const raw = String( value || '' ).trim();

	if ( ! raw ) {
		return null;
	}

	const rows = flattenSpecialties( taxonomy );
	const key = slug( raw );

	if ( ! rows.length ) {
		return { id: key, label: raw, group: null, matched: false };
	}

	// Sub-specialties first: a specific answer should win over its parent.
	const bySpecificity = [ ...rows ].sort( ( a, b ) => ( a.group ? 0 : 1 ) - ( b.group ? 0 : 1 ) );

	const exact = bySpecificity.find( ( row ) => row.id === key );

	if ( exact ) {
		return { ...exact, matched: true };
	}

	const aliased = ALIASES[ key ];

	if ( aliased ) {
		const row = rows.find( ( item ) => item.id === aliased );

		if ( row ) {
			return { ...row, matched: true };
		}
	}

	const byLabel = bySpecificity.find( ( row ) => slug( row.label ) === key );

	if ( byLabel ) {
		return { ...byLabel, matched: true };
	}

	// "Endodontist (root canal)" or "endodontics specialist" — take the longest
	// id that appears in the value, so a sub-specialty beats its group.
	const contained = bySpecificity
		.filter( ( row ) => key.includes( row.id ) || row.id.includes( key ) )
		.sort( ( a, b ) => b.id.length - a.id.length )[ 0 ];

	if ( contained ) {
		return { ...contained, matched: true };
	}

	// Qualified wording — "Endodontist (root canal)" reduces to
	// "endodontist-root-canal", so try each word and adjacent pair on its own.
	const words = key.split( '-' ).filter( Boolean );

	for ( let size = 2; size >= 1; size-- ) {
		for ( let i = 0; i + size <= words.length; i++ ) {
			const part = words.slice( i, i + size ).join( '-' );
			const target = ALIASES[ part ] || part;
			const row = bySpecificity.find( ( item ) => item.id === target );

			if ( row ) {
				return { ...row, matched: true };
			}
		}
	}

	// Near-misses of form rather than meaning: "…-surgeon" against
	// "…-surgery". Longest shared prefix wins, and only when it is long
	// enough to be a real signal.
	const prefixOf = ( a, b ) => {
		let n = 0;

		while ( n < a.length && n < b.length && a[ n ] === b[ n ] ) {
			n++;
		}

		return n;
	};

	const near = bySpecificity
		.map( ( row ) => ( {
			row,
			score: Math.max( prefixOf( key, row.id ), prefixOf( key, slug( row.label ) ) ),
		} ) )
		.filter( ( entry ) => entry.score >= 8 )
		.sort( ( a, b ) => b.score - a.score )[ 0 ];

	if ( near ) {
		return { ...near.row, matched: true };
	}

	return { id: key, label: raw, group: null, matched: false };
}

/**
 * Choose the best specialty from the several fields a response may carry.
 *
 * The service has no `recommended_specialty` field, so the id it was asked for
 * arrives in `doctor_category` (usually the group) and `specialist_type`
 * (usually the sub-specialty). Rather than trust one field, resolve them all
 * and keep the most specific match — a sub-specialty beats its own group.
 *
 * @param {Array} candidates Raw values, most authoritative first.
 * @param {Array} taxonomy   Specialty groups.
 * @return {Object|null} Resolved entry.
 */
export function pickSpecialty( candidates, taxonomy ) {
	const resolved = ( candidates || [] )
		.filter( Boolean )
		.map( ( value ) => resolveSpecialty( value, taxonomy ) )
		.filter( Boolean );

	if ( ! resolved.length ) {
		return null;
	}

	return (
		resolved.find( ( entry ) => entry.matched && entry.group ) ||
		resolved.find( ( entry ) => entry.matched ) ||
		resolved[ 0 ]
	);
}
