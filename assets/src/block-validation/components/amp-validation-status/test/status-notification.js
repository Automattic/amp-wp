/**
 * External dependencies
 */
import { act } from 'react-dom/test-utils';

/**
 * WordPress dependencies
 */
import { render, unmountComponentAtNode } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import AMPValidationStatusNotification from '../status-notification';

jest.mock( '@wordpress/data/build/components/use-select', () => jest.fn() );
jest.mock( '@wordpress/data/build/components/use-dispatch/use-dispatch', () => jest.fn() );

describe( 'AMPValidationStatusNotification', () => {
	let container;

	const autosave = jest.fn();
	const savePost = jest.fn();

	useDispatch.mockImplementation( () => ( { autosave, savePost } ) );

	function setupUseSelect( overrides ) {
		useSelect.mockImplementation( () => ( {
			fetchingErrorsRequestErrorMessage: '',
			isDraft: false,
			isEditedPostNew: false,
			isFetchingErrors: false,
			keptMarkupValidationErrorCount: 0,
			reviewLink: 'http://example.com',
			supportLink: 'http://example.com/support',
			unreviewedValidationErrorCount: 0,
			validationErrorCount: 0,
			...overrides,
		} ) );
	}

	beforeEach( () => {
		jest.clearAllMocks();

		container = document.createElement( 'div' );
		document.body.appendChild( container );
	} );

	afterEach( () => {
		unmountComponentAtNode( container );
		container.remove();
		container = null;
	} );

	it( 'does not render when errors are being fetched', () => {
		setupUseSelect( {
			isFetchingErrors: true,
		} );

		act( () => {
			render( <AMPValidationStatusNotification />, container );
		} );

		expect( container.children ).toHaveLength( 0 );
	} );

	it( 'renders message when the post is new', () => {
		setupUseSelect( {
			isEditedPostNew: true,
		} );

		act( () => {
			render( <AMPValidationStatusNotification />, container );
		} );

		expect( container.innerHTML ).toMatchSnapshot();
		expect( container.innerHTML ).toContain( 'Validation will be checked upon saving' );
		expect( container.querySelector( 'a[href="http://example.com"]' ) ).toBeNull();
	} );

	it( 'renders error message when API request error is present', () => {
		setupUseSelect( {
			fetchingErrorsRequestErrorMessage: 'request error message',
		} );

		act( () => {
			render( <AMPValidationStatusNotification />, container );
		} );

		expect( container.innerHTML ).toMatchSnapshot();
		expect( container.innerHTML ).toContain( 'request error message' );
		expect( container.querySelector( 'a[href="http://example.com"]' ) ).toBeNull();

		container.querySelector( 'button' ).click();
		expect( autosave ).toHaveBeenCalledWith( { isPreview: true } );

		setupUseSelect( {
			isDraft: true,
			fetchingErrorsRequestErrorMessage: 'request error message',
		} );

		act( () => {
			render( <AMPValidationStatusNotification />, container );
		} );
		container.querySelector( 'button' ).click();
		expect( savePost ).toHaveBeenCalledWith( { isPreview: true } );
	} );

	it( 'renders error message when there are kept issues', () => {
		setupUseSelect( {
			keptMarkupValidationErrorCount: 2,
		} );

		act( () => {
			render( <AMPValidationStatusNotification />, container );
		} );

		expect( container.innerHTML ).toMatchSnapshot();
		expect( container.innerHTML ).toContain( 'AMP is disabled due to invalid markup being kept for 2 issues.' );
		expect( container.querySelector( 'a[href="http://example.com"]' ) ).not.toBeNull();
	} );

	it( 'renders message when there are unreviewed issues', () => {
		setupUseSelect( {
			unreviewedValidationErrorCount: 3,
		} );

		act( () => {
			render( <AMPValidationStatusNotification />, container );
		} );

		expect( container.innerHTML ).toMatchSnapshot();
		expect( container.innerHTML ).toContain( 'AMP is valid, but 3 issues need review.' );
		expect( container.querySelector( 'a[href="http://example.com"]' ) ).not.toBeNull();
	} );

	it( 'renders message when there are reviewed issues', () => {
		setupUseSelect( {
			validationErrorCount: 1,
		} );

		act( () => {
			render( <AMPValidationStatusNotification />, container );
		} );

		expect( container.innerHTML ).toMatchSnapshot();
		expect( container.innerHTML ).toContain( 'AMP is valid. 1 issue was reviewed.' );
		expect( container.querySelector( 'a[href="http://example.com"]' ) ).not.toBeNull();
	} );

	it( 'renders message when there are no AMP validation errors', () => {
		setupUseSelect();

		act( () => {
			render( <AMPValidationStatusNotification />, container );
		} );

		expect( container.innerHTML ).toMatchSnapshot();
		expect( container.innerHTML ).toContain( 'No AMP validation issues detected.' );
	} );
} );

