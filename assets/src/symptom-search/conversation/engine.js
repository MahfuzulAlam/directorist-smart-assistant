/**
 * Conversation engine for the symptom checker.
 *
 * Walks the question graph in `conversation/dental.json`. Routing, scoring and
 * the urgency floor are computed here, in code — per the document's safety
 * rules, the model may raise urgency but must never lower it below what this
 * engine determined. Red flags never reach the model at all: they halt the
 * conversation immediately.
 */

const URGENCY_RANK = {
	routine: 0,
	soon_1week: 1,
	urgent_24h: 2,
	emergency: 3,
};

/**
 * Localised string from a { en, bn } map.
 *
 * @param {Object|string} value  Label map or plain string.
 * @param {string}        locale Preferred language code.
 * @return {string}
 */
export function localise( value, locale ) {
	if ( ! value ) {
		return '';
	}

	if ( typeof value === 'string' ) {
		return value;
	}

	return value[ locale ] || value.en || Object.values( value )[ 0 ] || '';
}

/**
 * The more severe of two urgency ids.
 *
 * @param {string} a First id.
 * @param {string} b Second id.
 * @return {string}
 */
function higherUrgency( a, b ) {
	if ( ! a ) {
		return b;
	}

	if ( ! b ) {
		return a;
	}

	return ( URGENCY_RANK[ a ] ?? 0 ) >= ( URGENCY_RANK[ b ] ?? 0 ) ? a : b;
}

/**
 * Options a node offers, honouring `fallback_type` for node types the widget
 * does not draw natively (the body map).
 *
 * @param {Object} node            Node definition.
 * @param {Array}  locationOptions Directory locations, for the booking node.
 * @return {Array} Option definitions.
 */
export function nodeOptions( node, locationOptions = [] ) {
	if ( ! node ) {
		return [];
	}

	if ( 'location' === node.type ) {
		return locationOptions.map( ( name ) => ( { value: name, label: name } ) );
	}

	return node.options || [];
}

/**
 * Create a walker over a flow document.
 *
 * @param {Object} flow     Decoded flow document.
 * @param {string} locale   Language code for labels.
 * @param {Array}  taxonomy Specialty groups the model may choose from.
 * @return {Object} Engine.
 */
