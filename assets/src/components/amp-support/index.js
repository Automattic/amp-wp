/**
 * External dependencies
 */
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import { __ } from '@wordpress/i18n';
import { ListItem } from '../ListItem';
import { Selectable } from '../selectable';
import { CopyButton } from '../copy-button';
import { AMPNotice } from '../amp-notice';

export class AMPSupport extends Component {
	/**
	 * Prop Types.
	 */
	static propTypes = {
		action: PropTypes.string.isRequired,
		nonce: PropTypes.string.isRequired,
		args: PropTypes.any,
		data: PropTypes.object.isRequired,
	}

	/**
	 * Construct method.
	 */
	constructor() {
		super();

		this.state = {
			uuid: null,
			error: null,
		};
	}

	/**
	 * To render component.
	 *
	 * @return {JSX.Element} Component markup.
	 */
	render() {
		const { data } = this.props;

		return (
			<div className="amp-support">
				<Selectable>
					<h2 className="amp-support__heading">
						{ __( 'AMP Support', 'amp' ) }
					</h2>
					<div className="amp-support__body">

						{ this._renderSiteInfo() }

						{ this._renderTheme() }

						{ this._renderPlugins() }

						<details open={ false }>
							<summary>
								{ __( 'Errors', 'amp' ) }
								{
									( () => {
										return ` (${ data.errors.length || 0 })`;
									} )()
								}
							</summary>
							<div className="detail-body">
								<p>
									<i>
										<small>
											{ __( 'Please check "Raw Data" for all error information.', 'amp' ) }
										</small>
									</i>
								</p>
							</div>
						</details>

						<details open={ false }>
							<summary>
								{ __( 'Error Sources', 'amp' ) }
								{
									( () => {
										return ` (${ data.error_sources.length || 0 })`;
									} )()
								}
							</summary>
							<div className="detail-body">
								<p>
									<i>
										<small>
											{ __( 'Please check "Raw Data" for all error source information.', 'amp' ) }
										</small>
									</i>
								</p>
							</div>
						</details>

						{ this._renderValidatedUrls() }

						{ this._renderRawData() }
					</div>
					<div className="amp-support__footer">
						<Button
							disabled={ Boolean( this.state.uuid ) }
							className="components-button--send-button is-primary"
							onClick={ this.submitData }
						>
							{ __( 'Send data', 'amp' ) }
						</Button>
						{ this.state.uuid ? this._renderUUID() : '' }
						{ this.state.error ? this._renderError() : '' }
					</div>
				</Selectable>
			</div>
		);
	}

	/**
	 * Render site information.
	 *
	 * @private
	 * @return {JSX.Element} Site information markup.
	 */
	_renderSiteInfo() {
		const { data } = this.props;

		if ( 'object' !== typeof data.site_info ) {
			return null;
		}

		const siteInfo = data.site_info;

		return (
			<details open={ false }>
				<summary>
					{ __( 'Site Information', 'amp' ) }
				</summary>
				<div className="detail-body">
					<ListItem
						heading={ __( 'Site Information', 'amp' ) }
						items={ [
							{ label: 'Site URL', value: siteInfo?.site_url },
							{ label: 'Site title', value: siteInfo?.site_title },
							{ label: 'PHP version', value: siteInfo?.php_version },
							{ label: 'MySQL version', value: siteInfo?.mysql_version },
							{ label: 'WordPress version', value: siteInfo?.wp_version },
							{ label: 'WordPress language', value: siteInfo?.wp_language },
						] } />
					<ListItem
						heading={ __( 'Site Health', 'amp' ) }
						items={ [
							{ label: 'Https status', value: siteInfo?.wp_https_status ? 'Yes' : 'No' },
							{ label: 'Object cache status', value: siteInfo?.object_cache_status ? 'Yes' : 'No' },
							{ label: 'Libxml version', value: siteInfo?.libxml_version },
							{ label: 'Is defined curl multi', value: siteInfo?.is_defined_curl_multi ? 'Yes' : 'No' },
						] } />
					<ListItem
						heading={ __( 'AMP Information', 'amp' ) }
						items={ [
							{ label: 'AMP mode', value: siteInfo?.amp_mode },
							{ label: 'AMP version', value: siteInfo?.amp_version },
							{ label: 'AMP plugin configured', value: siteInfo?.amp_plugin_configured ? 'Yes' : 'No' },
							{
								label: 'AMP all templates supported',
								value: siteInfo?.amp_all_templates_supported ? 'Yes' : 'No',
							},
							{
								label: 'AMP supported post types',
								value: siteInfo?.amp_supported_post_types ? siteInfo.amp_supported_post_types.join( ', ' ) : '',
							},
							{
								label: 'AMP supported templates',
								value: siteInfo?.amp_supported_templates ? siteInfo.amp_supported_templates.join( ', ' ) : '',
							},
							{ label: 'AMP mobile redirect', value: siteInfo?.amp_mobile_redirect ? 'Yes' : 'No' },
							{ label: 'AMP reader theme', value: siteInfo?.amp_reader_theme },
						] } />
				</div>
			</details>
		);
	}

