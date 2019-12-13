<?php
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

// stub classes for AMP_Base_Sanitizer, since it is an abstract class
class AMP_Test_Stub_Sanitizer extends AMP_Base_Sanitizer {
	public function sanitize() {
		return $this->dom;
	}
}

class AMP_Test_World_Sanitizer extends AMP_Base_Sanitizer {
	public function sanitize() {
		$node = $this->dom->createElement( 'em' );
		$text = $this->dom->createTextNode( 'World' );
		$node->appendChild( $text );
		$this->dom->getElementsByTagName( 'body' )->item( 0 )->appendChild( $node );
	}

	public function get_scripts() {
		return [ 'scripts' ];
	}

	public function get_styles() {
		return [ 'styles' ];
	}
}

// Stub class for WP_Admin_Bar.
class AMP_Test_WP_Admin_Bar {
	/**
	 * The nodes in the admin bar.
	 *
	 * @var array
	 */
	public $nodes = [];

	/**
	 * Adds a node to display in the admin bar.
	 *
	 * @param array $node The node to add.
	 */
	public function add_node( $node ) {
		array_push( $this->nodes, $node );
	}
}