export function createEngine( flow, locale = 'en', taxonomy = [] ) {
	const nodes = ( flow && flow.nodes ) || {};
	const config = ( flow && flow.config ) || {};
	const scoring = ( flow && flow.scoring ) || {};
	const maxQuestions = config.max_questions_per_session || 8;
	const maxAfterUrgency = config.max_questions_after_urgency_known || 2;

	/**
	 * Resolve the id of the node that follows an answer.
	 *
	 * @param {string} nodeId  Node just answered.
	 * @param {Array}  chosen  Option definitions the visitor picked.
	 * @param {Object} answers All answers so far.
	 * @return {string} Next node id.
	 */
	const resolveNext = ( nodeId, chosen, answers ) => {
		const node = nodes[ nodeId ];

		if ( ! node ) {
			return 'assessment';
		}

		// A picked option may redirect (the chief complaint does this).
		const redirect = chosen.find( ( option ) => option && option.next );

		if ( redirect ) {
			return redirect.next;
		}

		// An option can also open a conditional follow-up (allergy detail).
		const followUp = chosen.find( ( option ) => option && option.follow_up );

		if ( followUp ) {
			return followUp.follow_up;
		}

		return node.next || 'assessment';
	};

	/**
	 * Walk past invisible nodes (routers) to the next node a person sees.
	 *
	 * @param {string} nodeId  Candidate node id.
	 * @param {Object} answers All answers so far.
	 * @return {string} Node id to display.
	 */
	const skipInvisible = ( nodeId, answers ) => {
		let current = nodeId;
		let guard = 0;

		while ( current && guard < 20 ) {
			const node = nodes[ current ];

			if ( ! node ) {
				return 'assessment';
			}

			if ( 'router' === node.type ) {
				const on = answers.cc_chief_complaint;
				const key = Array.isArray( on ) ? on[ 0 ] : on;

				current = ( node.routes && node.routes[ key ] ) || node.default || 'assessment';
				guard += 1;
				continue;
			}

			// A conditional node is skipped when its condition is not met.
			if ( node.conditional_on ) {
				const source = answers[ node.conditional_on.node ];
				const values = Array.isArray( source ) ? source : [ source ];

				if ( ! values.includes( node.conditional_on.contains ) ) {
					current = node.next || 'assessment';
					guard += 1;
					continue;
				}
			}

			return current;
		}

		return 'assessment';
	};

	return {
		flow,
		locale,
		nodes,
		config,

		startNodeId() {
			const fromGate = flow?.red_flags?.none_option?.next;

			return skipInvisible( fromGate || 'cc_chief_complaint', {} );
		},

		node( nodeId ) {
			return nodes[ nodeId ] || null;
		},

		/**
		 * Apply an answer and report where the conversation goes next.
		 *
		 * @param {Object} state   Current state.
		 * @param {string} nodeId  Node being answered.
		 * @param {Array}  values  Chosen option values (or a single raw value).
		 * @return {Object} Next state.
		 */
		answer( state, nodeId, values ) {
			const node = nodes[ nodeId ];
			const list = Array.isArray( values ) ? values : [ values ];
			const options = nodeOptions( node, state.locationOptions || [] );
			const chosen = list
				.map( ( value ) => options.find( ( option ) => option.value === value ) )
				.filter( Boolean );

			let score = state.score;
			let floor = state.floor;
			const specialties = [ ...state.specialties ];
			const flags = [ ...state.flags ];

			chosen.forEach( ( option ) => {
				score += option.score || 0;
				floor = higherUrgency( floor, option.min_urgency || option.set_urgency || '' );

				if ( option.specialty && ! specialties.includes( option.specialty ) ) {
					specialties.push( option.specialty );
				}

				if ( option.flag && ! flags.includes( option.flag ) ) {
					flags.push( option.flag );
				}
			} );

			// A scale node scores by the band its value falls in.
			if ( 'scale' === node?.type ) {
				const value = parseInt( list[ 0 ], 10 );
				const band = ( node.scoring || [] ).find(
					( entry ) => value >= entry.range[ 0 ] && value <= entry.range[ 1 ]
				);

				if ( band ) {
					score += band.score || 0;
					floor = higherUrgency( floor, band.min_urgency || '' );
				}
			}

			// A number node applies its rules (age brackets).
			if ( 'number' === node?.type ) {
				const value = parseInt( list[ 0 ], 10 );

				( node.rules || [] ).forEach( ( rule ) => {
					const match = /value\s*([<>])\s*(\d+)/.exec( rule.if || '' );

					if ( ! match ) {
						return;
					}

					const passes = '<' === match[ 1 ] ? value < Number( match[ 2 ] ) : value > Number( match[ 2 ] );

					if ( ! passes ) {
						return;
					}

					score += rule.score || 0;

					if ( rule.specialty && ! specialties.includes( rule.specialty ) ) {
						specialties.push( rule.specialty );
					}

					if ( rule.flag && ! flags.includes( rule.flag ) ) {
						flags.push( rule.flag );
					}
				} );
			}

			const answers = { ...state.answers, [ nodeId ]: values };
			const asked = state.asked + 1;
			// Keep the state as it was before this answer, so "Edit" can return
			// here exactly rather than recomputing scores backwards.
			const snapshots = { ...state.snapshots, [ nodeId ]: { ...state, snapshots: undefined } };
			const nextId = skipInvisible( resolveNext( nodeId, chosen, answers ), answers );

			const next = {
				...state,
				snapshots,
				answers,
				score,
				floor,
				specialties,
				flags,
				asked,
				history: [ ...state.history, nodeId ],
				nodeId: nextId,
			};

			// Stop rule: urgency beats precision. Once the tier is known, only a
			// couple of follow-ups are worth the visitor's patience.
			next.askedAfterUrgency = this.urgencyKnown( state )
				? state.askedAfterUrgency + 1
				: state.askedAfterUrgency;

			if ( this.shouldStop( next ) ) {
				next.nodeId = 'assessment';
			}

			return next;
		},

		/**
		 * Urgency from the running score, floored by any min_urgency reached.
		 *
		 * @param {Object} state Current state.
		 * @return {string} Urgency id.
		 */
		urgency( state ) {
			const clamp = scoring.clamp || [ 0, 100 ];
			const total = Math.min( Math.max( state.score, clamp[ 0 ] ), clamp[ 1 ] );
			const thresholds = [ ...( scoring.thresholds || [] ) ].sort( ( a, b ) => b.min - a.min );
			const fromScore = ( thresholds.find( ( entry ) => total >= entry.min ) || {} ).urgency || 'routine';

			// "The floor always wins over the sum": a min_urgency reached by an
			// answer is the tier, not merely a minimum. Without this, points
			// accumulated across a long pain branch tip an urgent_24h case into
			// emergency — a tier the document reserves for the red flag gate.
			if ( scoring.urgency_floor_overrides_sum && state.floor ) {
				return state.floor;
			}

			return higherUrgency( fromScore, state.floor );
		},

		/**
		 * Priority score handed to the queue, clamped as the document specifies.
		 *
		 * @param {Object} state Current state.
		 * @return {number}
		 */
		queueScore( state ) {
			const clamp = scoring.clamp || [ 0, 100 ];

			return Math.min( Math.max( state.score, clamp[ 0 ] ), clamp[ 1 ] );
		},

		/**
		 * True once the tier is settled enough to wrap up.
		 *
		 * @param {Object} state Current state.
		 * @return {boolean}
		 */
		urgencyKnown( state ) {
			if ( state.floor ) {
				return true;
			}

			const urgentThreshold = ( scoring.thresholds || [] ).find(
				( entry ) => 'urgent_24h' === entry.urgency
			);

			return !! urgentThreshold && state.score >= urgentThreshold.min;
		},

		shouldStop( state ) {
			if ( state.asked >= maxQuestions ) {
				return true;
			}

			return this.urgencyKnown( state ) && state.askedAfterUrgency >= maxAfterUrgency;
		},

		/**
		 * Remaining question budget, for the progress hint.
		 *
		 * @param {Object} state Current state.
		 * @return {number}
		 */
		budget( state ) {
			const hard = maxQuestions - state.asked;

			if ( ! this.urgencyKnown( state ) ) {
				return hard;
			}

			return Math.max( 0, Math.min( hard, maxAfterUrgency - state.askedAfterUrgency ) );
		},

		/**
		 * Short, plain-language label for an answered node.
		 *
		 * Comes from the flow document's `summary_label`; falls back to the node
		 * id with its layer prefix stripped, so an unlabelled node still reads.
		 *
		 * @param {string} nodeId Node id.
		 * @return {string}
		 */
		summaryLabel( nodeId ) {
			const node = nodes[ nodeId ];

			if ( node && node.summary_label ) {
				return localise( node.summary_label, locale );
			}

			const words = String( nodeId )
				.replace( /^(cc|u|p|s|g|b|l|a|ctx|book|rf)_/, '' )
				.replace( /_/g, ' ' )
				.trim();

			return words.charAt( 0 ).toUpperCase() + words.slice( 1 );
		},

		/**
		 * An answer rendered for display, in the conversation's language.
		 *
		 * @param {Object} state  Current state.
		 * @param {string} nodeId Node id.
		 * @return {string}
		 */
		answerText( state, nodeId ) {
			const node = nodes[ nodeId ];
			const value = state.answers[ nodeId ];

			if ( ! node || value === undefined || value === '' ) {
				return '';
			}

			const options = nodeOptions( node, state.locationOptions || [] );
			const list = Array.isArray( value ) ? value : [ value ];

			if ( ! list.length ) {
				return '—';
			}

			return list
				.map( ( entry ) => {
					const option = options.find( ( item ) => item.value === entry );

					return option ? localise( option.label, locale ) : String( entry );
				} )
				.join( ', ' );
		},

		/**
		 * Answered nodes as display rows, in the order they were asked.
		 *
		 * @param {Object} state Current state.
		 * @return {Object[]} { nodeId, label, value } rows.
		 */
		rows( state ) {
			return state.history
				.map( ( nodeId ) => ( {
					nodeId,
					label: this.summaryLabel( nodeId ),
					value: this.answerText( state, nodeId ),
				} ) )
				.filter( ( row ) => '' !== row.value );
		},

		/**
		 * The prompt a node asks, in the conversation's language.
		 *
		 * @param {string} nodeId Node id.
		 * @return {string}
		 */
		promptText( nodeId ) {
			const node = nodes[ nodeId ];

			return node ? localise( node.prompt, locale ) : '';
		},

		/**
		 * The transcript as one English description for the triage service.
		 *
		 * Always English regardless of display language: the flow document's
		 * clinical hints are written in English and the model reasons better on it.
		 *
		 * @param {Object} state Current state.
		 * @return {string}
		 */
		describe( state ) {
			const lines = [];

			state.history.forEach( ( nodeId ) => {
				const node = nodes[ nodeId ];
				const value = state.answers[ nodeId ];

				if ( ! node || value === undefined || value === '' ) {
					return;
				}

				const question = localise( node.prompt, 'en' );
				const options = nodeOptions( node, state.locationOptions || [] );
				const list = Array.isArray( value ) ? value : [ value ];
				const answer = list
					.map( ( entry ) => {
						const option = options.find( ( item ) => item.value === entry );

						return option ? localise( option.label, 'en' ) : String( entry );
					} )
					.join( ', ' );

				if ( answer ) {
					lines.push( `${ question } ${ answer }` );
				}
			} );

			const urgency = this.urgency( state );

			// Hand the model what the code already decided, so it can explain the
			// tier rather than contradict it.
			lines.push(
				`Triage engine result: urgency tier ${ urgency }, queue priority score ${ this.queueScore(
					state
				) } of 100.`
			);

			if ( state.specialties.length ) {
				lines.push( `Suggested specialties from the question flow: ${ state.specialties.join( ', ' ) }.` );
			}

			if ( state.flags.length ) {
				lines.push( `Flags: ${ state.flags.join( ', ' ) }.` );
			}

			return lines.join( ' ' );
		},
	};
}

/**
 * A fresh conversation state.
 *
 * @param {Object} engine          Engine.
 * @param {Array}  locationOptions Directory locations for the booking node.
 * @return {Object} State.
 */
export function initialState( engine, locationOptions = [] ) {
	return {
		nodeId: engine.startNodeId(),
		answers: {},
		snapshots: {},
		history: [],
		score: 0,
		floor: '',
		specialties: [],
		flags: [],
		asked: 0,
		askedAfterUrgency: 0,
		locationOptions,
	};
}

/**
 * Return the conversation to a question that was already answered.
 *
 * Everything after it is discarded — later answers were given in the light of
 * this one, so keeping them would misreport what the visitor said.
 *
 * @param {Object} state  Current state.
 * @param {string} nodeId Node to re-open.
 * @return {Object} State positioned at that question.
 */
export function rewindTo( state, nodeId ) {
	const snapshot = state.snapshots && state.snapshots[ nodeId ];

	if ( ! snapshot ) {
		return state;
	}

	return {
		...snapshot,
		snapshots: state.snapshots,
		nodeId,
	};
}

export { URGENCY_RANK, higherUrgency };
