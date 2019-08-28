/**
 * External dependencies
 */
import classnames from 'classnames';
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import { withSelect } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { ResizableBox } from '@wordpress/components';
import isShallowEqual from '@wordpress/is-shallow-equal';

/**
 * Internal dependencies
 */
import withSnapTargets from '../higher-order/with-snap-targets';
import './edit.css';
import {
	getPercentageFromPixels,
	getPixelsFromPercentage,
	findClosestSnap,
	getBlockInnerElement,
} from '../../helpers';
import {
	getBlockPositioning,
	getResizedBlockPosition,
	getUpdatedBlockPosition,
	getResizedWidthAndHeight,
	getRadianFromDeg,
	getBlockTextElement,
} from './helpers';

import {
	STORY_PAGE_INNER_HEIGHT,
	STORY_PAGE_INNER_WIDTH,
	TEXT_BLOCK_PADDING,
	REVERSE_WIDTH_CALCULATIONS,
	REVERSE_HEIGHT_CALCULATIONS,
	BLOCK_DRAGGING_SNAP_GAP,
} from '../../constants';

let lastSeenX = 0,
	lastSeenY = 0,
	lastWidth,
	lastHeight,
	blockElement = null,
	blockElementTop,
	blockElementLeft,
	lastDeltaW,
	lastDeltaH,
	imageWrapper,
	textBlockWrapper,
	textElement;

class EnhancedResizableBox extends Component {
	constructor( ...args ) {
		super( ...args );
		this.state = {
			isResizing: false,
		};
	}

