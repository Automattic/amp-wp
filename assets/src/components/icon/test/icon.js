/**
 * External dependencies
 */
import { act } from 'react-dom/test-utils';

/**
 * WordPress dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { MoreMenuIcon, ToolbarIcon, StatusIcon, ValidationStatusIcon } from '../index';

let container;

describe( 'Icons', () => {
	beforeEach( () => {
		container = document.createElement( 'ul' );
		document.body.appendChild( container );
	} );

	afterEach( () => {
		document.body.removeChild( container );
		container = null;
	} );

	it( 'renders a toolbar icon without AMP broken and no badge', () => {
		act( () => {
			render(
				<ToolbarIcon broken={ false } count={ 0 } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-toolbar-icon' ) ).not.toBeNull();
		expect( container.querySelector( '.amp-toolbar-icon--has-badge' ) ).toBeNull();
		expect( container.querySelector( '.amp-toolbar-broken-icon' ) ).toBeNull();
		expect( container.querySelector( '.amp-toolbar-broken-icon--has-badge' ) ).toBeNull();
	} );

	it( 'renders a toolbar icon without AMP broken and with a badge', () => {
		act( () => {
			render(
				<ToolbarIcon broken={ false } count={ 1 } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-toolbar-icon' ) ).not.toBeNull();
		expect( container.querySelector( '.amp-toolbar-icon--has-badge' ) ).not.toBeNull();
		expect( container.querySelector( '.amp-toolbar-broken-icon' ) ).toBeNull();
		expect( container.querySelector( '.amp-toolbar-broken-icon--has-badge' ) ).toBeNull();
	} );

	it( 'renders a toolbar icon with AMP broken and with no badge', () => {
		act( () => {
			render(
				<ToolbarIcon broken={ true } count={ 0 } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-toolbar-icon' ) ).toBeNull();
		expect( container.querySelector( '.amp-toolbar-icon--has-badge' ) ).toBeNull();
		expect( container.querySelector( '.amp-toolbar-broken-icon' ) ).not.toBeNull();
		expect( container.querySelector( '.amp-toolbar-broken-icon--has-badge' ) ).toBeNull();
	} );

	it( 'renders a toolbar icon with AMP broken and with a badge', () => {
		act( () => {
			render(
				<ToolbarIcon broken={ true } count={ 1 } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-toolbar-icon' ) ).toBeNull();
		expect( container.querySelector( '.amp-toolbar-icon--has-badge' ) ).toBeNull();
		expect( container.querySelector( '.amp-toolbar-broken-icon' ) ).not.toBeNull();
		expect( container.querySelector( '.amp-toolbar-broken-icon--has-badge' ) ).not.toBeNull();
	} );

	it( 'renders the MoreMenuIcon', () => {
		act( () => {
			render(
				<MoreMenuIcon broken={ true } count={ 1 } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-toolbar-icon' ) ).not.toBeNull();
		expect( container.querySelector( '.amp-toolbar-icon--has-badge' ) ).toBeNull();
	} );

	it( 'renders the StatusIcon', () => {
		act( () => {
			render(
				<StatusIcon />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-status-icon' ) ).not.toBeNull();
		expect( container.querySelector( '.amp-status-icon--broken' ) ).toBeNull();
	} );

	it( 'renders the broken StatusIcon', () => {
		act( () => {
			render(
				<StatusIcon broken={ true } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-status-icon--broken' ) ).not.toBeNull();
	} );

	it( 'renders the valid ValidationStatusIcon', () => {
		act( () => {
			render(
				<ValidationStatusIcon isValid={ true } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-validation-status-icon--valid' ) ).not.toBeNull();
	} );

	it( 'renders the warning ValidationStatusIcon', () => {
		act( () => {
			render(
				<ValidationStatusIcon isWarning={ true } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-validation-status-icon--warning' ) ).not.toBeNull();
	} );

	it( 'renders the error ValidationStatusIcon', () => {
		act( () => {
			render(
				<ValidationStatusIcon isError={ true } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-validation-status-icon--error' ) ).not.toBeNull();
	} );

	it( 'renders the boxed ValidationStatusIcon', () => {
		act( () => {
			render(
				<ValidationStatusIcon isValid={ true } isBoxed={ true } />,
				container,
			);
		} );

		expect( container.querySelector( '.amp-validation-status-icon--boxed' ) ).not.toBeNull();
	} );
} );
