/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { numberFormat } from '../../../utils/number-format';
import FormattedMemoryValue from '../../../components/formatted-memory-value';
import { ValidationStatusIcon } from '../../../components/icon';

/**
 * Render stylesheets summary table.
 *
 * @param {Object} props Component props.
 * @param {number} props.cssBudgetBytes CSS budget value in bytes.
 * @param {Object} props.stylesheetSizes Stylesheet sizes object.
 */
export default function StylesheetsSummary( { cssBudgetBytes, stylesheetSizes } ) {
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
				<tr>
					<th>
						{ __( 'Percentage of used CSS budget', 'amp' ) }
						{ cssBudgetBytes && [ ' (', <FormattedMemoryValue value={ cssBudgetBytes / 1000 } unit="kB" key="" />, ')' ] }
						{ ':' }
					</th>
					<td>
						{ `${ numberFormat( parseFloat( stylesheetSizes.budget.usage ).toFixed( 1 ) ) }%` }
						{ ' ' }
						{ stylesheetSizes.budget.status === 'exceeded' && <ValidationStatusIcon type="error" boxed /> }
						{ stylesheetSizes.budget.status === 'warning' && <ValidationStatusIcon type="warning" boxed /> }
						{ stylesheetSizes.budget.status === 'valid' && <ValidationStatusIcon type="valid" boxed /> }
					</td>
				</tr>
				<tr>
					<th>
						{ sprintf(
							// translators: %d stands for the number of stylesheets
							__( 'Excluded minified CSS size (%d stylesheets):', 'amp' ),
							stylesheetSizes.excluded.stylesheets.length,
						) }
					</th>
					<td>
						<FormattedMemoryValue value={ stylesheetSizes.excluded.finalSize } unit="B" />
					</td>
				</tr>
			</tbody>
		</table>
	);
}
StylesheetsSummary.propTypes = {
	cssBudgetBytes: PropTypes.number,
	stylesheetSizes: PropTypes.shape( {
		included: PropTypes.shape( {
			originalSize: PropTypes.number,
			finalSize: PropTypes.number,
			stylesheets: PropTypes.arrayOf( PropTypes.string ),
		} ),
		excessive: PropTypes.shape( {
			stylesheets: PropTypes.arrayOf( PropTypes.string ),
		} ),
		excluded: PropTypes.shape( {
			originalSize: PropTypes.number,
			finalSize: PropTypes.number,
			stylesheets: PropTypes.arrayOf( PropTypes.string ),
		} ),
		budget: PropTypes.shape( {
			usage: PropTypes.number,
			status: PropTypes.oneOf( [ 'valid', 'warning', 'exceeded' ] ),
		} ),
	} ),
};
