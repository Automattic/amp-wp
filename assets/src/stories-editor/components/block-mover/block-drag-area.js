/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * Internal dependencies
 */
import BlockDraggable from './block-draggable';

export const BlockDragArea = ( { children, blockName, className, onDragStart, onDragEnd, blockElementId, clientId } ) => {
	return (
		<BlockDraggable
			clientId={ clientId }
			blockElementId={ blockElementId }
			blockName={ blockName }
			onDragStart={ onDragStart }
			onDragEnd={ onDragEnd }
		>
			{
				( { onDraggableStart, onDraggableEnd, isDragging } ) => (
					<div
						className={ className }
						onDragStart={ onDraggableStart }
						onDragEnd={ onDraggableEnd }
						aria-grabbed={ isDragging }
						draggable
					>
						{ children }
					</div>
				) }
		</BlockDraggable>
	);
};

BlockDragArea.propTypes = {
	icon: PropTypes.object,
	isVisible: PropTypes.bool,
	className: PropTypes.string,
	onDragStart: PropTypes.func,
	onDragEnd: PropTypes.func,
	blockElementId: PropTypes.string,
	blockName: PropTypes.string,
	clientId: PropTypes.string,
	children: PropTypes.any.isRequired,
};