	render() {
		const {
			angle,
			blockName,
			ampFitText,
			minWidth,
			minHeight,
			onResizeStart,
			onResizeStop,
			children,
			...otherProps
		} = this.props;

		let {
			width,
			height,
		} = this.props;

		const { isResizing } = this.state;

		const isImage = 'core/image' === blockName;
		const isText = 'amp/amp-story-text' === blockName;

		// Ensure that these props are not passed down.
		const {
			clientId,
			snapGap,
			horizontalSnaps,
			verticalSnaps,
			snapLines,
			showSnapLines,
			hideSnapLines,
			setSnapLines,
			clearSnapLines,
			parentBlockOffsetTop,
			parentBlockOffsetLeft,
			...childProps
		} = otherProps;

		return (
			<ResizableBox
				{ ...childProps }
				className={ classnames(
					'amp-story-resize-container',
					{
						'is-resizing': isResizing,
					},
				) }
				size={ {
					height,
					width,
				} }
				enable={ {
					top: true,
					right: true,
					bottom: true,
					left: true,
					topRight: true,
					bottomRight: true,
					bottomLeft: true,
					topLeft: true,
				} }
				onResizeStop={ ( event, direction ) => {
					const { deltaW, deltaH } = getResizedWidthAndHeight( event, angle, lastSeenX, lastSeenY, direction );
					let appliedWidth = width + deltaW;
					let appliedHeight = height + deltaH;

					// Restore the full height for Text block wrapper.
					if ( textBlockWrapper ) {
						textBlockWrapper.style.height = '100%';
					}

					// Ensure the measures not crossing limits.
					appliedWidth = appliedWidth < lastWidth ? lastWidth : appliedWidth;
					appliedHeight = appliedHeight < lastHeight ? lastHeight : appliedHeight;

					const elementTop = parseFloat( blockElement.style.top );
					const elementLeft = parseFloat( blockElement.style.left );

					const positionTop = Number( elementTop.toFixed( 2 ) );
					const positionLeft = Number( elementLeft.toFixed( 2 ) );

					this.setState( { isResizing: false } );

					hideSnapLines();
					if ( snapLines.length ) {
						clearSnapLines();
					}

					onResizeStop( {
						width: parseInt( appliedWidth ),
						height: parseInt( appliedHeight ),
						positionTop,
						positionLeft,
					} );
				} }
				onResizeStart={ ( event, direction, element ) => {
					lastSeenX = event.clientX;
					lastSeenY = event.clientY;
					lastWidth = width;
					lastHeight = height;
					lastDeltaW = null;
					lastDeltaH = null;
					blockElement = element.closest( '.wp-block' );
					blockElementTop = blockElement.style.top;
					blockElementLeft = blockElement.style.left;
					if ( isImage ) {
						imageWrapper = blockElement.querySelector( 'figure .components-resizable-box__container' );
					}
					textElement = ! ampFitText ? getBlockTextElement( blockName, blockElement ) : null;

					if ( ampFitText && isText ) {
						textBlockWrapper = blockElement.querySelector( '.with-line-height' );
					} else {
						textBlockWrapper = null;
					}

					this.setState( { isResizing: true } );

					showSnapLines();
					if ( snapLines.length ) {
						clearSnapLines();
					}

					this.horizontalSnaps = horizontalSnaps();
					this.verticalSnaps = verticalSnaps();

					onResizeStart();
				} }
				onResize={ ( event, direction, element ) => { // eslint-disable-line complexity
					const newSnapLines = [];

					const { deltaW, deltaH } = getResizedWidthAndHeight( event, angle, lastSeenX, lastSeenY, direction );

					// Handle case where media is inserted from URL.
					if ( isImage && ! width && ! height ) {
						width = blockElement.clientWidth;
						height = blockElement.clientHeight;
					}

					let appliedWidth = minWidth <= width + deltaW ? width + deltaW : minWidth;
					let appliedHeight = minHeight <= height + deltaH ? height + deltaH : minHeight;

					const isReducing = 0 > deltaW || 0 > deltaH;

					if ( textElement && isReducing ) {
						// If we have a rotated block, let's assign the width and height for measuring.
						// Without assigning the new measure, the calculation would be incorrect due to angle.
						// Text block is handled differently since the text block's content shouldn't have full width while measuring.
						if ( angle ) {
							if ( ! isText ) {
								textElement.style.width = appliedWidth + 'px';
								textElement.style.height = appliedHeight + 'px';
							} else if ( isText && ! ampFitText ) {
								textElement.style.width = 'initial';
							}
						}

						const scrollWidth = textElement.scrollWidth;
						const scrollHeight = textElement.scrollHeight;
						if ( appliedWidth <= scrollWidth || appliedHeight <= scrollHeight ) {
							appliedWidth = lastWidth;
							appliedHeight = lastHeight;
						}
						// If we have rotated block, let's restore the correct measures.
						if ( angle ) {
							if ( ! isText ) {
								textElement.style.width = 'initial';
								textElement.style.height = '100%';
							} else if ( isText && ! ampFitText ) {
								textElement.style.width = '100%';
							}
						}
					}

					// Is it's not min width / height yet, assign lastDeltaH and lastDeltaW for position calculation.
					if ( minHeight < appliedHeight ) {
						lastDeltaH = deltaH;
					}
					if ( minWidth < appliedWidth ) {
						lastDeltaW = deltaW;
					}

					const snappingEnabled = ! event.getModifierState( 'Alt' );

					const dimensions = blockElement.getBoundingClientRect();

					// We calculate with the block's actual dimensions relative to the page it's on.
					let {
						top: actualTop,
						right: actualRight,
						bottom: actualBottom,
						left: actualLeft,
					} = dimensions;

					actualTop -= parentBlockOffsetTop;
					actualRight -= parentBlockOffsetLeft;
					actualBottom -= parentBlockOffsetTop;
					actualLeft -= parentBlockOffsetLeft;

					const horizontalCenter = actualLeft + ( ( actualRight - actualLeft ) / 2 );
					const verticalCenter = actualTop + ( ( actualBottom - actualTop ) / 2 );

					const horizontalLeftSnap = findClosestSnap( actualLeft, this.horizontalSnaps, BLOCK_DRAGGING_SNAP_GAP );
					const horizontalRightSnap = findClosestSnap( actualRight, this.horizontalSnaps, BLOCK_DRAGGING_SNAP_GAP );
					const horizontalCenterSnap = findClosestSnap( horizontalCenter, this.horizontalSnaps, BLOCK_DRAGGING_SNAP_GAP );
					const verticalTopSnap = findClosestSnap( actualTop, this.verticalSnaps, BLOCK_DRAGGING_SNAP_GAP );
					const verticalBottomSnap = findClosestSnap( actualBottom, this.verticalSnaps, BLOCK_DRAGGING_SNAP_GAP );
					const verticalCenterSnap = findClosestSnap( verticalCenter, this.verticalSnaps, BLOCK_DRAGGING_SNAP_GAP );

					if ( horizontalLeftSnap !== null ) {
						const snapLine = [ [ horizontalLeftSnap, 0 ], [ horizontalLeftSnap, STORY_PAGE_INNER_HEIGHT ] ];
						newSnapLines.push( snapLine );
					}

					if ( horizontalRightSnap !== null ) {
						const snapLine = [ [ horizontalRightSnap, 0 ], [ horizontalRightSnap, STORY_PAGE_INNER_HEIGHT ] ];
						newSnapLines.push( snapLine );
					}

					if ( horizontalCenterSnap !== null ) {
						const snapLine = [ [ horizontalCenterSnap, 0 ], [ horizontalCenterSnap, STORY_PAGE_INNER_HEIGHT ] ];
						newSnapLines.push( snapLine );
					}

					if ( verticalTopSnap !== null ) {
						const snapLine = [ [ 0, verticalTopSnap ], [ STORY_PAGE_INNER_WIDTH, verticalTopSnap ] ];
						newSnapLines.push( snapLine );
					}

					if ( verticalBottomSnap !== null ) {
						const snapLine = [ [ 0, verticalBottomSnap ], [ STORY_PAGE_INNER_WIDTH, verticalBottomSnap ] ];
						newSnapLines.push( snapLine );
					}

					if ( verticalCenterSnap !== null ) {
						const snapLine = [ [ 0, verticalCenterSnap ], [ STORY_PAGE_INNER_WIDTH, verticalCenterSnap ] ];
						newSnapLines.push( snapLine );
					}

					if ( ! angle ) {
						// If the resizing is to left or top then we have to compensate
						if ( REVERSE_WIDTH_CALCULATIONS.includes( direction ) ) {
							let leftInPx = getPixelsFromPercentage( 'x', parseFloat( blockElementLeft ) );

							leftInPx = leftInPx - lastDeltaW;

							if ( lastDeltaW ) {
								if ( horizontalLeftSnap !== null && snappingEnabled ) {
									appliedWidth += leftInPx - horizontalLeftSnap;
									leftInPx = horizontalLeftSnap;
								}
							}

							blockElement.style.left = getPercentageFromPixels( 'x', leftInPx ) + '%';
						} else if ( lastDeltaW ) {
							if ( horizontalRightSnap !== null && snappingEnabled ) {
								appliedWidth = horizontalRightSnap - blockElement.offsetLeft;
							}
						}

						if ( REVERSE_HEIGHT_CALCULATIONS.includes( direction ) ) {
							let topInPx = getPixelsFromPercentage( 'y', parseFloat( blockElementTop ) );

							topInPx = topInPx - lastDeltaH;

							if ( lastDeltaH ) {
								if ( verticalTopSnap !== null && snappingEnabled ) {
									appliedHeight += topInPx - verticalTopSnap;
									topInPx = verticalTopSnap;
								}
							}

							blockElement.style.top = getPercentageFromPixels( 'y', topInPx ) + '%';
						} else if ( lastDeltaH ) {
							if ( verticalBottomSnap !== null && snappingEnabled ) {
								appliedHeight = verticalBottomSnap - blockElement.offsetTop;
							}
						}
					} else {
						const radianAngle = getRadianFromDeg( angle );

						// Compare position between the initial and after resizing.
						let initialPosition, resizedPosition;

						// If it's a text block, we shouldn't consider the added padding for measuring.
						if ( isText ) {
							initialPosition = getBlockPositioning( width - ( TEXT_BLOCK_PADDING * 2 ), height - ( TEXT_BLOCK_PADDING * 2 ), radianAngle, direction );
							resizedPosition = getBlockPositioning( appliedWidth - ( TEXT_BLOCK_PADDING * 2 ), appliedHeight - ( TEXT_BLOCK_PADDING * 2 ), radianAngle, direction );
						} else {
							initialPosition = getBlockPositioning( width, height, radianAngle, direction );
							resizedPosition = getBlockPositioning( appliedWidth, appliedHeight, radianAngle, direction );
						}
						const diff = {
							left: resizedPosition.left - initialPosition.left,
							top: resizedPosition.top - initialPosition.top,
						};

						const originalPos = getResizedBlockPosition( direction, blockElementLeft, blockElementTop, lastDeltaW, lastDeltaH );
						const updatedPos = getUpdatedBlockPosition( direction, originalPos, diff );

						// @todo: Do actual snapping.

						blockElement.style.left = getPercentageFromPixels( 'x', updatedPos.left ) + '%';
						blockElement.style.top = getPercentageFromPixels( 'y', updatedPos.top ) + '%';
					}

					element.style.width = appliedWidth + 'px';
					element.style.height = appliedHeight + 'px';

					lastWidth = appliedWidth;
					lastHeight = appliedHeight;

					if ( textBlockWrapper ) {
						if ( ampFitText ) {
							textBlockWrapper.style.lineHeight = appliedHeight + 'px';
						}
						// Also add the height to the wrapper since the background color is set to the wrapper.
						textBlockWrapper.style.height = appliedHeight + 'px';
					}

					// If it's image, let's change the width and height of the image, too.
					if ( imageWrapper && isImage ) {
						imageWrapper.style.width = appliedWidth + 'px';
						imageWrapper.style.height = appliedHeight + 'px';
					}

					const hasSnapLine = ( item ) => snapLines.find( ( snapLine ) => isShallowEqual( item[ 0 ], snapLine[ 0 ] ) && isShallowEqual( item[ 1 ], snapLine[ 1 ] ) );

					if ( newSnapLines.length ) {
						if ( ! newSnapLines.every( hasSnapLine ) ) {
							setSnapLines( ...newSnapLines );
						}
					} else if ( snapLines.length ) {
						clearSnapLines();
					}
				} }
			>
				{ children }
			</ResizableBox>
		);
	}
}

