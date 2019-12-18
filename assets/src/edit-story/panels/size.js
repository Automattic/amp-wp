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

function SizePanel( { selectedElements, onSetProperties } ) {
	const width = getCommonValue( selectedElements, 'width' );
	const height = getCommonValue( selectedElements, 'height' );
	const isFullbleed = getCommonValue( selectedElements, 'isFullbleed' );
	const [ state, setState ] = useState( { width, height } );
	useEffect( () => {
		setState( { width, height } );
	}, [ width, height ] );
	const handleSubmit = ( evt ) => {
		onSetProperties( state );
		evt.preventDefault();
	};
	return (
		<Panel onSubmit={ handleSubmit }>
			<Title>
				{ 'Size' }
			</Title>
			<InputGroup
				label="Width"
				value={ state.width }
				isMultiple={ width === '' }
				onChange={ ( value ) => setState( { ...state, width: isNaN( value ) || value === '' ? '' : parseFloat( value ) } ) }
				postfix="px"
				disabled={ isFullbleed }
			/>
			<InputGroup
				label="Height"
				value={ state.height }
				isMultiple={ height === '' }
				onChange={ ( value ) => setState( { ...state, height: isNaN( value ) || value === '' ? '' : parseFloat( value ) } ) }
				postfix="px"
				disabled={ isFullbleed }
			/>
		</Panel>
	);
}

SizePanel.propTypes = {
	selectedElements: PropTypes.array.isRequired,
	onSetProperties: PropTypes.func.isRequired,
};

export default SizePanel;
