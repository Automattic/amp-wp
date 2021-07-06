<?php
/**
 * Class PrettyPrinter.
 *
 * This class is based on code from the WordPress/phpdoc-parser project by
 * Ryan McCue, Paul Gibbs, Andrey "Rarst" Savchenko and Contributors,
 * licensed under the GPLv2 or later.
 *
 * @link https://github.com/WordPress/phpdoc-parser
 *
 * @package AmpProject\AmpWP
 */

namespace AmpProject\AmpWP\Documentation\Parser;

use Parsedown;
use phpDocumentor\Reflection\BaseReflector;
use phpDocumentor\Reflection\ClassReflector\MethodReflector;
use phpDocumentor\Reflection\ClassReflector\PropertyReflector;
use phpDocumentor\Reflection\FunctionReflector;
use phpDocumentor\Reflection\FunctionReflector\ArgumentReflector;
use phpDocumentor\Reflection\ReflectionAbstract;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;
use WP_Error;

/**
 * Parser class that goes over files and extracts the key information we need
 * for the documentation.
 */
final class Parser {

	/**
	 * Get the files to parse.
	 *
	 * @param string $directory     Directory to look in for files.
	 * @param array  $included_dirs List of regex patterns to include.
	 *
	 * @return array|WP_Error Array of files, or WP_Error if a problem occurred.
	 */
	public function get_files( $directory, $included_dirs ) {
		$directories          = new RecursiveDirectoryIterator( $directory );
		$filtered_directories = new DirectoryFilter( $directories, $included_dirs );
		$iterable_files       = new RecursiveIteratorIterator( $filtered_directories );

		$files = [];

		try {
			foreach ( $iterable_files as $file ) {
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}

				$files[] = $file->getPathname();
			}
		} catch ( UnexpectedValueException $exc ) {
			return new WP_Error(
				'unexpected_value_exception',
				sprintf( 'Directory [%s] contained a directory we can not recurse into', $directory )
			);
		}

