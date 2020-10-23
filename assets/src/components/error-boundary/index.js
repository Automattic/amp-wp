/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ErrorScreen } from '../error-screen';

/**
 * Catches errors in the application and displays a fallback screen.
 *
 * @see https://reactjs.org/docs/error-boundaries.html
 */
export class ErrorBoundary extends Component {
	static propTypes = {
		children: PropTypes.any,
		exitLink: PropTypes.string,
		fullScreen: PropTypes.bool,
	}

	constructor( props ) {
		super( props );

		this.timeout = null;
		this.state = { error: null };
	}

	componentDidMount() {
		this.mounted = true;
	}

	componentWillUnmount() {
		this.mounted = false;
	}

	componentDidCatch( error ) {
		this.setState( { error } );
	}

	render() {
		const { error } = this.state;
		const { children, exitLink, fullScreen } = this.props;

		if ( error && fullScreen ) {
			return (
				<ErrorScreen error={ error } finishLink={ exitLink } />
			);
		}

		return children;
	}
}
