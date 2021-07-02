/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, ExternalLink } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import AMPValidationErrorsKeptIcon from '../../../../images/amp-validation-errors-kept.svg';
import { BLOCK_VALIDATION_STORE_KEY } from '../../store';
import { StatusIcon } from '../icon';
import { SidebarNotification } from '../sidebar-notification';
import { useErrorsFetchingStateChanges } from '../../hooks/use-errors-fetching-state-changes';

/**
 * AMP validation status notification component.
 */
export default function AMPValidationStatusNotification() {
	const { autosave, savePost } = useDispatch( 'core/editor' );
	const { isFetchingErrors } = useErrorsFetchingStateChanges();

	const {
		fetchingErrorsRequestErrorMessage,
		isDraft,
		isEditedPostNew,
		keptMarkupValidationErrorCount,
		reviewLink,
		supportLink,
		unreviewedValidationErrorCount,
		validationErrorCount,
	} = useSelect( ( select ) => ( {
		fetchingErrorsRequestErrorMessage: select( BLOCK_VALIDATION_STORE_KEY ).getFetchingErrorsRequestErrorMessage(),
		isDraft: [ 'draft', 'auto-draft' ].indexOf( select( 'core/editor' ).getEditedPostAttribute( 'status' ) ) !== -1,
		isEditedPostNew: select( 'core/editor' ).isEditedPostNew(),
		keptMarkupValidationErrorCount: select( BLOCK_VALIDATION_STORE_KEY ).getKeptMarkupValidationErrors().length,
		reviewLink: select( BLOCK_VALIDATION_STORE_KEY ).getReviewLink(),
		supportLink: select( BLOCK_VALIDATION_STORE_KEY ).getSupportLink(),
		unreviewedValidationErrorCount: select( BLOCK_VALIDATION_STORE_KEY ).getUnreviewedValidationErrors().length,
		validationErrorCount: select( BLOCK_VALIDATION_STORE_KEY ).getValidationErrors().length,
	} ), [] );

	if ( isFetchingErrors ) {
		return null;
	}

	if ( isEditedPostNew ) {
		return (
			<SidebarNotification
				icon={ <StatusIcon /> }
				message={ __( 'Validation will be checked upon saving.', 'amp' ) }
			/>
		);
	}

	if ( fetchingErrorsRequestErrorMessage ) {
		return (
			<SidebarNotification
				icon={ <AMPValidationErrorsKeptIcon /> }
				message={ fetchingErrorsRequestErrorMessage }
				action={
					<Button
						isLink
						onClick={ isDraft
							? () => savePost( { isPreview: true } )
							: () => autosave( { isPreview: true } )
						}
					>
						{ __( 'Try again', 'amp' ) }
					</Button>
				}
			/>
		);
	}

	if ( keptMarkupValidationErrorCount > 0 ) {
		return (
			<SidebarNotification
				icon={ <AMPValidationErrorsKeptIcon /> }
				message={
					sprintf(
						/* translators: %d is count of validation errors whose invalid markup is kept */
						_n(
							'AMP is disabled due to invalid markup being kept for %d issue.',
							'AMP is disabled due to invalid markup being kept for %d issues.',
							keptMarkupValidationErrorCount,
							'amp',
						),
						keptMarkupValidationErrorCount,
					)
				}
				action={ reviewLink && supportLink && (
					<>
						<ExternalLink href={ reviewLink }>
							{ __( 'View technical details', 'amp' ) }
						</ExternalLink>
						<br />
						<br />
						<ExternalLink href={ supportLink }>
							{ __( 'Get Support', 'amp' ) }
						</ExternalLink>
					</>
				) }
			/>
		);
	}

	if ( unreviewedValidationErrorCount > 0 ) {
		return (
			<SidebarNotification
				icon={ <StatusIcon broken={ true } /> }
				message={
					// @todo De-duplicate with what is in AMPDocumentStatusNotification.
					sprintf(
						/* translators: %d is count of unreviewed validation error */
						_n(
							'AMP is valid, but %d issue needs review.',
							'AMP is valid, but %d issues need review.',
							unreviewedValidationErrorCount,
							'amp',
						),
						unreviewedValidationErrorCount,
					)
				}
				action={ reviewLink && supportLink && (
					<>
						<ExternalLink href={ reviewLink }>
							{ __( 'View technical details', 'amp' ) }
						</ExternalLink>
						<br />
						<br />
						<ExternalLink href={ supportLink }>
							{ __( 'Get Support', 'amp' ) }
						</ExternalLink>
					</>
				) }
			/>
		);
	}

	if ( validationErrorCount > 0 ) {
		return <SidebarNotification
			icon={ <StatusIcon /> }
			message={
				// @todo De-duplicate with what is in AMPDocumentStatusNotification.
				sprintf(
					/* translators: %d is count of unreviewed validation error */
					_n(
						'AMP is valid. %d issue was reviewed.',
						'AMP is valid. %d issues were reviewed.',
						validationErrorCount,
						'amp',
					),
					validationErrorCount,
				)
			}
			action={ reviewLink && supportLink && (
				<>
					<ExternalLink href={ reviewLink }>
						{ __( 'View technical details', 'amp' ) }
					</ExternalLink>
					<br />
					<br />
					<ExternalLink href={ supportLink }>
						{ __( 'Get Support', 'amp' ) }
					</ExternalLink>
				</>
			) }
		/>;
	}

	// @todo De-duplicate with what is in AMPDocumentStatusNotification.
	return (
		<SidebarNotification
			icon={ <StatusIcon /> }
			message={ __( 'No AMP validation issues detected.', 'amp' ) }
		/>
	);
}
