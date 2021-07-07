/**
 * Internal dependencies
 */
const Changelog = require( './changelog' );

const milestone = process.argv[ 2 ];

const token = process.env.GITHUB_TOKEN;

new Changelog( 'ampproject/amp-wp', milestone, token ).generate()
	// eslint-disable-next-line no-console
	.then( ( changelog ) => console.log( changelog ) )
	// eslint-disable-next-line no-console
	.catch( console.error );
