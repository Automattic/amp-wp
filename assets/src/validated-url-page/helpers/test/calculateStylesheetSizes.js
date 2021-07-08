/**
 * Internal dependencies
 */
import { calculateStylesheetSizes } from '..';

describe( 'calculateStylesheetSizes', () => {
	it( 'returns null if no stylesheets are provided', () => {
		expect( calculateStylesheetSizes() ).toBeNull();
		expect( calculateStylesheetSizes( [] ) ).toBeNull();
	} );

	it( 'returns correct sizes prior and after minification', () => {
		const stylesheets = [
			{
				hash: 'excessive-1',
				included: true,
				priority: 10,
				group: 'amp-custom',
				original_size: 200,
				final_size: 30,
			},
			{
				hash: 'included',
				included: true,
				priority: 1,
				group: 'amp-custom',
				original_size: 100,
				final_size: 20,
			},
			{
				hash: 'excessive-2',
				included: true,
				priority: 100,
				group: 'amp-custom',
				original_size: 100,
				final_size: 0,
			},
			{
				hash: 'excluded-3',
				included: false,
				priority: 90,
				group: 'amp-custom',
				original_size: 200,
				final_size: 30,
			},
			{
				hash: 'excluded-1',
				included: false,
				priority: 5,
				group: 'amp-custom',
				original_size: 100,
				final_size: 20,
			},
			{
				hash: 'excluded-2',
				included: false,
				priority: 10,
				group: 'amp-custom',
				original_size: 100,
				final_size: 0,
			},
		];
		expect( calculateStylesheetSizes( stylesheets, 25 ) ).toMatchObject( {
			included: {
				originalSize: 400,
				finalSize: 50,
				stylesheets: [ 'included' ],
			},
			excessive: {
				stylesheets: [ 'excessive-1', 'excessive-2' ],
			},
			excluded: {
				originalSize: 400,
				finalSize: 50,
				stylesheets: [ 'excluded-1', 'excluded-2', 'excluded-3' ],
			},
			budgetUsed: 4,
		} );
	} );

	it( 'ignores groups other than amp-custom and duplicate stylesheets', () => {
		const stylesheets = [
			{
				hash: 'included',
				group: 'amp-custom',
				included: true,
				priority: 1,
				original_size: 100,
				final_size: 20,
			},
			{
				hash: 'ignored',
				group: 'foo-bar',
				included: true,
				priority: 100,
			},
			{
				hash: 'included',
				group: 'amp-custom',
				duplicate: true,
				priority: 1,
			},
		];

		const result = calculateStylesheetSizes( stylesheets, 75000 );
		expect( result.included.stylesheets ).toHaveLength( 1 );
		expect( result.included.stylesheets ).toContain( 'included' );
	} );
} );
