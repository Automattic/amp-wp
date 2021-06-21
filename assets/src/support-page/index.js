/* global ampSupportData */

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import '../css/variables.css';
import '../css/elements.css';
import '../css/core-components.css';
import './style.css';
import { ErrorContextProvider } from '../components/error-context-provider';
import { AMPSupport } from '../components/amp-support';

domReady( () => {
	const root = document.getElementById( 'amp-support-root' );
	const { args, data, action, nonce } = ampSupportData;

	if ( root ) {
		render( (
			<ErrorContextProvider>
				<AMPSupport args={ args } data={ data } action={ action } nonce={ nonce } />
			</ErrorContextProvider>
		), root );
	}
} );
