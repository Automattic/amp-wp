/**
 * External dependencies
 */
import { create } from 'react-test-renderer';

/**
 * Internal dependencies
 */
import { CopyButton } from '..';

describe( 'CopyButton', () => {
	it( 'matches snapshots', () => {
		// Normal listing.
		const wrapper = create(
			<CopyButton value="Some Text" />,
		);

		expect( wrapper.toJSON() ).toMatchSnapshot();
	} );
} );
