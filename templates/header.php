<?php if ( get_theme_mod( 'amp_navbar_background_image', '' ) != '' ) {
		$header_background = 'header-background-image';
	} else {
		$header_background = '';
	}
 ?>
<header id="#top" class="amp-wp-header <?php echo $header_background; ?>" itemscope="itemscope" itemtype="http://schema.org/WPHeader" role="banner">
	<div>
		<a href="<?php echo esc_url( $this->get( 'home_url' ) ); ?>">
			<?php $site_icon_url = $this->get( 'site_icon_url' ); ?>
			<?php if ( $site_icon_url ) : ?>
				<amp-img src="<?php echo esc_url( $site_icon_url ); ?>" width="32" height="32" class="amp-wp-site-icon"></amp-img>
			<?php endif; ?>
			<?php echo esc_html( $this->get( 'blog_name' ) ); ?>
		</a>
	</div>
</header>