/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { BLOCK_VALIDATION_STORE_KEY } from '../store';
import { MoreMenuIcon, ToolbarIcon } from '../../components/icon';
import { Sidebar } from '../components/sidebar';
import { InvalidBlockOutline } from '../components/invalid-block-outline';
import { usePostDirtyStateChanges } from '../hooks/use-post-dirty-state-changes';
import { useValidationErrorStateUpdates } from '../hooks/use-validation-error-state-updates';
import { useAMPDocumentToggle } from '../hooks/use-amp-document-toggle';

export const PLUGIN_NAME = 'amp-block-validation';
export const SIDEBAR_NAME = 'amp-editor-sidebar';
export const PLUGIN_TITLE = __( 'AMP Validation', 'amp' );
export const PLUGIN_ICON = MoreMenuIcon;

/**
 * Provides a dedicated sidebar for the plugin, with toggle buttons in the editor toolbar and more menu.
 */
export default function AMPBlockValidation() {
	const { broken, errorCount } = useSelect( ( select ) => ( {
		broken: select( BLOCK_VALIDATION_STORE_KEY ).getAMPCompatibilityBroken(),
		errorCount: select( BLOCK_VALIDATION_STORE_KEY ).getUnreviewedValidationErrors()?.length || 0,
	} ), [] );
	const { isAMPEnabled } = useAMPDocumentToggle();

	useValidationErrorStateUpdates();
	usePostDirtyStateChanges();

	if ( ! isAMPEnabled ) {
		return null;
	}

	return (
		<>
			<PluginSidebarMoreMenuItem
				icon={ <PLUGIN_ICON /> }
				target={ SIDEBAR_NAME }
			>
				{ PLUGIN_TITLE }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				className={ `${ PLUGIN_NAME }-sidebar` }
				icon={ (
					<ToolbarIcon count={ errorCount } broken={ broken } />
				) }
				name={ SIDEBAR_NAME }
				title={ PLUGIN_TITLE }
			>

				<Sidebar />
				<InvalidBlockOutline />
			</PluginSidebar>
		</>
	);
}
