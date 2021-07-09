/**
 * WordPress dependencies
 */
import { render } from '@wordpress/element';

/**
 * External dependencies
 */
import { act } from 'react-dom/test-utils';

/**
 * Internal dependencies
 */
import FormattedMemoryValue from '..';

let container;

describe( 'FormattedMemoryValue', () => {
	beforeEach( () => {
		container = document.createElement( 'div' );
		document.body.appendChild( container );
	} );

	afterEach( () => {
		document.body.removeChild( container );
		container = null;
	} );

	it( 'prints bare value if no unit is provided', () => {
		act( () => {
			render(
				<FormattedMemoryValue value={ 123 } />,
				container,
			);
		} );

		expect( container.textContent ).toBe( '123' );
	} );

	it( 'prints correct output if value is a string', () => {
		act( () => {
			render(
				<FormattedMemoryValue value="234" />,
				container,
			);
		} );

		expect( container.textContent ).toBe( '234' );
	} );

	it( 'prints correct value and unit', () => {
		act( () => {
			render(
				<FormattedMemoryValue value="345" unit="B" />,
				container,
			);
		} );

		expect( container.textContent ).toBe( '345 B' );
		expect( container.querySelector( 'abbr' ) ).not.toBeNull();
		expect( container.querySelector( 'abbr' ).title ).toBe( 'bytes' );
	} );

	it.each( [
		[ 'bytes', 'B', 'B' ],
		[ 'bytes', 'b', 'B' ],
		[ 'kilobytes', 'kB', 'kB' ],
		[ 'kilobytes', 'kb', 'kB' ],
		[ 'kilobytes', 'KB', 'kB' ],
	] )(
		'prints correct %s value and unit for the following input unit: %s',
		( fullName, inputUnit, abbreviation ) => {
			act( () => {
				render(
					<FormattedMemoryValue value="100" unit={ inputUnit } />,
					container,
				);
			} );

			expect( container.querySelector( 'abbr' ).title ).toBe( fullName );
			expect( container.querySelector( 'abbr' ).textContent ).toBe( abbreviation );
		},
	);
} );
