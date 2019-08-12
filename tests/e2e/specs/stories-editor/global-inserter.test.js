/**
 * WordPress dependencies
 */
import { createNewPost, getAllBlocks } from '@wordpress/e2e-test-utils';

/**
 * Internal dependencies
 */
import { activateExperience, deactivateExperience, insertBlock } from '../../utils';

const INSERTER_SELECTOR = '#amp-story-inserter';
const CTA_BLOCK_NAME = 'Call to Action';

describe( 'Global Inserter', () => {
	beforeAll( async () => {
		await activateExperience( 'stories' );
	} );

	afterAll( async () => {
		await deactivateExperience( 'stories' );
	} );

	describe( 'Blocks Present', () => {
		it.each( [
			'Image',
			'Text',
			'Video',
			'Quote',
			'Story Author',
			'List',
			'Story Title',
			'Story Date',
			'Custom HTML',
			'Code',
			'Preformatted',
			'Pullquote',
			'Table',
			'Verse',
			'Page',
			'Embed',
		] )( 'has an expected block',
			async ( block ) => {
				await createNewPost( { postType: 'amp_story' } );
				await insertBlock( block );
				await page.click( INSERTER_SELECTOR );
				const inserter = await page.$( INSERTER_SELECTOR );
				await expect( inserter ).toMatch( block );
			}
		);
	} );

	it( 'should only show the CTA block when not on the first page', async () => {
		await createNewPost( { postType: 'amp_story' } );
		await page.click( INSERTER_SELECTOR );

		// This is on the first page, so it should not have the CTA block.
		expect( page ).not.toMatch( CTA_BLOCK_NAME );
		await page.click( INSERTER_SELECTOR );

		// Create and go to a second page.
		await insertBlock( 'Page' );
		await page.click( INSERTER_SELECTOR );

		// The inserter should have the CTA block.
		const inserter = await page.$( INSERTER_SELECTOR );
		await expect( inserter ).toMatch( CTA_BLOCK_NAME );
		await page.click( INSERTER_SELECTOR );

		// It should be possible to add this block.
		await insertBlock( CTA_BLOCK_NAME );
	} );

	it( 'should always add blocks as children of the current Page block', async () => {
		await createNewPost( { postType: 'amp_story' } );
		await insertBlock( 'Image' );
		await insertBlock( 'Video' );
		await insertBlock( 'Text' );

		// There should only be one 'parent' block, the Page block.
		const blocks = await getAllBlocks();
		expect( blocks.length ).toStrictEqual( 1 );

		// The 3 blocks added and the default text block should be innerBlocks of the Page block.
		expect( blocks[ 0 ].innerBlocks.length ).toStrictEqual( 4 );

		await insertBlock( 'Page' );

		// Now that there is a second Page block, there should be 2 blocks.
		expect( ( await getAllBlocks() ).length ).toStrictEqual( 2 );

		await insertBlock( 'Image' );

		// The Image block should simply be an innerBlock of the 2nd page, and the parent block count should remain at 2.
		expect( ( await getAllBlocks() )[ 1 ].innerBlocks.length ).toStrictEqual( 1 );
		expect( ( await getAllBlocks() ).length ).toStrictEqual( 2 );
	} );

	it( 'should always add Page blocks as top-level blocks, not innerBlocks', async () => {
		await createNewPost( { postType: 'amp_story' } );
		await insertBlock( 'Page' );
		const blocks = await getAllBlocks();

		// After adding a 2nd Page block, there should be 2 top-level blocks (2 pages).
		expect( blocks.length ).toStrictEqual( 2 );

		// The 1st Page should only have 1 innerBlock, the default Text block, not another Page.
		expect( blocks[ 0 ].innerBlocks.length ).toStrictEqual( 1 );

		await insertBlock( 'Page' );
		const blocksWithThreePages = await getAllBlocks();

		// After adding a 3rd Page block, there should be 3 top-level blocks (3 pages).
		expect( blocksWithThreePages.length ).toStrictEqual( 3 );

		// The 1st Page should still only have 1 innerBlock, the default Text block.
		expect( blocksWithThreePages[ 0 ].innerBlocks.length ).toStrictEqual( 1 );

		// The 2nd and 3rd pages should not have innerBlocks, as the inserter only added Pages.
		expect( blocksWithThreePages[ 1 ].innerBlocks.length ).toStrictEqual( 0 );
		expect( blocksWithThreePages[ 2 ].innerBlocks.length ).toStrictEqual( 0 );
	} );
} );
