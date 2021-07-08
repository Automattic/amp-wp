/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import FormattedMemoryValue from '../../../components/formatted-memory-value';

export default function StylesheetsSummary( { stylesheetSizes } ) {
	return (
		<table className="amp-stylesheet-summary">
			<tbody>
				<tr>
					<th>
						{ __( 'Total CSS size prior to minification:', 'amp' ) }
					</th>
					<td>
						<FormattedMemoryValue value={ stylesheetSizes.included.originalSize } unit="B" />
					</td>
				</tr>
				<tr>
					<th>
						{ __( 'Total CSS size after minification:', 'amp' ) }
					</th>
					<td>
						<FormattedMemoryValue value={ stylesheetSizes.included.finalSize } unit="B" />
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
