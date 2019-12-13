/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Panel, Title, InputGroup, getCommonValue } from './shared';

function TextPanel( { selectedElements, onSetProperties } ) {
	const content = getCommonValue( selectedElements, 'content' );
	const [ state, setState ] = useState( { content } );
	useEffect( () => {
		setState( { content } );
	}, [ content ] );
	const handleSubmit = ( evt ) => {
		onSetProperties( state );
		evt.preventDefault();
	};
	return (
		<Panel onSubmit={ handleSubmit }>
			<Title>
				{ 'Text' }
			</Title>
			<InputGroup
				label="Text content"
				value={ state.content }
				isMultiple={ content === '' }
				onChange={ ( value ) => setState( { ...state, content: value } ) }
			/>
		</Panel>
	);
}

TextPanel.propTypes = {
	selectedElements: PropTypes.array.isRequired,
	onSetProperties: PropTypes.func.isRequired,
};

export default TextPanel;
