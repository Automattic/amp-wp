/**
 * External dependencies
 */
import PropTypes from 'prop-types';

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

/**
 * Settings page application root.
 */
function Root( ) {
	return '';
}

Root.propTypes = {
	appRoot: PropTypes.instanceOf( global.Element ),
};

domReady( () => {
	const root = document.getElementById( 'amp-support-root' );

	if ( root ) {
		render( (
			<ErrorContextProvider>
				<Root appRoot={ root } />
			</ErrorContextProvider>
		), root );
	}
} );
