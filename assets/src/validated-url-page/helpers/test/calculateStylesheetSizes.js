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
				original_size: 20,
				final_size: 3,
				included: true,
			},
			{
				original_size: 10,
				final_size: 2,
				included: true,
			},
			{
				original_size: 10,
				final_size: 0,
				included: true,
			},
			{
				original_size: 200,
				final_size: 30,
				included: false,
			},
			{
				original_size: 100,
				final_size: 20,
				included: false,
			},
			{
				original_size: 100,
				final_size: 0,
				included: false,
			},
		];
		expect( calculateStylesheetSizes( stylesheets ) ).toMatchObject( {
			included: {
				originalSize: 40,
				finalSize: 5,
			},
			excluded: {
				originalSize: 400,
				finalSize: 50,
			},
		} );
	} );
} );
