<?php
/**
 * Final class AmpWpPlugin.
 *
 * @package AmpProject\AmpWP
 */

namespace AmpProject\AmpWP;

use AmpProject\AmpWP\Admin;
use AmpProject\AmpWP\BackgroundTask;
use AmpProject\AmpWP\Infrastructure\ServiceBasedPlugin;
use AmpProject\AmpWP\Instrumentation;
use AmpProject\AmpWP\Validation\SavePostValidationEvent;
use AmpProject\AmpWP\Validation\URLValidationCron;
use AmpProject\AmpWP\BackgroundTask\BackgroundTaskDeactivator;

use function is_user_logged_in;

/**
 * The AmpWpPlugin class is the composition root of the plugin.
 *
 * In here we assemble our infrastructure, configure it for the specific use
 * case the plugin is meant to solve and then kick off the services so that they
 * can hook themselves into the WordPress lifecycle.
 *
 * @since 2.0
 * @internal
 */
final class AmpWpPlugin extends ServiceBasedPlugin {
	/*
	 * The "plugin" is only a tool to hook arbitrary code up to the WordPress
	 * execution flow.
	 *
	 * The main structure we use to modularize our code is "services". These are
	 * what makes up the actual plugin, and they provide self-contained pieces
	 * of code that can work independently.
	 */

	// Whether to enable filtering by default or not.
	const ENABLE_FILTERS_DEFAULT = false;

	/**
	 * Prefix to use for all actions and filters.
	 *
	 * This is used to make the filters for the dependency injector unique.
	 *
	 * @var string
	 */
	const HOOK_PREFIX = 'amp_';

	/**
	 * List of services.
	 *
	 * The services array contains a map of <identifier> => <service class name>
	 * associations.
	 *
	 * @var string[]
	 */
	const SERVICES = [
		'admin.analytics_menu'              => Admin\AnalyticsOptionsSubmenu::class,
		'admin.google_fonts'                => Admin\GoogleFonts::class,
		'admin.onboarding_menu'             => Admin\OnboardingWizardSubmenu::class,
		'admin.onboarding_wizard'           => Admin\OnboardingWizardSubmenuPage::class,
		'admin.options_menu'                => Admin\OptionsMenu::class,
		'admin.polyfills'                   => Admin\Polyfills::class,
		'amp_slug_customization_watcher'    => AmpSlugCustomizationWatcher::class,
		'css_transient_cache.ajax_handler'  => Admin\ReenableCssTransientCachingAjaxAction::class,
		'css_transient_cache.monitor'       => BackgroundTask\MonitorCssTransientCaching::class,
		'dev_tools.block_sources'           => DevTools\BlockSources::class,
		'dev_tools.callback_reflection'     => DevTools\CallbackReflection::class,
		'dev_tools.error_page'              => DevTools\ErrorPage::class,
		'dev_tools.file_reflection'         => DevTools\FileReflection::class,
		'dev_tools.likely_culprit_detector' => DevTools\LikelyCulpritDetector::class,
		'dev_tools.user_access'             => DevTools\UserAccess::class,
		'editor.editor_support'             => Editor\EditorSupport::class,
		'extra_theme_and_plugin_headers'    => ExtraThemeAndPluginHeaders::class,
		'mobile_redirection'                => MobileRedirection::class,
		'obsolete_block_attribute_remover'  => ObsoleteBlockAttributeRemover::class,
		'plugin_activation_notice'          => Admin\PluginActivationNotice::class,
		'plugin_registry'                   => PluginRegistry::class,
		'plugin_suppression'                => PluginSuppression::class,
		'reader_theme_loader'               => ReaderThemeLoader::class,
		'rest.options_controller'           => OptionsRESTController::class,
		'server_timing'                     => Instrumentation\ServerTiming::class,
		'site_health_integration'           => Admin\SiteHealth::class,
		'validated_url_stylesheet_gc'       => BackgroundTask\ValidatedUrlStylesheetDataGarbageCollection::class,
		'url_validation_cron'               => URLValidationCron::class,
		'save_post_validation_event'        => SavePostValidationEvent::class,
		'background_task_deactivator'       => BackgroundTaskDeactivator::class,
		'theme_entities_rest_controller'    => Validation\ThemeEntitiesRESTController::class,
	];

	/**
	 * Get the list of services to register.
	 *
	 * The services array contains a map of <identifier> => <service class name>
	 * associations.
	 *
	 * @return array<string> Associative array of identifiers mapped to fully
	 *                       qualified class names.
	 */
	protected function get_service_classes() {
		return self::SERVICES;
	}

	/**
	 * Get the bindings for the dependency injector.
	 *
	 * The bindings array contains a map of <interface> => <implementation>
	 * mappings, both of which should be fully qualified class names (FQCNs).
	 *
	 * The <interface> does not need to be the actual PHP `interface` language
	 * construct, it can be a `class` as well.
	 *
	 * Whenever you ask the injector to "make()" an <interface>, it will resolve
	 * these mappings and return an instance of the final <class> it found.
	 *
	 * @return array<string> Associative array of fully qualified class names.
	 */
	protected function get_bindings() {
		return [];
	}

	/**
	 * Get the argument bindings for the dependency injector.
	 *
	 * The arguments array contains a map of <class> => <associative array of
	 * arguments> mappings.
	 *
	 * The array is provided in the form <argument name> => <argument value>.
	 *
	 * @return array<array> Associative array of arrays mapping argument names
	 *                      to argument values.
	 */
	protected function get_arguments() {
		return [
			Instrumentation\ServerTiming::class => [
				// Wrapped in a closure so it is lazily evaluated. Otherwise,
				// is_user_logged_in() breaks because it's used too early.
				'verbose' => static function () {
					return is_user_logged_in()
						&& current_user_can( 'manage_options' )
						&& isset( $_GET[ QueryVar::VERBOSE_SERVER_TIMING ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						&& filter_var(
							$_GET[ QueryVar::VERBOSE_SERVER_TIMING ], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							FILTER_VALIDATE_BOOLEAN
						);
				},
			],
		];
	}

	/**
	 * Get the shared instances for the dependency injector.
	 *
	 * The shared instances array contains a list of FQCNs that are meant to be
	 * reused. For multiple "make()" requests, the injector will return the same
	 * instance reference for these, instead of always returning a new one.
	 *
	 * This effectively turns these FQCNs into a "singleton", without incurring
	 * all the drawbacks of the Singleton design anti-pattern.
	 *
	 * @return array<string> Array of fully qualified class names.
	 */
	protected function get_shared_instances() {
		return [
			PluginRegistry::class,
			Instrumentation\StopWatch::class,
			DevTools\CallbackReflection::class,
			DevTools\FileReflection::class,
			ReaderThemeLoader::class,
			BackgroundTask\BackgroundTaskDeactivator::class,
		];
	}

	/**
	 * Get the delegations for the dependency injector.
	 *
	 * The delegations array contains a map of <class> => <callable>
	 * mappings.
	 *
	 * The <callable> is basically a factory to provide custom instantiation
	 * logic for the given <class>.
	 *
	 * @return array<callable> Associative array of callables.
	 */
	protected function get_delegations() {
		return [];
	}
}
