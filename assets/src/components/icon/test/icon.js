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
import { MoreMenuIcon, ToolbarIcon, StatusIcon } from '../index';

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
} );
