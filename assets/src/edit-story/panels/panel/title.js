/**
 * External dependencies
 */
import styled from 'styled-components';
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { useContext, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import useInspector from '../../components/inspector/useInspector';
import panelContext from './context';
import DragHandle from './handle';
import Arrow from './arrow.svg';

const Header = styled.header`
	background-color: ${ ( { theme, isPrimary } ) => isPrimary ? theme.colors.fg.v2 : theme.colors.fg.v1 };
	border: 0px solid ${ ( { theme } ) => theme.colors.fg.v2 };
	border-top-width: ${ ( { isPrimary } ) => isPrimary ? 0 : '1px' };
	color: ${ ( { theme } ) => theme.colors.bg.v2 };
	display: flex;
	padding: 10px 20px;
	justify-content: space-between;
	align-items: center;
	position: relative;
`;

const Heading = styled.h1`
	color: inherit;
	margin: 0;
	font-weight: 500;
	font-size: 16px;
	line-height: 19px;
`;

const Collapse = styled.button.attrs( { type: 'button' } )`
	color: inherit;
	width: 28px;
	height: 28px;
	border: 0;
	padding: 0;
	background: transparent;
	display: flex; // removes implicit line-height padding from child element
	cursor: pointer;

	svg {
		width: 28px;
		height: 28px;
		${ ( { isCollapsed } ) => isCollapsed && `transform: rotate(.5turn);` }
	}
`;

function Title( { children, isPrimary, isResizable } ) {
	const {
		state: { isCollapsed },
		actions: { collapse, expand, setHeight },
	} = useContext( panelContext );
	const { state: { inspectorContentHeight } } = useInspector();

	// Max panel height is set to 70% of full available height.
	const maxHeight = Math.round( inspectorContentHeight * 0.7 );

	const handleHeightChange = useCallback(
		( deltaHeight ) => setHeight( ( height ) => Math.max( 0, Math.min( maxHeight, height + deltaHeight ) ) ),
		[ setHeight, maxHeight ],
	);

	return (
		<Header isPrimary={ isPrimary }>
			{ isResizable && ! isCollapsed && (
				<DragHandle handleHeightChange={ handleHeightChange } />
			) }
			<Heading>
				{ children }
			</Heading>
			<Collapse isCollapsed={ isCollapsed } onClick={ isCollapsed ? expand : collapse }>
				<Arrow />
			</Collapse>
		</Header>
	);
}

Title.propTypes = {
	children: PropTypes.oneOfType( [
		PropTypes.arrayOf( PropTypes.node ),
		PropTypes.node,
	] ).isRequired,
	isPrimary: PropTypes.bool,
	isResizable: PropTypes.bool,
};

Title.defaultProps = {
	isPrimary: false,
	isResizable: false,
};

export default Title;