		return $files;
	}

	/**
	 * Parse the files.
	 *
	 * @param array  $files List of files to parse.
	 * @param string $root  Root folder that the files are in.
	 *
	 * @return array Associative array of parsed data.
	 */
	public function parse_files( $files, $root ) {
		$output = [];

		foreach ( $files as $filename ) {
			$file = new FileReflector( $filename );

			$path = ltrim( substr( $filename, strlen( $root ) ), DIRECTORY_SEPARATOR );
			$file->setFilename( $path );

			$file->process();

			// TODO: Add proper exporter.
			$out = [
				'file' => $this->export_docblock( $file ),
				'path' => str_replace( DIRECTORY_SEPARATOR, '/', $file->getFilename() ),
			];

			if ( ! empty( $file->uses ) ) {
				$out['uses'] = $this->export_uses( $file->uses );
			}

			foreach ( $file->getIncludes() as $include ) {
				$out['includes'][] = [
					'name' => $include->getName(),
					'line' => $include->getLineNumber(),
					'type' => $include->getType(),
				];
			}

			foreach ( $file->getConstants() as $constant ) {
				$out['constants'][] = [
					'name'  => $constant->getShortName(),
					'line'  => $constant->getLineNumber(),
					'value' => $constant->getValue(),
				];
			}

			if ( ! empty( $file->uses['hooks'] ) ) {
				$out['hooks'] = $this->export_hooks( $file->uses['hooks'] );
			}

			foreach ( $file->getFunctions() as $function ) {
				$func = [
					'name'      => $function->getShortName(),
					'namespace' => $function->getNamespace(),
					'aliases'   => $function->getNamespaceAliases(),
					'line'      => $function->getLineNumber(),
					'end_line'  => $function->getNode()
											->getAttribute( 'endLine' ),
					'arguments' => $this->export_arguments( $function->getArguments() ),
					'doc'       => $this->export_docblock( $function ),
					'hooks'     => [],
				];

				if ( ! empty( $function->uses ) ) {
					$func['uses'] = $this->export_uses( $function->uses );

					if ( ! empty( $function->uses['hooks'] ) ) {
						$func['hooks'] = $this->export_hooks( $function->uses['hooks'] );
					}
				}

				$out['functions'][] = $func;
			}

			foreach ( $file->getClasses() as $class ) {
				$class_data = [
					'name'       => $class->getShortName(),
					'namespace'  => $class->getNamespace(),
					'line'       => $class->getLineNumber(),
					'end_line'   => $class->getNode()->getAttribute( 'endLine' ),
					'final'      => $class->isFinal(),
					'abstract'   => $class->isAbstract(),
					'extends'    => $class->getParentClass(),
					'implements' => $class->getInterfaces(),
					'properties' => $this->export_properties( $class->getProperties() ),
					'methods'    => $this->export_methods( $class->getMethods() ),
					'doc'        => $this->export_docblock( $class ),
				];

				$out['classes'][] = $class_data;
			}

			$output[] = $out;
		}

		return $output;
	}

	/**
	 * Fixes newline handling in parsed text.
	 *
	 * DocBlock lines, particularly for descriptions, generally adhere to a
	 * given character width. For sentences and paragraphs that exceed that
	 * width, what is intended as a manual soft wrap (via line break) is used
	 * to ensure on-screen/in-file legibility of that text. These line breaks
	 * are retained by phpDocumentor. However, consumers of this parsed data
	 * may believe the line breaks to be intentional and may display the text
	 * as such.
	 *
	 * This function fixes text by merging consecutive lines of text into a
	 * single line. A special exception is made for text appearing in `<code>`
	 * and `<pre>` tags or wrapped in triple backticks (```), as newlines
	 * appearing in those are always intentional.
	 *
	 * @param string $text Text for which to fix the newlines.
	 *
	 * @return string Fixed text.
	 */
	private function fix_newlines( $text ) {
		// Non-naturally occurring string to use as temporary replacement.
		$replacement_string = '{{{{{}}}}}';

		// Replace newline characters within 'code' and 'pre' tags with replacement string.
		$text = preg_replace_callback(
			'/(?<=<pre><code>)(.+)(?=<\/code><\/pre>)/s',
			static function ( $matches ) use ( $replacement_string ) {
				return preg_replace( '/[\n\r]/', $replacement_string, $matches[1] );
			},
			$text
		);

		// Replace newline characters within triple backticks with replacement string.
		$text = preg_replace_callback(
			'/(?<=```)(.+)(?=```)/s',
			static function ( $matches ) use ( $replacement_string ) {
				return preg_replace( '/[\n\r]/', $replacement_string, $matches[1] );
			},
			$text
		);

		// Merge consecutive non-blank lines together by replacing the newlines with a space.
		$text = preg_replace(
			"/[\n\r](?!\s*[\n\r])/m",
			' ',
			$text
		);

		// Restore newline characters into code blocks.
		$text = str_replace( $replacement_string, "\n", $text );

		return $text;
	}

	/**
	 * Export a docblock for a provided element.
	 *
	 * @param BaseReflector|ReflectionAbstract $element Element to extract the
	 *                                                  doc-block for.
	 *
	 * @return array Associative array of doc-block data.
	 */
	private function export_docblock( $element ) {
		$docblock = $element->getDocBlock();
		if ( ! $docblock ) {
			return [
				'description'      => '',
				'long_description' => '',
				'tags'             => [],
			];
		}

		$output = [
			'description'      => preg_replace(
				'/[\n\r]+/',
				' ',
				$docblock->getShortDescription()
			),
			'long_description' => $this->fix_newlines(
				$docblock->getLongDescription()->getFormattedContents()
			),
			'tags'             => [],
		];

		foreach ( $docblock->getTags() as $tag ) {
			$tag_data = [
				'name'    => $tag->getName(),
				'content' => preg_replace(
					'/[\n\r]+/',
					' ',
					$this->format_description( $tag->getDescription() )
				),
			];
			if ( method_exists( $tag, 'getTypes' ) ) {
				$tag_data['types'] = $tag->getTypes();
			}
			if ( method_exists( $tag, 'getLink' ) ) {
				$tag_data['link'] = $tag->getLink();
			}
			if ( method_exists( $tag, 'getVariableName' ) ) {
				$tag_data['variable'] = $tag->getVariableName();
			}
			if ( method_exists( $tag, 'getReference' ) ) {
				$tag_data['refers'] = $tag->getReference();
			}
			if ( method_exists( $tag, 'getVersion' ) ) {
				// Version string.
				$version = $tag->getVersion();
				if ( ! empty( $version ) ) {
					$tag_data['content'] = $version;
				}
				// Description string.
				if ( method_exists( $tag, 'getDescription' ) ) {
					$description = preg_replace(
						'/[\n\r]+/',
						' ',
						$this->format_description( $tag->getDescription() )
					);
					if ( ! empty( $description ) ) {
						$tag_data['description'] = $description;
					}
				}
			}
			$output['tags'][] = $tag_data;
		}

		return $output;
	}

	/**
	 * Export the hooks.
	 *
	 * @param HookReflector[] $hooks Hooks to export.
	 *
	 * @return array Associative array of hooks data.
	 */
	private function export_hooks( $hooks ) {
		$out = [];

		foreach ( $hooks as $hook ) {
			$out[] = [
				'name'      => $hook->getName(),
				'line'      => $hook->getLineNumber(),
				'end_line'  => $hook->getNode()->getAttribute( 'endLine' ),
				'type'      => $hook->getType(),
				'arguments' => $hook->getArgs(),
				'doc'       => $this->export_docblock( $hook ),
			];
		}

		return $out;
	}

	/**
	 * Export the arguments.
	 *
	 * @param ArgumentReflector[] $arguments Arguments to export.
	 *
	 * @return array Associative array of argument data.
	 */
	private function export_arguments( $arguments ) {
		$output = [];

		foreach ( $arguments as $argument ) {
			$output[] = [
				'name'    => $argument->getName(),
				'default' => $argument->getDefault(),
				'type'    => $argument->getType(),
			];
		}

		return $output;
	}

	/**
	 * Export the properties.
	 *
	 * @param PropertyReflector[] $properties Properties to export.
	 *
	 * @return array Associative array of property data.
	 */
	private function export_properties( $properties ) {
		$out = [];

		foreach ( $properties as $property ) {
			$out[] = [
				'name'       => $property->getName(),
				'line'       => $property->getLineNumber(),
				'end_line'   => $property->getNode()->getAttribute( 'endLine' ),
				'default'    => $property->getDefault(),
				'static'     => $property->isStatic(),
				'visibility' => $property->getVisibility(),
				'doc'        => $this->export_docblock( $property ),
			];
		}

		return $out;
	}

	/**
	 * Export the methods.
	 *
	 * @param MethodReflector[] $methods Methods to export.
	 *
	 * @return array Associative array of method data.
	 */
	private function export_methods( $methods ) {
		$output = [];

		foreach ( $methods as $method ) {

			$method_data = [
				'name'       => $method->getShortName(),
				'namespace'  => $method->getNamespace(),
				'aliases'    => $method->getNamespaceAliases(),
				'line'       => $method->getLineNumber(),
				'end_line'   => $method->getNode()->getAttribute( 'endLine' ),
				'final'      => $method->isFinal(),
				'abstract'   => $method->isAbstract(),
				'static'     => $method->isStatic(),
				'visibility' => $method->getVisibility(),
				'arguments'  => $this->export_arguments( $method->getArguments() ),
				'doc'        => $this->export_docblock( $method ),
			];

			if ( ! empty( $method->uses ) ) {
				$method_data['uses'] = $this->export_uses( $method->uses );

				if ( ! empty( $method->uses['hooks'] ) ) {
					$method_data['hooks'] = $this->export_hooks( $method->uses['hooks'] );
				}
			}

			$output[] = $method_data;
		}

		return $output;
	}

	/**
	 * Export the list of elements used by a file or structure.
	 *
	 * @param array $uses Array of usage data.
	 *
	 * @return array Associative array of usage data.
	 */
	private function export_uses( $uses ) {
		$out = [];

		// Ignore hooks here, they are exported separately.
		unset( $uses['hooks'] );

		foreach ( $uses as $type => $used_elements ) {

			/** @var MethodReflector|FunctionReflector $element */
			foreach ( $used_elements as $element ) {

				$name = $element->getName();

				switch ( $type ) {
					case 'methods':
						$out[ $type ][] = [
							'name'     => $name[1],
							'class'    => $name[0],
							'static'   => $element->isStatic(),
							'line'     => $element->getLineNumber(),
							'end_line' => $element->getNode()->getAttribute( 'endLine' ),
						];
						break;

					default:
					case 'functions':
						$out[ $type ][] = [
							'name'     => $name,
							'line'     => $element->getLineNumber(),
							'end_line' => $element->getNode()->getAttribute( 'endLine' ),
						];

						if (
							'_deprecated_file' === $name
							||
							'_deprecated_function' === $name
							||
							'_deprecated_argument' === $name
							||
							'_deprecated_hook' === $name
						) {
							$arguments = $element->getNode()->args;

							$out[ $type ][0]['deprecation_version'] = $arguments[1]->value->value;
						}

						break;
				}
			}
		}

		return $out;
	}

	/**
	 * Format the given description with Markdown.
	 *
	 * @param string $description Description.
	 * @return string Description as Markdown if the Parsedown class exists,
	 *                otherwise return the given description text.
	 */
	private function format_description( $description ) {
		if ( class_exists( 'Parsedown' ) ) {
			$parsedown   = Parsedown::instance();
			$description = $parsedown->line( $description );
		}
		return $description;
	}
}
