<?php
/**
 * Class AMP_Core_Theme_Sanitizer_Test.
 *
 * @package AMP
 */

/**
 * Class AMP_Core_Theme_Sanitizer_Test
 */
class AMP_Core_Theme_Sanitizer_Test extends WP_UnitTestCase {

	/**
	 * Call a private method as if it was public.
	 *
	 * @param object $object      Object instance to call the method on.
	 * @param string $method_name Name of the method to call.
	 * @param array  $args        Optional. Array of arguments to pass to the method.
	 * @return mixed Return value of the method call.
	 * @throws ReflectionException If the object could not be reflected upon.
	 */
	private function call_private_method( $object, $method_name, $args = [] ) {
		$method = ( new ReflectionClass( $object ) )->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $args );
	}

	/**
	 * Set a private property as if it was public.
	 *
	 * @param object $object        Object instance to the property of.
	 * @param string $property_name Name of the property to set.
	 * @param mixed  $value         Value to set the property to.
	 * @throws ReflectionException If the object could not be reflected upon.
	 */
	private function set_private_property( $object, $property_name, $value ) {
		$property = ( new ReflectionClass( $object ) )->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}

	public function get_xpath_from_css_selector_data() {
		return [
			// Single element.
			[ 'body', '//body' ],
			// Simple ID.
			[ '#some-id', "//*[ @id = 'some-id' ]" ],
			// Simple class.
			[ '.some-class', "//*[ @class and contains( concat( ' ', normalize-space( @class ), ' ' ), ' some-class ' ) ]" ],
			// Class descendants.
			[ '.some-class .other-class', "//*[ @class and contains( concat( ' ', normalize-space( @class ), ' ' ), ' some-class ' ) ]//*[ @class and contains( concat( ' ', normalize-space( @class ), ' ' ), ' other-class ' ) ]" ],
			// Class direct descendants.
			[ '.some-class > .other-class', "//*[ @class and contains( concat( ' ', normalize-space( @class ), ' ' ), ' some-class ' ) ]/*[ @class and contains( concat( ' ', normalize-space( @class ), ' ' ), ' other-class ' ) ]" ],
			// ID direct descendant elements.
			[ '#some-id > ul', "//*[ @id = 'some-id' ]/ul" ],
			// ID direct descendant elements with messy whitespace.
			[ "   \t  \n #some-id    \t  >   \n  ul  \t \n ", "//*[ @id = 'some-id' ]/ul" ],
		];
	}

	/**
	 * Test xpath_from_css_selector().
	 *
	 * @dataProvider get_xpath_from_css_selector_data
	 * @covers AMP_Core_Theme_Sanitizer::xpath_from_css_selector()
	 */
	public function test_xpath_from_css_selector( $css_selector, $expected ) {
		$dom       = new DOMDocument();
		$sanitizer = new AMP_Core_Theme_Sanitizer( $dom );
		$actual    = $this->call_private_method( $sanitizer, 'xpath_from_css_selector', [ $css_selector ] );
		$this->assertEquals( $expected, $actual );
	}

	public function get_get_closest_submenu_data() {
		$html  = '
			<nav>
				<ul class="primary-menu">
					<li id="menu-item-1" class="menu-item menu-item-1"><a href="https://example.com/a">Link A</a></li>
					<li id="menu-item-2" class="menu-item menu-item-2"><a href="https://example.com/b">Link B</a><span class="icon"></span>
						<ul id="sub-menu-1" class="sub-menu">
							<li id="menu-item-3" class="menu-item menu-item-3"><a href="https://example.com/c">Link C</a></li>
							<li id="menu-item-4" class="menu-item menu-item-4"><a href="https://example.com/d">Link D</a></li>
						</ul>
					</li>
					<li id="menu-item-5" class="menu-item menu-item-5"><a href="https://example.com/e">Link E</a><span class="icon"></span>
						<ul id="sub-menu-2" class="sub-menu">
							<li id="menu-item-6" class="menu-item menu-item-6"><a href="https://example.com/f">Link F</a><span class="icon"></span>
								<ul id="sub-menu-3" class="sub-menu">
									<li id="menu-item-7" class="menu-item menu-item-7"><a href="https://example.com/g">Link G</a></li>
									<li id="menu-item-8" class="menu-item menu-item-8"><a href="https://example.com/h">Link H</a></li>
								</ul>
							</li>
							<li id="menu-item-9" class="menu-item menu-item-9"><a href="https://example.com/i">Link I</a></li>
						</ul>
					</li>
				</ul>
			</nav>
		';
		$dom   = AMP_DOM_Utils::get_dom_from_content( $html );
		$xpath = new DOMXPath( $dom );
		return [
			// First sub-menu.
			[ $dom, $xpath, $xpath->query( "//*[ @id = 'menu-item-2' ]" )->item( 0 ), $xpath->query( "//*[ @id = 'sub-menu-1' ]" )->item( 0 ) ],

			// Second sub-menu.
			[ $dom, $xpath, $xpath->query( "//*[ @id = 'menu-item-5' ]" )->item( 0 ), $xpath->query( "//*[ @id = 'sub-menu-2' ]" )->item( 0 ) ],

			// Sub-menu of second sub-menu.
			[ $dom, $xpath, $xpath->query( "//*[ @id = 'menu-item-6' ]" )->item( 0 ), $xpath->query( "//*[ @id = 'sub-menu-3' ]" )->item( 0 ) ],
		];
	}

	/**
	 * Test get_closest_submenu().
	 *
	 * @dataProvider get_get_closest_submenu_data
	 * @covers AMP_Core_Theme_Sanitizer::get_closest_submenu()
	 */
	public function test_get_closest_submenu( $dom, $xpath, $element, $expected ) {
		$sanitizer = new AMP_Core_Theme_Sanitizer( $dom );
		$this->set_private_property( $sanitizer, 'xpath', $xpath );
		$actual = $this->call_private_method( $sanitizer, 'get_closest_submenu', [ $element ] );
		$this->assertEquals( $expected, $actual );
	}
}