EnhancedResizableBox.defaultProps = {
	snapGap: 0,
};

EnhancedResizableBox.propTypes = {
	ampFitText: PropTypes.bool,
	angle: PropTypes.number,
	blockName: PropTypes.string,
	clientId: PropTypes.string,
	minWidth: PropTypes.number,
	minHeight: PropTypes.number,
	onResizeStart: PropTypes.func.isRequired,
	onResizeStop: PropTypes.func.isRequired,
	children: PropTypes.any.isRequired,
	width: PropTypes.number,
	height: PropTypes.number,
	horizontalSnaps: PropTypes.oneOfType( [
		PropTypes.arrayOf( PropTypes.number ),
		PropTypes.func,
	] ).isRequired,
	verticalSnaps: PropTypes.oneOfType( [
		PropTypes.arrayOf( PropTypes.number ),
		PropTypes.func,
	] ).isRequired,
	snapGap: PropTypes.number.isRequired,
	snapLines: PropTypes.array.isRequired,
	showSnapLines: PropTypes.func.isRequired,
	hideSnapLines: PropTypes.func.isRequired,
	setSnapLines: PropTypes.func.isRequired,
	clearSnapLines: PropTypes.func.isRequired,
	parentBlockOffsetTop: PropTypes.number.isRequired,
	parentBlockOffsetLeft: PropTypes.number.isRequired,
};

