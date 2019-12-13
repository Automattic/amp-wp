<?php
/**
 * Class AMP_Autoloader
 *
 * @package AMP
 */

/**
 * Autoload the classes used by the AMP plugin.
 *
 * Class AMP_Autoloader
 */
class AMP_Autoloader {

	/**
	 * Map of Classname to relative filepath sans extension.
	 *
	 * @note We omitted the leading slash and the .php extension from each
	 *       relative filepath because they are redundant and to include
	 *       them would take up unnecessary bytes of memory at runtime.
	 *
	 * @example Format (note no leading / and no .php extension):
	 *
	 *  array(
	 *      'Class_Name1' =>  'subdir-of-includes/filename1',
	 *      'Class_Name2' =>  '2nd-subdir-of-includes/filename2',
	 *  );
	 *
	 * @var string[]
	 */
	private static $classmap = [
		'AMP_Editor_Blocks'                  => 'includes/admin/class-amp-editor-blocks',
		'AMP_Theme_Support'                  => 'includes/class-amp-theme-support',
		'AMP_Service_Worker'                 => 'includes/class-amp-service-worker',
		'AMP_HTTP'                           => 'includes/class-amp-http',
		'AMP_Comment_Walker'                 => 'includes/class-amp-comment-walker',
		'AMP_Template_Customizer'            => 'includes/admin/class-amp-customizer',
		'AMP_Post_Meta_Box'                  => 'includes/admin/class-amp-post-meta-box',
		'AMP_Admin_Pointer'                  => 'includes/admin/class-amp-admin-pointer',
		'AMP_Admin_Pointers'                 => 'includes/admin/class-amp-admin-pointers',
		'AMP_Post_Type_Support'              => 'includes/class-amp-post-type-support',
		'AMP_Base_Embed_Handler'             => 'includes/embeds/class-amp-base-embed-handler',
		'AMP_Crowdsignal_Embed_Handler'      => 'includes/embeds/class-amp-crowdsignal-embed-handler',
		'AMP_DailyMotion_Embed_Handler'      => 'includes/embeds/class-amp-dailymotion-embed',
		'AMP_Facebook_Embed_Handler'         => 'includes/embeds/class-amp-facebook-embed',
		'AMP_Gallery_Embed_Handler'          => 'includes/embeds/class-amp-gallery-embed',
		'AMP_Gfycat_Embed_Handler'           => 'includes/embeds/class-amp-gfycat-embed-handler',
		'AMP_Hulu_Embed_Handler'             => 'includes/embeds/class-amp-hulu-embed-handler',
		'AMP_Imgur_Embed_Handler'            => 'includes/embeds/class-amp-imgur-embed-handler',
		'AMP_Core_Block_Handler'             => 'includes/embeds/class-amp-core-block-handler',
		'AMP_Instagram_Embed_Handler'        => 'includes/embeds/class-amp-instagram-embed',
		'AMP_Issuu_Embed_Handler'            => 'includes/embeds/class-amp-issuu-embed-handler',
		'AMP_Meetup_Embed_Handler'           => 'includes/embeds/class-amp-meetup-embed-handler',
		'AMP_Pinterest_Embed_Handler'        => 'includes/embeds/class-amp-pinterest-embed',
		'AMP_Playlist_Embed_Handler'         => 'includes/embeds/class-amp-playlist-embed-handler',
		'AMP_Reddit_Embed_Handler'           => 'includes/embeds/class-amp-reddit-embed-handler',
		'AMP_SoundCloud_Embed_Handler'       => 'includes/embeds/class-amp-soundcloud-embed',
		'AMP_Tumblr_Embed_Handler'           => 'includes/embeds/class-amp-tumblr-embed-handler',
		'AMP_Twitter_Embed_Handler'          => 'includes/embeds/class-amp-twitter-embed-handler',
		'AMP_Vimeo_Embed_Handler'            => 'includes/embeds/class-amp-vimeo-embed',
		'AMP_Vine_Embed_Handler'             => 'includes/embeds/class-amp-vine-embed',
		'AMP_WordPress_TV_Embed_Handler'     => 'includes/embeds/class-amp-wordpress-tv-embed-handler',
		'AMP_YouTube_Embed_Handler'          => 'includes/embeds/class-amp-youtube-embed-handler',
		'AMP_Scribd_Embed_Handler'           => 'includes/embeds/class-amp-scribd-embed-handler',
		'AMP_Analytics_Options_Submenu'      => 'includes/options/class-amp-analytics-options-submenu',
		'AMP_Options_Menu'                   => 'includes/options/class-amp-options-menu',
		'AMP_Options_Manager'                => 'includes/options/class-amp-options-manager',
		'AMP_Analytics_Options_Submenu_Page' => 'includes/options/views/class-amp-analytics-options-submenu-page',
		'AMP_Options_Menu_Page'              => 'includes/options/views/class-amp-options-menu-page',
		'AMP_Rule_Spec'                      => 'includes/sanitizers/class-amp-rule-spec',
		'AMP_Allowed_Tags_Generated'         => 'includes/sanitizers/class-amp-allowed-tags-generated',
		'AMP_Audio_Sanitizer'                => 'includes/sanitizers/class-amp-audio-sanitizer',
		'AMP_Base_Sanitizer'                 => 'includes/sanitizers/class-amp-base-sanitizer',
		'AMP_Blacklist_Sanitizer'            => 'includes/sanitizers/class-amp-blacklist-sanitizer',
		'AMP_Block_Sanitizer'                => 'includes/sanitizers/class-amp-block-sanitizer',
		'AMP_Dev_Mode_Sanitizer'             => 'includes/sanitizers/class-amp-dev-mode-sanitizer',
		'AMP_Gallery_Block_Sanitizer'        => 'includes/sanitizers/class-amp-gallery-block-sanitizer',
		'AMP_Iframe_Sanitizer'               => 'includes/sanitizers/class-amp-iframe-sanitizer',
		'AMP_Img_Sanitizer'                  => 'includes/sanitizers/class-amp-img-sanitizer',
		'AMP_Link_Sanitizer'                 => 'includes/sanitizers/class-amp-link-sanitizer',
		'AMP_Nav_Menu_Toggle_Sanitizer'      => 'includes/sanitizers/class-amp-nav-menu-toggle-sanitizer',
		'AMP_Nav_Menu_Dropdown_Sanitizer'    => 'includes/sanitizers/class-amp-nav-menu-dropdown-sanitizer',
		'AMP_Comments_Sanitizer'             => 'includes/sanitizers/class-amp-comments-sanitizer',
		'AMP_Form_Sanitizer'                 => 'includes/sanitizers/class-amp-form-sanitizer',
		'AMP_O2_Player_Sanitizer'            => 'includes/sanitizers/class-amp-o2-player-sanitizer',
		'AMP_Playbuzz_Sanitizer'             => 'includes/sanitizers/class-amp-playbuzz-sanitizer',
		'AMP_Style_Sanitizer'                => 'includes/sanitizers/class-amp-style-sanitizer',
		'AMP_Script_Sanitizer'               => 'includes/sanitizers/class-amp-script-sanitizer',
		'AMP_Embed_Sanitizer'                => 'includes/sanitizers/class-amp-embed-sanitizer',
		'AMP_Tag_And_Attribute_Sanitizer'    => 'includes/sanitizers/class-amp-tag-and-attribute-sanitizer',
		'AMP_Video_Sanitizer'                => 'includes/sanitizers/class-amp-video-sanitizer',
		'AMP_Core_Theme_Sanitizer'           => 'includes/sanitizers/class-amp-core-theme-sanitizer',
		'AMP_Noscript_Fallback'              => 'includes/sanitizers/trait-amp-noscript-fallback',
		'AMP_Customizer_Design_Settings'     => 'includes/settings/class-amp-customizer-design-settings',
		'AMP_Customizer_Settings'            => 'includes/settings/class-amp-customizer-settings',
		'AMP_Content'                        => 'includes/templates/class-amp-content',
		'AMP_Content_Sanitizer'              => 'includes/templates/class-amp-content-sanitizer',
		'AMP_Post_Template'                  => 'includes/templates/class-amp-post-template',
		'AMP_DOM_Utils'                      => 'includes/utils/class-amp-dom-utils',
		'AMP_HTML_Utils'                     => 'includes/utils/class-amp-html-utils',
		'AMP_Image_Dimension_Extractor'      => 'includes/utils/class-amp-image-dimension-extractor',
		'AMP_Validation_Manager'             => 'includes/validation/class-amp-validation-manager',
		'AMP_Validation_Callback_Wrapper'    => 'includes/validation/class-amp-validation-callback-wrapper',
		'AMP_Validated_URL_Post_Type'        => 'includes/validation/class-amp-validated-url-post-type',
		'AMP_Validation_Error_Taxonomy'      => 'includes/validation/class-amp-validation-error-taxonomy',
		'AMP_Debug'                          => 'includes/validation/class-amp-debug',
		'AMP_CLI_Namespace'                  => 'includes/cli/class-amp-cli-namespace',
		'AMP_CLI_Validation_Command'         => 'includes/cli/class-amp-cli-validation-command',
		'AMP_String_Utils'                   => 'includes/utils/class-amp-string-utils',
		'AMP_Widget_Archives'                => 'includes/widgets/class-amp-widget-archives',
		'AMP_Widget_Categories'              => 'includes/widgets/class-amp-widget-categories',
		'AMP_Widget_Text'                    => 'includes/widgets/class-amp-widget-text',
		'AMP_Story_Post_Type'                => 'includes/class-amp-story-post-type',
		'AMP_Story_Media'                    => 'includes/class-amp-story-media',
		'AMP_Story_Templates'                => 'includes/admin/class-amp-story-templates',
		'AMP_Story_Sanitizer'                => 'includes/sanitizers/class-amp-story-sanitizer',
		'AMP_Story_Export_Sanitizer'         => 'includes/sanitizers/class-amp-story-export-sanitizer',
		'AMP_Test_Stub_Sanitizer'            => 'tests/php/stubs',
		'AMP_Test_World_Sanitizer'           => 'tests/php/stubs',
		'AMP_Test_HandleValidation'          => 'tests/php/validation/trait-handle-validation',
	];

