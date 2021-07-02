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
import { ErrorScreen } from '../components/error-screen';
import { ErrorBoundary } from '../components/error-boundary';

domReady( () => {
	const root = document.getElementById( 'amp-support-root' );
	const { args, data, action, nonce } = ampSupportData;

	const errorHandler = ( event ) => {
		// Handle only own errors.
		if ( event.filename && /amp-support(\.min)?\.js/.test( event.filename ) ) {
			render( <ErrorScreen error={ event.error } />, root );
		}
	};

	global.addEventListener( 'error', errorHandler );

	if ( root ) {
		render( (
			<ErrorContextProvider>
				<ErrorBoundary>
					<AMPSupport args={ args } data={ data } action={ action } nonce={ nonce } />
				</ErrorBoundary>
			</ErrorContextProvider>
		), root );
	}
} );