	/**
	 * To render theme information.
	 *
	 * @private
	 * @return {JSX.Element} Theme markup.
	 */
	_renderTheme() {
		const { data } = this.props;

		if ( 'object' !== typeof data.themes ) {
			return null;
		}

		return (
			<details open={ false }>
				<summary>
					{ __( 'Theme', 'amp' ) }
				</summary>
				<div className="detail-body">
					<ListItem
						className="list-items--list-style-disc"
						items={ data.themes.map( ( item ) => {
							return { value: `${ item.name } ${ item.version ? '(' + item.version + ')' : '' }` };
						} ) }
					/>
				</div>
			</details>
		);
	}

	/**
	 * To render plugins information.
	 *
	 * @private
	 * @return {JSX.Element} Plugins markup.
	 */
	_renderPlugins() {
		const { data } = this.props;

		if ( 'object' !== typeof data.plugins ) {
			return null;
		}

		return (
			<details open={ false }>
				<summary>
					{ __( 'Plugins', 'amp' ) }
					{
						( () => {
							return ` (${ data.plugins.length || 0 })`;
						} )()
					}
				</summary>
				<div className="detail-body">
					<ListItem
						className="list-items--list-style-disc"
						items={ data.plugins.map( ( item ) => {
							return { value: `${ item.name } ${ item.version ? '(' + item.version + ')' : '' }` };
						} ) }
					/>
				</div>
			</details>
		);
	}

	/**
	 * Render validated URls
	 *
	 * @private
	 * @return {JSX.Element} Validated URL markup.
	 */
	_renderValidatedUrls() {
		const { data } = this.props;

		if ( 'object' !== typeof data.urls ) {
			return null;
		}

		const urls = data.urls.map( ( item ) => item.url ? item.url : null );

		return (
			<details open={ false }>
				<summary>
					{ __( 'Validated URLs', 'amp' ) }
					{
						( () => {
							return ` (${ data.urls.length || 0 })`;
						} )()
					}
				</summary>
				<div className="detail-body">
					<ListItem
						className="list-items--list-style-disc"
						items={ urls.map( ( url ) => {
							return {
								value: () => {
									return (
										<a href={ url } title={ url } target="_blank" rel="noreferrer">
											{ url }
										</a>
									);
								},
							};
						} ) }
					/>
				</div>
			</details>
		);
	}

	/**
	 * To render raw data.
	 *
	 * @private
	 * @return {JSX.Element} Raw data markup
	 */
	_renderRawData() {
		const { data } = this.props;
		return (
			<details open={ false }>
				<summary>
					{ __( 'Raw Data', 'amp' ) }
				</summary>
				<pre className="amp-support__raw-data detail-body">
					{ JSON.stringify( data, null, 4 ) }
				</pre>
			</details>
		);
	}

	/**
	 * To render UUID Notice
	 *
	 * @private
	 * @return {JSX.Element} UUID notice markup.
	 */
	_renderUUID() {
		if ( this.state.uuid ) {
			return (
				<AMPNotice type="info" size="small">
					{ __( 'Support UUID: ', 'amp' ) }
					<code>
						{ this.state.uuid }
					</code>
					<CopyButton value={ this.state.uuid } />
				</AMPNotice>
			);
		}

		return null;
	}

	/**
	 * To render error notice.
	 *
	 * @private
	 * @return {JSX.Element} Error notice markup.
	 */
	_renderError() {
		if ( this.state.error ) {
			return (
				<AMPNotice type="error" size="small">
					{ this.state.error }
				</AMPNotice>
			);
		}

		return null;
	}

	/**
	 * Event callback for send button.
	 *
	 * @param {Object} event Event Object.
	 */
	submitData = ( event ) => {
		const { ajaxurl: wpAjaxUrl } = global;

		const element = event.target;
		const previousText = element.textContent;
		element.disabled = true;
		element.textContent = __( 'Sending…', 'amp' );

		/**
		 * Ajax callback.
		 */
		( async () => {
			this.setState( { uuid: null, error: null } );

			try {
				const body = new global.FormData();
				body.append( 'action', this.props.action );
				body.append( '_wpnonce', this.props.nonce );

				for ( const key in this.props.args ) {
					if ( this.props.args[ key ] ) {
						this.props.args[ key ].map( ( value ) => {
							return body.append( `args[${ key }][]`, value );
						} );
					}
				}

				/**
				 * @type {{ok: boolean}}
				 */
				let response = await global.fetch( wpAjaxUrl, {
					method: 'POST',
					body,
				} );

				element.disabled = false;
				element.textContent = previousText;

				if ( ! response.ok ) {
					throw new Error( __( 'Failed to send support request. Please try again after some time', 'amp' ) );
				}

				response = await response.json();

				if ( undefined !== response.success && undefined !== response.data ) {
					this.setState( {
						uuid: response.data.uuid,
					} );
				} else {
					throw new Error( __( 'Failed to send support request. Please try again after some time', 'amp' ) );
				}
			} catch ( exception ) {
				this.setState( { error: exception.message } );
			}
		} )();
	}
}
