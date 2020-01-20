/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Panel, Title, InputGroup, getCommonValue } from './shared';

function ScalePanel( { selectedElements, onSetProperties } ) {
	const scale = getCommonValue( selectedElements, 'scale' );
	const focalX = getCommonValue( selectedElements, 'focalX' );
	const focalY = getCommonValue( selectedElements, 'focalY' );
	const [ state, setState ] = useState( { scale, focalX, focalY } );
	useEffect( () => {
		setState( { scale, focalX, focalY } );
	}, [ scale, focalX, focalY ] );
	const handleSubmit = ( evt ) => {
		onSetProperties( {
			scale: typeof state.scale === 'string' ? null : state.scale,
			focalX: typeof state.focalX === 'string' ? null : state.focalX,
			focalY: typeof state.focalY === 'string' ? null : state.focalY,
		} );
		evt.preventDefault();
	};
	return (
		<Panel onSubmit={ handleSubmit }>
			<Title>
				{ __( 'Image actual size', 'amp' ) }
			</Title>
			<InputGroup
				label="Scale"
				value={ typeof state.scale === 'number' ? state.scale : '(auto)' }
				isMultiple={ scale === '' }
				onChange={ ( value ) => setState( { ...state, scale: isNaN( value ) || value === '' ? '(auto)' : parseFloat( value ) } ) }
				postfix="%"
			/>
			<InputGroup
				label="Focal X"
				value={ typeof state.focalX === 'number' ? state.focalX : '(auto)' }
				isMultiple={ focalX === '' }
				onChange={ ( value ) => setState( { ...state, focalX: isNaN( value ) || value === '' ? '(auto)' : parseFloat( value ) } ) }
				postfix="%"
			/>
			<InputGroup
				label="Focal Y"
				value={ typeof state.focalY === 'number' ? state.focalY : '(auto)' }
				isMultiple={ focalY === '' }
				onChange={ ( value ) => setState( { ...state, focalY: isNaN( value ) || value === '' ? '(auto)' : parseFloat( value ) } ) }
				postfix="%"
			/>
		</Panel>
	);
}

ScalePanel.propTypes = {
	selectedElements: PropTypes.array.isRequired,
	onSetProperties: PropTypes.func.isRequired,
};

export default ScalePanel;
