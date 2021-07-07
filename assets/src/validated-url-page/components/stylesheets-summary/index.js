/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';

export default function StylesheetsSummary( { stylesheetSizes } ) {
	return (
		<table className="amp-stylesheet-summary">
			<tbody>
				<tr>
					<th>
						{ __( 'Total CSS size prior to minification:', 'amp' ) }
					</th>
					<td>
						{ stylesheetSizes.included.originalSize }
						<abbr title={ __( 'bytes', 'amp' ) }>
							{ _x( 'B', 'abbreviation for bytes', 'amp' ) }
						</abbr>
					</td>
				</tr>
				<tr>
					<th>
						{ __( 'Total CSS size after minification:', 'amp' ) }
					</th>
					<td>
						{ stylesheetSizes.included.finalSize }
						<abbr title={ __( 'bytes', 'amp' ) }>
							{ _x( 'B', 'abbreviation for bytes', 'amp' ) }
						</abbr>
					</td>
				</tr>
			</tbody>
		</table>
	);
}
StylesheetsSummary.propTypes = {
	stylesheetSizes: PropTypes.shape( {
		included: PropTypes.shape( {
			originalSize: PropTypes.number,
			finalSize: PropTypes.number,
		} ),
		excluded: PropTypes.shape( {
			originalSize: PropTypes.number,
			finalSize: PropTypes.number,
		} ),
	} ),
};
