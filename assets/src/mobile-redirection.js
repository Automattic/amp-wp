( function( { ampUrl, isCustomizePreview, isAmpDevMode, noampQueryVarName, noampQueryVarValue, disabledStorageKey, mobileUserAgents, regexRegex } ) {
	if ( typeof sessionStorage === 'undefined' ) {
		return;
	}

	// Yes, this is a regular expression to match regular expressions. So meta.
	const regExpRegExp = new RegExp( regexRegex );

	// A user agent may be expressed as a RegExp string serialization in the the form of `/pattern/[i]*`. If a user
	// agent string does not match this pattern, then the string will be used as a simple string needle for the haystack.
	const isMobile = mobileUserAgents.some( ( pattern ) => {
		const matches = pattern.match( regExpRegExp ); // A regex for a regex. So meta.
		if ( matches ) {
			const re = new RegExp( matches[ 1 ], matches[ 2 ] );
			if ( re.test( navigator.userAgent ) ) {
				return true;
			}
		}
		return navigator.userAgent.includes( pattern );
	} );

	// If not mobile, there's nothing left to do.
	if ( ! isMobile ) {
		return;
	}

	global.addEventListener( 'DOMContentLoaded', () => {
		// Show the mobile version switcher link once the DOM has loaded.
		const siteVersionSwitcher = document.getElementById( 'amp-mobile-version-switcher' );
		if ( ! siteVersionSwitcher ) {
			return;
		}

		// Show the link to return to the mobile version of the site since it is hidden by default when client-side
		// redirection is enabled, since JS is used to determine whether it is a mobile browser.
		siteVersionSwitcher.hidden = false;

		// Re-enable mobile redirection when navigating back to the mobile version of the site.
		const link = siteVersionSwitcher.querySelector( 'a[href]' );
		if ( link ) {
			link.addEventListener( 'click', () => {
				sessionStorage.removeItem( disabledStorageKey );
			} );
		}
	} );

	// Short-circuit if mobile redirection is disabled. Redirection is disabled if the user opted-out by clicking the
	// link to exit the mobile version, if they are in paired browsing (since non-AMP and AMP are forced in the respective
	// iframes), and when in the customizer (since the Customizer is opened with a given URL and that should be the URL
	// which is then used for Customization).
	const isPairedBrowsing = isAmpDevMode && [ 'paired-browsing-non-amp', 'paired-browsing-amp' ].includes( window.name );
	if ( sessionStorage.getItem( disabledStorageKey ) || isCustomizePreview || isPairedBrowsing ) {
		return;
	}

	const locationUrlObject = new URL( location.href );
	const amphtmlUrlObject = new URL( ampUrl );

	// Persist the URL fragment when redirecting to the AMP version. This is needed because the server-generated amphtml
	// link has no awareness of the client-side URL target.
	amphtmlUrlObject.hash = locationUrlObject.hash;

	if ( locationUrlObject.searchParams.has( noampQueryVarName ) && noampQueryVarValue === locationUrlObject.searchParams.get( noampQueryVarName ) ) {
		// If the noamp query param is present, remember that redirection should be disabled.
		sessionStorage.setItem( disabledStorageKey, '1' );
	} else if ( amphtmlUrlObject.href !== locationUrlObject.href ) {
		// Otherwise, since JS is running then we know it's not an AMP page and we need to redirect to the AMP version.
		// Nevertheless, the `url.href !== location.href` condition was added for the edge case where a caching plugin
		// is erroneously serving a cached non-AMP page at the AMP URL, so the condition prevents an infinite redirect
		// from ensuing. See <https://github.com/ampproject/amp-wp/issues/5767>.
		window.stop(); // Stop loading the page! This should cancel all loading resources.

		// Pass along referrer so that amp-analytics can report the correct referrer.
		if ( document.referrer ) {
			amphtmlUrlObject.searchParams.set( 'amp_referrer', document.referrer );
		}

		// Replace the current page with the AMP version.
		location.replace( amphtmlUrlObject.href );
	}
}(
	// Note: The argument here is replaced with a JSON object literal in PHP by \AmpProject\AmpWP\MobileRedirection::add_mobile_redirect_script().
	AMP_MOBILE_REDIRECTION,
) );
