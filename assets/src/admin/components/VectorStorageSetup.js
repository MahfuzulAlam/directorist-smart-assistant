/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { TextControl, ToggleControl, Button, CheckboxControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import {
	Card,
	CardBody,
	SectionHeader,
	Field,
	FieldGrid,
	SecretField,
	OptionList,
	SaveBar,
	StatusPill,
} from './Ui';
import { LinkIcon, SyncIcon, SlidersIcon } from './Icons';

export const SECTIONS = [
	{
		key: 'connection',
		title: __( 'API Connection', 'directorist-smart-assistant' ),
		icon: <LinkIcon size={ 16 } />,
		intro: __(
			'Point the plugin at your vector storage service and authenticate it.',
			'directorist-smart-assistant'
		),
	},
	{
		key: 'sync',
		title: __( 'Sync Options', 'directorist-smart-assistant' ),
		icon: <SyncIcon size={ 16 } />,
		intro: __( 'Choose which listings reach the vector store, and when.', 'directorist-smart-assistant' ),
	},
	{
		key: 'advanced',
		title: __( 'Advanced', 'directorist-smart-assistant' ),
		icon: <SlidersIcon size={ 16 } />,
		intro: __(
			'Chunking and index settings. The defaults suit most directories.',
			'directorist-smart-assistant'
		),
	},
];

/**
 * Vector Storage Setup Component
 */
export default function VectorStorageSetup( { settings, onSave, onNotice, section } ) {
	const [ localSettings, setLocalSettings ] = useState( settings );
	const [ saving, setSaving ] = useState( false );
	const [ bulkSyncing, setBulkSyncing ] = useState( false );
	const [ directoryTypes, setDirectoryTypes ] = useState( [] );
	const [ listingStatuses, setListingStatuses ] = useState( [] );
	const [ loadingOptions, setLoadingOptions ] = useState( true );

	useEffect( () => {
		loadOptions();
	}, [] );

	useEffect( () => {
		setLocalSettings( settings );
	}, [ settings ] );

	const dirty = JSON.stringify( localSettings ) !== JSON.stringify( settings );

	const loadOptions = async () => {
		setLoadingOptions( true );
		try {
			const [ typesResponse, statusesResponse ] = await Promise.all( [
				apiFetch( { path: 'directorist-smart-assistant/v1/directory-types' } ),
				apiFetch( { path: 'directorist-smart-assistant/v1/listing-statuses' } ),
			] );
			setDirectoryTypes( typesResponse || [] );
			setListingStatuses( statusesResponse || [] );
		} catch ( error ) {
			console.error( 'Error loading options:', error );
		} finally {
			setLoadingOptions( false );
		}
	};

	const notify = ( type, message ) => {
		if ( typeof onNotice === 'function' ) {
			onNotice( type, message );
		} else {
			alert( message );
		}
	};

	const handleChange = ( key, value ) => {
		setLocalSettings( ( prev ) => ( {
			...prev,
			[ key ]: value,
		} ) );
	};

	const handleCheckboxChange = ( key, value, checked ) => {
		setLocalSettings( ( prev ) => {
			const currentArray = prev[ key ] || [];
			const newArray = checked
				? [ ...currentArray, value ]
				: currentArray.filter( ( item ) => item !== value );
			return {
				...prev,
				[ key ]: newArray,
			};
		} );
	};

	const handleSave = async () => {
		setSaving( true );
		try {
			await onSave( localSettings );
		} finally {
			setSaving( false );
		}
	};

	const handleBulkSync = async () => {
		if (
			! confirm(
				__( 'Are you sure you want to sync all listings? This may take a while.', 'directorist-smart-assistant' )
			)
		) {
			return;
		}

		setBulkSyncing( true );
		try {
			const response = await apiFetch( {
				path: 'directorist-smart-assistant/v1/bulk-sync',
				method: 'POST',
				data: {
					post_ids: [], // Empty array means sync all listings based on settings
				},
			} );

			if ( response.success ) {
				const message =
					response.message || __( 'Bulk sync completed successfully!', 'directorist-smart-assistant' );
				if ( response.results && response.results.errors && response.results.errors.length > 0 ) {
					console.warn( 'Bulk sync errors:', response.results.errors );
				}
				notify( 'success', message );
			} else {
				notify(
					'error',
					response.message || __( 'Bulk sync failed. Please try again.', 'directorist-smart-assistant' )
				);
			}
		} catch ( error ) {
			console.error( 'Bulk sync error:', error );
			notify(
				'error',
				error.message ||
					__(
						'An error occurred during bulk sync. Please check the console for details.',
						'directorist-smart-assistant'
					)
			);
		} finally {
			setBulkSyncing( false );
		}
	};

	const selectedTypes = localSettings.vector_sync_directory_types || [];
	const selectedStatuses = localSettings.vector_sync_listing_statuses || [];
	const connected = !! (
		( localSettings.vector_api_base_url || '' ).trim() && ( localSettings.vector_api_secret_key || '' ).trim()
	);
	const current = SECTIONS.find( ( item ) => item.key === section ) || SECTIONS[ 0 ];

	const selectionLabel = ( count ) =>
		count > 0
			? sprintf(
					/* translators: %d: number of selected options. */
					__( '%d selected', 'directorist-smart-assistant' ),
					count
			  )
			: __( 'All', 'directorist-smart-assistant' );

	return (
		<div className="dsa-panel">
			<SectionHeader
				title={ current.title }
				description={ current.intro }
				badge={
					'connection' === section ? (
						connected ? (
							<StatusPill status="success">
								{ __( 'Configured', 'directorist-smart-assistant' ) }
							</StatusPill>
						) : (
							<StatusPill status="warning">
								{ __( 'Incomplete', 'directorist-smart-assistant' ) }
							</StatusPill>
						)
					) : null
				}
			/>

			<Card>
				<CardBody>

					{ /* API Configuration Section */ }
					{ 'connection' === section && (
						<>
							<Field className="dsa-field--control">
								<TextControl
									label={ __( 'API Base URL', 'directorist-smart-assistant' ) }
									value={ localSettings.vector_api_base_url || '' }
									onChange={ ( value ) => handleChange( 'vector_api_base_url', value ) }
									placeholder="https://api.example.com"
									help={ __(
										'Base URL of your vector storage API, without a trailing slash.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</Field>

							<SecretField
								id="dsa-vector-secret"
								label={ __( 'API Secret Key', 'directorist-smart-assistant' ) }
								value={ localSettings.vector_api_secret_key }
								onChange={ ( value ) => handleChange( 'vector_api_secret_key', value ) }
								placeholder={ __( 'Enter your API secret key', 'directorist-smart-assistant' ) }
								help={ __(
									'Used to authenticate every request to the vector storage API.',
									'directorist-smart-assistant'
								) }
							/>

							<Field className="dsa-field--control">
								<TextControl
									label={ __( 'Website ID', 'directorist-smart-assistant' ) }
									value={ localSettings.vector_website_id || '' }
									onChange={ ( value ) => handleChange( 'vector_website_id', value ) }
									placeholder={ __( 'Enter your website ID', 'directorist-smart-assistant' ) }
									help={ __(
										'Sent as the X-Website-ID header on every API call.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</Field>
						</>
					) }

					{ /* Sync Options Section */ }
					{ 'sync' === section && (
						<>
							<div className="dsa-toggle-row">
								<ToggleControl
									label={ __( 'Auto-sync on post save', 'directorist-smart-assistant' ) }
									checked={ localSettings.vector_auto_sync || false }
									onChange={ ( value ) => handleChange( 'vector_auto_sync', value ) }
									help={ __(
										'Push listings to vector storage automatically whenever they are saved or updated.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</div>

							<div className="dsa-callout">
								<div className="dsa-callout__text">
									<strong>{ __( 'Bulk sync', 'directorist-smart-assistant' ) }</strong>
									<span>
										{ __(
											'Send every existing listing that matches the filters below.',
											'directorist-smart-assistant'
										) }
									</span>
								</div>
								<Button
									className="dsa-button dsa-button--ghost"
									variant="secondary"
									onClick={ handleBulkSync }
									isBusy={ bulkSyncing }
									disabled={ bulkSyncing }
								>
									<SyncIcon size={ 16 } />
									<span>
										{ bulkSyncing
											? __( 'Syncing…', 'directorist-smart-assistant' )
											: __( 'Sync all listings', 'directorist-smart-assistant' ) }
									</span>
								</Button>
							</div>

							<Field className="dsa-field--control">
								<TextControl
									label={ __( 'Listing Chunk Size', 'directorist-smart-assistant' ) }
									type="number"
									value={ localSettings.vector_listing_chunk_size || 20 }
									onChange={ ( value ) =>
										handleChange( 'vector_listing_chunk_size', parseInt( value, 10 ) )
									}
									min={ 1 }
									max={ 100 }
									step={ 1 }
									help={ __(
										'Number of listings sent per batch during a bulk sync.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</Field>

							<FieldGrid>
								<Field
									label={
										<>
											{ __( 'Directory Types', 'directorist-smart-assistant' ) }
											<span className="dsa-field__count">
												{ selectionLabel( selectedTypes.length ) }
											</span>
										</>
									}
									help={ __(
										'Leave every box unchecked to sync all directory types.',
										'directorist-smart-assistant'
									) }
								>
									<OptionList
										loading={ loadingOptions }
										empty={ directoryTypes.length === 0 }
										loadingLabel={ __(
											'Loading directory types…',
											'directorist-smart-assistant'
										) }
										emptyLabel={ __(
											'No directory types found.',
											'directorist-smart-assistant'
										) }
									>
										{ directoryTypes.map( ( type ) => (
											<CheckboxControl
												key={ type.id }
												label={ type.name }
												checked={ selectedTypes.includes( type.id ) }
												onChange={ ( checked ) =>
													handleCheckboxChange(
														'vector_sync_directory_types',
														type.id,
														checked
													)
												}
												__nextHasNoMarginBottom
											/>
										) ) }
									</OptionList>
								</Field>

								<Field
									label={
										<>
											{ __( 'Listing Status', 'directorist-smart-assistant' ) }
											<span className="dsa-field__count">
												{ selectionLabel( selectedStatuses.length ) }
											</span>
										</>
									}
									help={ __(
										'Leave every box unchecked to sync all statuses.',
										'directorist-smart-assistant'
									) }
								>
									<OptionList
										loading={ loadingOptions }
										empty={ listingStatuses.length === 0 }
										loadingLabel={ __(
											'Loading listing statuses…',
											'directorist-smart-assistant'
										) }
										emptyLabel={ __(
											'No listing statuses found.',
											'directorist-smart-assistant'
										) }
									>
										{ listingStatuses.map( ( status ) => (
											<CheckboxControl
												key={ status.value }
												label={ status.label }
												checked={ selectedStatuses.includes( status.value ) }
												onChange={ ( checked ) =>
													handleCheckboxChange(
														'vector_sync_listing_statuses',
														status.value,
														checked
													)
												}
												__nextHasNoMarginBottom
											/>
										) ) }
									</OptionList>
								</Field>
							</FieldGrid>
						</>
					) }

					{ /* Advanced Section */ }
					{ 'advanced' === section && (
						<>
							<FieldGrid>
								<Field className="dsa-field--control">
									<TextControl
										label={ __( 'Chunk Size', 'directorist-smart-assistant' ) }
										type="number"
										value={ localSettings.vector_chunk_size || 500 }
										onChange={ ( value ) =>
											handleChange( 'vector_chunk_size', parseInt( value, 10 ) )
										}
										min={ 100 }
										max={ 2000 }
										step={ 100 }
										help={ __(
											'Characters per chunk when splitting listing content.',
											'directorist-smart-assistant'
										) }
										__nextHasNoMarginBottom
									/>
								</Field>

								<Field className="dsa-field--control">
									<TextControl
										label={ __( 'Chunk Overlap', 'directorist-smart-assistant' ) }
										type="number"
										value={ localSettings.vector_chunk_overlap || 50 }
										onChange={ ( value ) =>
											handleChange( 'vector_chunk_overlap', parseInt( value, 10 ) )
										}
										min={ 0 }
										max={ 200 }
										step={ 10 }
										help={ __(
											'Characters repeated between neighbouring chunks.',
											'directorist-smart-assistant'
										) }
										__nextHasNoMarginBottom
									/>
								</Field>

								<Field className="dsa-field--control">
									<TextControl
										label={ __( 'Embedding Model', 'directorist-smart-assistant' ) }
										value={ localSettings.vector_embedding_model || 'text-embedding-ada-002' }
										onChange={ ( value ) => handleChange( 'vector_embedding_model', value ) }
										help={ __(
											'Model used to generate vectors.',
											'directorist-smart-assistant'
										) }
										__nextHasNoMarginBottom
									/>
								</Field>

								<Field className="dsa-field--control">
									<TextControl
										label={ __( 'Index Name', 'directorist-smart-assistant' ) }
										value={ localSettings.vector_index_name || 'directorist-listings' }
										onChange={ ( value ) => handleChange( 'vector_index_name', value ) }
										help={ __(
											'Name of the vector index or collection.',
											'directorist-smart-assistant'
										) }
										__nextHasNoMarginBottom
									/>
								</Field>
							</FieldGrid>

							<Field className="dsa-field--control">
								<TextControl
									label={ __( 'Namespace', 'directorist-smart-assistant' ) }
									value={ localSettings.vector_namespace || '' }
									onChange={ ( value ) => handleChange( 'vector_namespace', value ) }
									help={ __(
										'Optional namespace for organising vectors.',
										'directorist-smart-assistant'
									) }
									__nextHasNoMarginBottom
								/>
							</Field>
						</>
					) }
				</CardBody>
			</Card>

			<SaveBar
				onSave={ handleSave }
				onDiscard={ () => setLocalSettings( settings ) }
				saving={ saving }
				dirty={ dirty }
			/>
		</div>
	);
}
