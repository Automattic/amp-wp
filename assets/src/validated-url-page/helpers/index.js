/**
 * Calculate stylesheets sizes.
 *
 * Calculates total CSS size prior and after the minification based on the
 * stylesheets data.
 *
 * @param {Array} stylesheets List of stylesheets.
 * @return {Object|null} Stylesheets sizes data or null.
 */
export function calculateStylesheetSizes( stylesheets ) {
	if ( ! stylesheets || stylesheets?.length === 0 ) {
		return null;
	}

	return stylesheets.reduce( ( sizes, stylesheet ) => {
		const key = stylesheet.included === true ? 'included' : 'excluded';

		return {
			...sizes,
			[ key ]: {
				originalSize: sizes[ key ].originalSize + stylesheet.original_size,
				finalSize: sizes[ key ].finalSize + stylesheet.final_size,
			},
		};
	}, {
		included: {
			originalSize: 0,
			finalSize: 0,
		},
		excluded: {
			originalSize: 0,
			finalSize: 0,
		},
	} );
}
