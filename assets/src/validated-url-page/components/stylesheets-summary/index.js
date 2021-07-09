/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { numberFormat } from '../../../utils/number-format';
import FormattedMemoryValue from '../../../components/formatted-memory-value';
import { ValidationStatusIcon } from '../../../components/icon';
import {
	AMPNotice,
	NOTICE_SIZE_LARGE,
	NOTICE_TYPE_WARNING,
	NOTICE_TYPE_ERROR,
} from '../../../components/amp-notice';
import {
	STYLESHEETS_BUDGET_STATUS_VALID,
	STYLESHEETS_BUDGET_STATUS_WARNING,
	STYLESHEETS_BUDGET_STATUS_EXCEEDED,
} from '../../helpers';

/**
 * Render stylesheets summary table.
 *
 * @param {Object} props Component props.
 * @param {Object} props.stylesheetSizes Stylesheet sizes object.
 */
export default function StylesheetsSummary( { stylesheetSizes } ) {
	const {
		included,
		excluded,
		usage: {
			actualPercentage,
			budgetBytes,
			status,
		},
	} = stylesheetSizes;

	return (
		<>
			<table className="amp-stylesheet-summary">
				<tbody>
					<tr>
						<th>
							{ __( 'Total CSS size prior to minification:', 'amp' ) }
						</th>
						<td>
							<FormattedMemoryValue value={ included.originalSize + excluded.originalSize } unit="B" />
						</td>
					</tr>
					<tr>
						<th>
							{ __( 'Total CSS size after minification:', 'amp' ) }
						</th>
						<td>
							<FormattedMemoryValue value={ included.finalSize + excluded.finalSize } unit="B" />
						</td>
					</tr>
					<tr>
						<th>
							{ __( 'Percentage of used CSS budget', 'amp' ) }
							{ budgetBytes && [ ' (', <FormattedMemoryValue value={ budgetBytes / 1000 } unit="kB" key="" />, ')' ] }
							{ ':' }
						</th>
						<td>
							{ `${ numberFormat( parseFloat( actualPercentage ).toFixed( 1 ) ) }%` }
							{ ' ' }
							{ status === STYLESHEETS_BUDGET_STATUS_EXCEEDED && (
								<ValidationStatusIcon isError isBoxed />
							) }
							{ status === STYLESHEETS_BUDGET_STATUS_WARNING && (
								<ValidationStatusIcon isWarning isBoxed />
							) }
							{ status === STYLESHEETS_BUDGET_STATUS_VALID && (
								<ValidationStatusIcon isValid isBoxed />
							) }
						</td>
					</tr>
					<tr>
						<th>
							{ sprintf(
								// translators: %d stands for the number of stylesheets
								_n(
									'Excluded minified CSS size (%d stylesheet):',
									'Excluded minified CSS size (%d stylesheets):',
									excluded.stylesheets.length,
									'amp',
								),
								excluded.stylesheets.length,
							) }
						</th>
						<td>
							<FormattedMemoryValue value={ excluded.finalSize } unit="B" />
						</td>
					</tr>
				</tbody>
			</table>
			{ status === STYLESHEETS_BUDGET_STATUS_WARNING && (
				<AMPNotice size={ NOTICE_SIZE_LARGE } type={ NOTICE_TYPE_WARNING }>
					{ __( 'You are nearing the limit of the CSS budget. Once this limit is reached, stylesheets deemed of lesser priority will be excluded from the page. Please review the stylesheets below and determine if the current theme or a particular plugin is including excessive CSS.', 'amp' ) }
				</AMPNotice>
			) }
			{ status === STYLESHEETS_BUDGET_STATUS_EXCEEDED && (
				<AMPNotice size={ NOTICE_SIZE_LARGE } type={ NOTICE_TYPE_ERROR }>
					{ excluded.stylesheets.length === 0
						? __( 'You have exceeded the CSS budget. The page will not be served as a valid AMP page.', 'amp' )
						: __( 'You have exceeded the CSS budget. Stylesheets deemed of lesser priority have been excluded from the page.', 'amp' ) }
					{ __( 'Please review the flagged stylesheets below and determine if the current theme or a particular plugin is including excessive CSS.', 'amp' ) }
				</AMPNotice>
			) }
		</>
	);
}
StylesheetsSummary.propTypes = {
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
		usage: PropTypes.shape( {
			actualPercentage: PropTypes.number,
			budgetBytes: PropTypes.number,
			status: PropTypes.oneOf( [
				STYLESHEETS_BUDGET_STATUS_VALID,
				STYLESHEETS_BUDGET_STATUS_WARNING,
				STYLESHEETS_BUDGET_STATUS_EXCEEDED,
			] ),
		} ),
	} ),
};
