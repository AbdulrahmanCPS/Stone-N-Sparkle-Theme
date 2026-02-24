<?php
if (!defined('ABSPATH')) { exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="ss-site-header">
  <div class="ss-header-inner">

    <button class="ss-burger" type="button" aria-label="<?php esc_attr_e('Menu', 'stone-sparkle'); ?>" aria-controls="ssPrimaryNav" aria-expanded="false">
      <span class="ss-burger__line" aria-hidden="true"></span>
      <span class="ss-burger__line" aria-hidden="true"></span>
      <span class="ss-burger__line" aria-hidden="true"></span>
    </button>

    <div id="ssPrimaryNav" class="ss-drawer" aria-hidden="true">
      <div class="ss-drawer__overlay" data-ss-drawer-close></div>

      <div class="ss-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Menu', 'stone-sparkle'); ?>">
        <button class="ss-drawer__close" type="button" aria-label="<?php esc_attr_e('Close menu', 'stone-sparkle'); ?>" data-ss-drawer-close>
          <span aria-hidden="true">&times;</span>
        </button>

        <nav class="ss-drawer__nav" aria-label="<?php esc_attr_e('Primary', 'stone-sparkle'); ?>">
          <?php
          if ( has_nav_menu('primary') ) {
            wp_nav_menu([
              'theme_location' => 'primary',
              'container'      => false,
              'menu_class'     => 'ss-drawer__menu',
              'fallback_cb'    => false,
              'depth'          => 2,
            ]);
          } else {
            echo '<ul class="ss-drawer__menu">';
            echo '<li><a href="' . esc_url(home_url('/shop/')) . '">' . esc_html__('Shop', 'stone-sparkle') . '</a></li>';
            echo '<li><a href="' . esc_url(home_url('/contact/')) . '">' . esc_html__('Contact', 'stone-sparkle') . '</a></li>';
            echo '</ul>';
          }
          ?>
        </nav>
      </div>
    </div>

    <a class="ss-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
      <?php
        // Prefer the Customizer "Site Logo" if set; fallback to the bundled logo.
        if ( has_custom_logo() ) {
          $custom_logo_id = get_theme_mod('custom_logo');
          echo wp_get_attachment_image(
            $custom_logo_id,
            'full',
            false,
            array(
              'class' => 'ss-brand__img',
              'alt'   => get_bloginfo('name'),
            )
          );
        } else {
          printf(
            '<img class="ss-brand__img" src="%s" alt="%s">',
            esc_url( get_template_directory_uri() . '/assets/images/logo.png' ),
            esc_attr( get_bloginfo('name') )
          );
        }
      ?>
    </a>

    <div class="ss-header-actions">
      <?php
        $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
      ?>
      <?php
        if (function_exists('ss_render_cart_link')) {
          echo ss_render_cart_link();
        }
      ?>
      <a class="ss-icon-btn" href="<?php echo esc_url($account_url); ?>" aria-label="<?php esc_attr_e('Account', 'stone-sparkle'); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 12a4.2 4.2 0 1 0 0-8.4A4.2 4.2 0 0 0 12 12Z" stroke="currentColor" stroke-width="1.6"/>
          <path d="M4.5 20.4c1.7-4 13.3-4 15 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
      </a>
    </div>

  </div>
</header>
