/**
 * Internal dependencies
 */
import { getUserLocale } from '../locale';

/**
 * Format a number using the locale in use by the user viewing the page.
 *
 * @param {number} number The number to format.
 * @return {string} Formatted number.
 */
export const numberFormat = ( number ) => {
	const locale = getUserLocale();

	return new Intl.NumberFormat( locale ).format( number );
};
