/**
 * External dependencies
 */
import styled from 'styled-components';

/**
 * Internal dependencies
 */
import { PAGE_NAV_WIDTH, PAGE_WIDTH, PAGE_HEIGHT } from '../../constants';
import Page from './page';
import PageMenu from './pagemenu';
import PageNav from './pagenav';
import Carousel from './carousel';
import SelectionCanvas from './selectionCanvas';

const Background = styled.div`
	background-color: ${ ( { theme } ) => theme.colors.bg.v1 };
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 100%;

	display: grid;
	grid:
    ".         .         .         .         .       " 1fr
    ".         prev      page      next      .       " ${ PAGE_HEIGHT }px
    ".         .         menu      .         .       " 48px
    ".         .         .         .         .       " 1fr
    "carousel  carousel  carousel  carousel  carousel" auto
    / 1fr ${ PAGE_NAV_WIDTH }px ${ PAGE_WIDTH }px ${ PAGE_NAV_WIDTH }px 1fr;
`;

const Area = styled.div`
	grid-area: ${ ( { area } ) => area };
	height: 100%;
	width: 100%;
`;

function CanvasLayout() {
	return (
		<SelectionCanvas>
			<Background>
				<Area area="page">
					<Page />
				</Area>
				<Area area="menu">
					<PageMenu />
				</Area>
				<Area area="prev">
					<PageNav isNext={ false } />
				</Area>
				<Area area="next">
					<PageNav />
				</Area>
				<Area area="carousel">
					<Carousel />
				</Area>
			</Background>
		</SelectionCanvas>
	);
}

export default CanvasLayout;
