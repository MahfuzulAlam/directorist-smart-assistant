/**
 * Helpers shared by the symptom search widget.
 */

const SESSION_KEY = 'dsa_symptom_session';

/**
 * A stable session id for this browser tab, so follow-up refinements land in
 * the same conversation on the triage service.
 *
 * @return {string} UUID-ish identifier.
 */
export function getSessionId() {
	try {
		const stored = window.sessionStorage.getItem( SESSION_KEY );

		if ( stored ) {
			return stored;
		}
	} catch ( e ) {
		// Private browsing — fall through and use a throwaway id.
	}

	const id =
		window.crypto && window.crypto.randomUUID
			? window.crypto.randomUUID()
			: 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, ( c ) => {
					const r = ( Math.random() * 16 ) | 0;
					const v = c === 'x' ? r : ( r & 0x3 ) | 0x8;
					return v.toString( 16 );
			  } );

	try {
		window.sessionStorage.setItem( SESSION_KEY, id );
	} catch ( e ) {
		// Nothing to do; the id still works for this page view.
	}

	return id;
}

/**
 * Build the directory search URL for a triage verdict.
 *
 * @param {string} base    Search page URL.
 * @param {Object} result  Triage payload.
 * @param {Object} profile Collected answers, for the location.
 * @return {string} URL.
 */
export function searchUrlFor( base, result, profile ) {
	const query = [
		result.resolved_label ||
			( result.specialist_required && result.specialist_type ? result.specialist_type : result.doctor_category ),
		profile.location || '',
	]
		.filter( Boolean )
		.join( ' ' )
		.trim();

	if ( ! query ) {
		return base;
	}

	return base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + 'q=' + encodeURIComponent( query );
}
