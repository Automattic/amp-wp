/**
 * External dependencies
 */
import PropTypes from 'prop-types';
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { __ } from '@wordpress/i18n';
import './style.scss';

export class CopyButton extends Component {
	/**
	 * Prop Types.
	 */
	static propTypes = {
		className: PropTypes.string,
		value: PropTypes.string.isRequired,
	}

	/**
	 * To render component.
	 *
	 * @return {JSX.Element} Component markup.
	 */
	render() {
		const { className } = this.props;

		return (
			<Button
				className={ classnames( 'components-button--copy', className ) }
				onClick={ ( event ) => this.onClick( event ) }
			>
				{ __( 'Copy', 'amp' ) }
			</Button>
		);
	}

	/**
	 * On Click handler for copy button.
	 *
	 * @param {Object} event Click event objet.
	 */
	onClick( event ) {
		const element = event.target;
		const textArea = document.createElement( 'textarea' );
		document.body.appendChild( textArea );
		textArea.value = this.props.value || '';
		textArea.select();

		let status = false;

		try {
			status = document.execCommand( 'copy' );
		} catch ( exception ) {
		}

		document.body.removeChild( textArea );
		element.textContent = status ? __( 'Copied', 'amp' ) : __( 'Failed', 'amp' );
	}
}
