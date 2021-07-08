/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import {
	createContext,
	useContext,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { ErrorContext } from '../error-context-provider';
import { useAsyncError } from '../../utils/use-async-error';
import { calculateStylesheetSizes } from '../../validated-url-page/helpers';

export const ValidatedUrl = createContext();

/**
 * Validated URL data context provider.
 *
 * @param {Object} props Component props.
 * @param {any} props.children Component children.
 * @param {number} props.cssBudgetBytes CSS budget value in bytes.
 * @param {boolean} props.hasErrorBoundary Whether the component is wrapped in an error boundary.
 * @param {number} props.postId Validated URL post ID.
 * @param {string} props.validatedUrlsRestPath REST endpoint to retrieve validated URL data.
 */
export function ValidatedUrlProvider( {
	children,
	cssBudgetBytes,
	hasErrorBoundary = false,
	postId,
	validatedUrlsRestPath,
} ) {
	const [ validatedUrl, setValidatedUrl ] = useState( {} );
	const [ stylesheetSizes, setStylesheetSizes ] = useState( {} );
	const [ fetchingValidatedUrl, setFetchingValidatedUrl ] = useState( null );

	const { error, setError } = useContext( ErrorContext );
	const { setAsyncError } = useAsyncError();

	/**
	 * This component sets state inside async functions.
	 * Use this ref to prevent state updates after unmount.
	 */
	const hasUnmounted = useRef( false );
	useEffect( () => () => {
		hasUnmounted.current = true;
	}, [] );

	/**
	 * Fetches validated URL data.
	 */
	useEffect( () => {
		if ( error || Object.keys( validatedUrl ).length || fetchingValidatedUrl ) {
			return;
		}

		( async () => {
			setFetchingValidatedUrl( true );

			try {
				const fetchedValidatedUrl = await apiFetch( {
					path: `${ validatedUrlsRestPath }/${ postId }`,
				} );

				if ( hasUnmounted.current === true ) {
					return;
				}

				setValidatedUrl( fetchedValidatedUrl );
				setStylesheetSizes( calculateStylesheetSizes( fetchedValidatedUrl?.stylesheets, cssBudgetBytes ) );
			} catch ( e ) {
				if ( hasUnmounted.current === true ) {
					return;
				}

				setError( e );

				if ( hasErrorBoundary ) {
					setAsyncError( e );
				}

				return;
			}

			setFetchingValidatedUrl( false );
		} )();
	}, [ cssBudgetBytes, error, fetchingValidatedUrl, hasErrorBoundary, postId, setAsyncError, setError, validatedUrl, validatedUrlsRestPath ] );

	return (
		<ValidatedUrl.Provider
			value={ {
				cssBudgetBytes,
				fetchingValidatedUrl,
				stylesheetSizes,
				validatedUrl,
			} }
		>
			{ children }
		</ValidatedUrl.Provider>
	);
}

ValidatedUrlProvider.propTypes = {
	children: PropTypes.any,
	cssBudgetBytes: PropTypes.number,
	hasErrorBoundary: PropTypes.bool,
	postId: PropTypes.number,
	validatedUrlsRestPath: PropTypes.string,
};