/**
 * Enhances the default snap targets specifically for resizing.
 *
 * This allows resizing a block so that it can be nicely centered.
 *
 * @type {Component}
 */
const withResizingSnapTargets = withSelect( ( select, ownProps ) => {
	const { getBlock } = select( 'core/block-editor' );

	const {
		clientId,
		horizontalSnaps,
		verticalSnaps,
		parentBlockOffsetTop,
		parentBlockOffsetLeft,
	} = ownProps;

	return {
		horizontalSnaps: () => {
			const blockInnerElement = getBlockInnerElement( getBlock( clientId ) );

			if ( ! blockInnerElement ) {
				return horizontalSnaps();
			}

			const dimensions = blockInnerElement.getBoundingClientRect();

			// We calculate with the block's actual dimensions relative to the page it's on.
			let { right: actualRight, left: actualLeft } = dimensions;

			actualRight -= parentBlockOffsetLeft;
			actualLeft -= parentBlockOffsetLeft;

			return [
				...horizontalSnaps(),
				...[ STORY_PAGE_INNER_WIDTH - actualLeft, STORY_PAGE_INNER_WIDTH - actualRight ],
			];
		},
		verticalSnaps: () => {
			const blockInnerElement = getBlockInnerElement( getBlock( clientId ) );

			if ( ! blockInnerElement ) {
				return horizontalSnaps();
			}

			const dimensions = blockInnerElement.getBoundingClientRect();

			// We calculate with the block's actual dimensions relative to the page it's on.
			let { top: actualTop, bottom: actualBottom } = dimensions;

			actualTop -= parentBlockOffsetTop;
			actualBottom -= parentBlockOffsetTop;

			return [
				...verticalSnaps(),
				...[ STORY_PAGE_INNER_HEIGHT - actualTop, STORY_PAGE_INNER_HEIGHT - actualBottom ],
			];
		},
	};
} );

const enhance = compose(
	withSnapTargets,
	withResizingSnapTargets,
);

export default enhance( EnhancedResizableBox );
