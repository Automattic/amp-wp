/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarButton } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { ToolbarIcon } from '../../../components/icon';
import { PLUGIN_NAME, SIDEBAR_NAME } from '../../plugins/amp-block-validation';
import { useAMPDocumentToggle } from '../../hooks/use-amp-document-toggle';

/**
 * AMP button displaying in the block toolbar.
 *
 * @param {Object} props Component props.
 * @param {string} props.clientId Block Client ID.
 * @param {number} props.count The number of errors associated with the block.
 */
export function AMPToolbarButton( { clientId, count } ) {
	const { openGeneralSidebar } = useDispatch( 'core/edit-post' );

	const { isAMPEnabled } = useAMPDocumentToggle();

	if ( ! isAMPEnabled ) {
		return null;
	}

	return (
		<BlockControls>
			<ToolbarButton
				onClick={ () => {
					openGeneralSidebar( `${ PLUGIN_NAME }/${ SIDEBAR_NAME }` );
					// eslint-disable-next-line @wordpress/react-no-unsafe-timeout
					setTimeout( () => {
						const buttons = Array.from( document.querySelectorAll( `.error-${ clientId } button` ) );
						const firstButton = buttons[ 0 ];

						// Ensure all errors are expanded.
						// @todo This would be more elegant if this state were captured in the store?
						buttons.reverse(); // Reverse so that the first one is focused first.
						for ( const button of buttons ) {
							if ( 'false' === button.getAttribute( 'aria-expanded' ) ) {
								button.click();
							}
						}

						// Make sure the first is scrolled into view.
						if ( firstButton ) {
							firstButton.scrollIntoView( { block: 'start', inline: 'nearest', behavior: 'smooth' } );
						}
					} );
				} }
			>
				<ToolbarIcon count={ count } />
			</ToolbarButton>
		</BlockControls>
	);
}
AMPToolbarButton.propTypes = {
	clientId: PropTypes.string.isRequired,
	count: PropTypes.number.isRequired,
};
