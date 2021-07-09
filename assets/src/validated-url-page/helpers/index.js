export const STYLESHEETS_BUDGET_STATUS_VALID = 'valid';
export const STYLESHEETS_BUDGET_STATUS_WARNING = 'warning';
export const STYLESHEETS_BUDGET_STATUS_EXCEEDED = 'exceeded';

/**
 * Calculate stylesheets sizes.
 *
 * Calculates total CSS size prior and after the minification based on the
 * stylesheets data.
 *
 * @param {Array} stylesheets List of stylesheets.
 * @param {number} cssBudgetBytes CSS budget value in bytes.
 * @param {number} cssBudgetWarningPercentage CSS budget warning level percentage.
 * @return {Object|null} Stylesheets sizes data or null.
 */
export function calculateStylesheetSizes( stylesheets, cssBudgetBytes, cssBudgetWarningPercentage ) {
	if ( ! stylesheets || stylesheets?.length === 0 ) {
		return null;
	}

	const initialState = {
		included: {
			originalSize: 0,
			finalSize: 0,
			stylesheets: [],
		},
		excessive: {
			stylesheets: [],
		},
		excluded: {
			originalSize: 0,
			finalSize: 0,
			stylesheets: [],
		},
		budget: {
			usage: 0,
			status: STYLESHEETS_BUDGET_STATUS_VALID,
		},
	};

	const result = stylesheets
		// Determine which stylesheets are included based on their priorities.
		.sort( ( a, b ) => a.priority - b.priority )
		.reduce( ( sizes, stylesheet ) => {
			// Skip duplicate stylesheets and invalid groups.
			if ( stylesheet?.duplicate || stylesheet.group !== 'amp-custom' ) {
				return sizes;
			}

			// Excluded stylesheet.
			if ( ! stylesheet.included ) {
				return {
					...sizes,
					excluded: {
						originalSize: sizes.excluded.originalSize + stylesheet.original_size,
						finalSize: sizes.excluded.finalSize + stylesheet.final_size,
						stylesheets: [
							...sizes.excluded.stylesheets,
							stylesheet.hash,
						],
					},
				};
			}

			const isExcessive = sizes.included.finalSize + stylesheet.final_size >= cssBudgetBytes;

			return {
				...sizes,
				included: {
					originalSize: sizes.included.originalSize + stylesheet.original_size,
					finalSize: sizes.included.finalSize + stylesheet.final_size,
					stylesheets: ! isExcessive
						? [ ...sizes.included.stylesheets, stylesheet.hash ]
						: sizes.included.stylesheets,
				},
				excessive: {
					stylesheets: isExcessive
						? [ ...sizes.excessive.stylesheets, stylesheet.hash ]
						: sizes.excessive.stylesheets,
				},
			};
		}, initialState );

	// Calculate CSS budget used.
	result.budget.usage = ( result.included.finalSize + result.excluded.finalSize ) / cssBudgetBytes * 100;

	if ( result.budget.usage > 100 ) {
		result.budget.status = STYLESHEETS_BUDGET_STATUS_EXCEEDED;
	} else if ( result.budget.usage > cssBudgetWarningPercentage ) {
		result.budget.status = STYLESHEETS_BUDGET_STATUS_WARNING;
	}

	return result;
}
