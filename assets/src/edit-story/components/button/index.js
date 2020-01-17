/**
 * External dependencies
 */
import styled from 'styled-components';

/**
 * Internal dependencies
 */
import UndoIcon from './icons/undo.svg';
import RedoIcon from './icons/redo.svg';
import LeftArrowIcon from './icons/arrow_left.svg';
import RightArrowIcon from './icons/arrow_right.svg';
import GridViewIcon from './icons/grid_view.svg';

const Base = styled.button.attrs(
	( { isDisabled } ) => ( { disabled: isDisabled } ),
)`
	border-width: 1px;
	border-style: solid;
	border-radius: 4px;
	background: transparent;
	display: block;
	min-width: ${ ( { isIcon } ) => isIcon ? 'initial' : '63px' };
	line-height: 28px;
	height: 30px;
	padding: 0 10px;
	cursor: pointer;

	&:focus, &:active {
		outline: none;
	}

	svg {
		width: 1em;
	}

	${ ( { disabled } ) => disabled && `
		pointer-events: none;
		opacity: .3;
	` }
`;

const StyledButton = styled( Base )`
	border: none;
	padding: 0;
	width: ${ ( { width } ) => width }px;
	height: ${ ( { height } ) => height }px;
	min-width: initial;
	visibility: ${ ( { isHidden } ) => isHidden ? 'hidden' : 'visible' };
	opacity: .3;
	color: ${ ( { theme } ) => theme.colors.fg.v1 };
	&:focus, &:active, &:hover {
		opacity: 1;
	}
	svg {
		width: ${ ( { width } ) => width }px;
		height: ${ ( { height } ) => height }px;
	}
`;

export const Primary = styled( Base )`
	border-color: ${ ( { theme } ) => theme.colors.action };
	background-color: ${ ( { theme } ) => theme.colors.action };
	color: ${ ( { theme } ) => theme.colors.fg.v1 };
`;

export const Secondary = styled( Base )`
	border-color: ${ ( { theme } ) => theme.colors.fg.v1 };
	background-color: ${ ( { theme } ) => theme.colors.fg.v3 };
	color: ${ ( { theme } ) => theme.colors.bg.v5 };
`;

export const Outline = styled( Base )`
	border-color: ${ ( { theme } ) => theme.colors.fg.v2 };
	color: ${ ( { theme } ) => theme.colors.fg.v1 };
`;

export const LeftArrow = ( props ) => (
	<StyledButton { ...props }>
		<LeftArrowIcon />
	</StyledButton>
);

export const RightArrow = ( props ) => (
	<StyledButton { ...props }>
		<RightArrowIcon />
	</StyledButton>
);

export const Undo = ( props ) => (
	<Outline isIcon { ...props }>
		<UndoIcon />
	</Outline>
);

export const Redo = ( props ) => (
	<Outline isIcon { ...props }>
		<RedoIcon />
	</Outline>
);

export const GridView = ( props ) => (
	<StyledButton { ...props }>
		<GridViewIcon />
	</StyledButton>
);
