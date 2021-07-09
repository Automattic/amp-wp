/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';

/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * Internal dependencies
 */
import { numberFormat } from '../../utils/number-format';

function getMemoryUnit( unit ) {
	if ( ! unit || typeof unit !== 'string' ) {
		return null;
	}

	switch ( unit.toLowerCase() ) {
		case 'b':
			return {
				name: __( 'bytes', 'amp' ),
				abbreviation: _x( 'B', 'abbreviation for bytes', 'amp' ),
			};
		case 'kb':
			return {
				name: __( 'kilobytes', 'amp' ),
				abbreviation: _x( 'kB', 'abbreviation for kilobytes', 'amp' ),
			};
		default:
			return null;
	}
}

export default function FormattedMemoryValue( { value, unit } ) {
	const memoryUnit = getMemoryUnit( unit );

	return (
		<>
			{ numberFormat( value ) }
			{ memoryUnit && (
				<>
					{ ' ' }
					<abbr title={ memoryUnit.name }>
						{ memoryUnit.abbreviation }
					</abbr>
				</>
			) }
		</>
	);
}
FormattedMemoryValue.propTypes = {
	value: PropTypes.oneOfType( [ PropTypes.number, PropTypes.string ] ).isRequired,
	unit: PropTypes.oneOf( [ 'b', 'B', 'kb', 'kB', 'KB' ] ),
};
