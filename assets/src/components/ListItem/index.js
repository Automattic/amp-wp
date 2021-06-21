/**
 * External dependencies
 */
import PropTypes from 'prop-types';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import './style.scss';

export function ListItem( { className = '', heading, items } ) {
	return (
		<ul className={ classnames( 'list-items', className ) }>
			{
				( () => {
					if ( heading ) {
						return (
							<li className="list-items__item">
								<h4 className="list-items__heading">
									{ heading }
								</h4>
							</li>
						);
					}

					return null;
				} )()
			}
			{ items.map( ( item, index ) => {
				return (
					<li key={ index } className="list-items__item">
						{
							( () => {
								if ( item.label ) {
									return (
										<strong className="list-items__item-key">
											{ item.label }
										</strong>
									);
								}

								return null;
							} )()
						}
						{
							( () => {
								let markup = '-';

								if ( item.value ) {
									markup = (
										<span className="list-items__item-value">
											{ 'function' === typeof item.value ? item.value() : item.value }
										</span>
									);
								}

								return markup;
							} )()
						}
					</li>
				);
			} ) }
		</ul>
	);
}

ListItem.propTypes = {
	className: PropTypes.string,
	heading: PropTypes.string,
	items: PropTypes.arrayOf( PropTypes.shape( {
		label: PropTypes.string,
		value: PropTypes.oneOfType( [
			PropTypes.string,
			PropTypes.number,
			PropTypes.func,
		] ).isRequired,
	} ) ).isRequired,
};
