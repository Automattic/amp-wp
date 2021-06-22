/**
 * External dependencies
 */
import { create } from 'react-test-renderer';

/**
 * Internal dependencies
 */
import { ListItem } from '..';

describe( 'ListItem', () => {
	it( 'matches snapshots', () => {
		// Normal listing.
		let wrapper = create(
			<ListItem
				items={
					[
						{ value: 'Item 1' },
						{ value: 'Item 2' },
					]
				}
			/>,
		);

		expect( wrapper.toJSON() ).toMatchSnapshot();

		// Listing with keys.
		wrapper = create(
			<ListItem
				items={
					[
						{ label: 'Label 1', value: 'Item 1' },
						{ label: 'Label 2', value: 'Item 2' },
					]
				}
			/>,
		);

		expect( wrapper.toJSON() ).toMatchSnapshot();

		// Listing with hading.
		wrapper = create(
			<ListItem
				heading="List heading"
				items={
					[
						{ label: 'Label 1', value: 'Item 1' },
						{ label: 'Label 2', value: 'Item 2' },
					]
				}
			/>,
		);

		expect( wrapper.toJSON() ).toMatchSnapshot();
	} );
} );
