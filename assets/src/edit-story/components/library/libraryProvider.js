/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { useState, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useAPI } from '../../app';
import Context from './context';

const MEDIA = 'media';
const TEXT = 'text';
const SHAPES = 'shapes';
const LINKS = 'links';

function LibraryProvider( { children } ) {
	const { actions: { getMedia } } = useAPI();
	const [ media, setMedia ] = useState( [] );
	const [ mediaType, setMediaType ] = useState( '' );
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ isMediaLoaded, setIsMediaLoaded ] = useState( false );
	const [ isMediaLoading, setIsMediaLoading ] = useState( false );
	const [ tab, setTab ] = useState( MEDIA );

	const loadMedia = useCallback( () => {
		if ( ! isMediaLoaded && ! isMediaLoading ) {
			setIsMediaLoading( true );
			getMedia( { mediaType, searchTerm } ).then( ( loadedMedia ) => {
				setIsMediaLoading( false );
				setIsMediaLoaded( true );
				setMedia( loadedMedia );
			} );
		}
	}, [ isMediaLoaded, isMediaLoading, getMedia, mediaType, searchTerm ] );

	const state = {
		state: {
			tab,
			media,
			isMediaLoading,
			isMediaLoaded,
			mediaType,
			searchTerm,
		},
		actions: {
			setTab,
			setIsMediaLoading,
			setIsMediaLoaded,
			setMediaType,
			loadMedia,
			setSearchTerm,
		},
		data: {
			tabs: {
				MEDIA,
				TEXT,
				SHAPES,
				LINKS,
			},
		},
	};

	return (
		<Context.Provider value={ state }>
			{ children }
		</Context.Provider>
	);
}

LibraryProvider.propTypes = {
	children: PropTypes.oneOfType( [
		PropTypes.arrayOf( PropTypes.node ),
		PropTypes.node,
	] ).isRequired,
};

export default LibraryProvider;