	/**
	 * Is registered.
	 *
	 * @var bool
	 */
	public static $is_registered = false;

	/**
	 * Perform the autoload on demand when requested by PHP runtime.
	 *
	 * Design Goal: Execute as few lines of code as possible each call.
	 *
	 * @since 0.6
	 *
	 * @param string $class_name Class name.
	 */
	protected static function autoload( $class_name ) {
		if ( ! isset( self::$classmap[ $class_name ] ) ) {
			return;
		}
		$filepath = self::$classmap[ $class_name ];
		require AMP__DIR__ . "/{$filepath}.php";
	}

	/**
	 * Registers this autoloader to PHP.
	 *
	 * @since 0.6
	 *
	 * Called at the end of this file; calling a second time has no effect.
	 */
	public static function register() {
		if ( file_exists( AMP__DIR__ . '/vendor/autoload.php' ) ) {
			require_once AMP__DIR__ . '/vendor/autoload.php';
		}

		if ( ! self::$is_registered ) {
			spl_autoload_register( [ __CLASS__, 'autoload' ] );
			self::$is_registered = true;
		}
	}

	/**
	 * Allows an extensions plugin to register a class and its file for autoloading
	 *
	 * @since 0.6
	 *
	 * @param string $class_name Full classname (include namespace if applicable).
	 * @param string $filepath   Absolute filepath to class file, including .php extension.
	 */
	public static function register_autoload_class( $class_name, $filepath ) {
		self::$classmap[ $class_name ] = '!' . $filepath;
	}
}
