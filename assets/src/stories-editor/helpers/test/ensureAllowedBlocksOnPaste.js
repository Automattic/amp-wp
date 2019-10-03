/**
 * Internal dependencies
 */
import ensureAllowedBlocksOnPaste from '../ensureAllowedBlocksOnPaste';

const mockGetBlocksByClientId = jest.fn( () => [] );
const mockGetBlockOrder = jest.fn( () => [] );

jest.mock( '@wordpress/data', () => {
	return {
		select: () => ( {
			getBlocksByClientId: ( ...args ) => mockGetBlocksByClientId( ...args ),
			getBlockOrder: ( ...args ) => mockGetBlockOrder( ...args ),
		} ),
	};
} );

describe( 'pasting blocks', () => {
	describe( 'ensureAllowedBlocksOnPaste', () => {
		it( 'should not allow pasting blocks that are not allowed', () => {
			const blocks = [
				{
					name: 'amp/amp-story-cta',
				},
				{
					name: 'some-random-block',
				},
				{
					name: 'amp/amp-story-text',
				},
				{
					name: 'core/paragraph',
				},
			];
			const filteredBlocks = ensureAllowedBlocksOnPaste( blocks, 'abc123', true );
			expect( filteredBlocks ).toHaveLength( 1 );
			expect( filteredBlocks[ 0 ].name ).toStrictEqual( 'amp/amp-story-text' );
		} );
	} );
} );
