/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { useConfig } from '../';
import Context from './context';

function APIProvider( { children } ) {
	const { api: { stories, media } } = useConfig();

	const getStoryById = useCallback(
		( storyId ) => apiFetch( { path: `${ stories }/${ storyId }?context=edit` } ),
		[ stories ],
	);

	const getMedia = useCallback(
		( { mediaType } ) => {
			let apiPath = media;
			const perPage = 100;
			apiPath = addQueryArgs( apiPath, { per_page: perPage } );

			if ( mediaType ) {
				apiPath = addQueryArgs( apiPath, { media_type: mediaType } );
			}

			return apiFetch( { path: apiPath } )
				.then( ( data ) => data.map(
					( {
						guid: { rendered: src },
						media_details: { width: origWidth, height: origHeight },
					} ) => ( {
						src,
						origWidth,
						origHeight,
					} ),
				) );
		},	[ media ],
	);

	const state = {
		actions: {
			getStoryById,
			getMedia,
		},
	};

	return (
		<Context.Provider value={ state }>
			{ children }
		</Context.Provider>
	);
}

APIProvider.propTypes = {
	children: PropTypes.oneOfType( [
		PropTypes.arrayOf( PropTypes.node ),
		PropTypes.node,
	] ).isRequired,
};

export default APIProvider;
