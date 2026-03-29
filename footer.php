<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Render a list of manual footer links from fixed ACF Free slots.
 * Slots are stored as: {prefix}_link_{i}_label and {prefix}_link_{i}_url
 * Example: product_link_1_label / product_link_1_url
 */
function ss_footer_render_link_slots($prefix, $count = 8) {
    $items = [];
    for ($i = 1; $i <= $count; $i++) {
        $label = trim((string) ss_footer_get_field("{$prefix}_link_{$i}_label", ''));
        $url   = trim((string) ss_footer_get_field("{$prefix}_link_{$i}_url", ''));
        if ($label === '' || $url === '') {
            continue;
        }
        $items[] = ['label' => $label, 'url' => $url];
    }

    if (empty($items)) {
        return false; // nothing rendered
    }

    echo '<ul class="ss-footer-menu">';
    foreach ($items as $it) {
        echo '<li class="menu-item"><a href="' . esc_url($it['url']) . '">' . esc_html($it['label']) . '</a></li>';
    }
    echo '</ul>';
    return true;
}

/**
 * Footer is fully controlled via ACF (Free) + WP menus.
 * - Settings fields live on the "Footer Settings" page (ID via ss_footer_settings_page_id()).
 * - Menu sub-items are managed via WP Menus (unlimited).
 */

// Global toggle
$footer_enabled = (bool) ss_footer_get_field('footer_enabled', 1);
if (!$footer_enabled) {
    wp_footer();
    echo '</body></html>';
    return;
}

// Section toggles + titles (contact rendered after “Follow Us” for column order)
$sections = [
    'product' => [
        'enabled'   => (bool) ss_footer_get_field('footer_product_enabled', 1),
        'title'     => (string) ss_footer_get_field('footer_product_title', 'Products'),
        'links_key' => 'footer_product_links',
        'menu'      => 'footer_product_type_menu', // fallback only
    ],
    'about' => [
        'enabled'   => (bool) ss_footer_get_field('footer_about_enabled', 1),
        'title'     => (string) ss_footer_get_field('footer_about_title', 'About'),
        'links_key' => 'footer_about_links',
        'menu'      => 'footer_about_menu',
    ],
    'support' => [
        'enabled'   => (bool) ss_footer_get_field('footer_support_enabled', 1),
        'title'     => (string) ss_footer_get_field('footer_support_title', 'Support'),
        'links_key' => 'footer_support_links',
        'menu'      => 'footer_support_menu',
    ],
];

$contact_cfg = [
    'enabled' => (bool) ss_footer_get_field('footer_contact_enabled', 1),
    'title'   => (string) ss_footer_get_field('footer_contact_title', 'Contact'),
    'phone'   => trim((string) ss_footer_get_field('footer_contact_phone', '')),
    'email'   => trim((string) ss_footer_get_field('footer_contact_email', '')),
    'address' => (string) ss_footer_get_field('footer_contact_address', ''),
    'menu'    => 'footer_contact_menu',
];

$brand_cfg = [
    'enabled' => (bool) ss_footer_get_field('footer_brand_enabled', 1),
    'title'   => (string) ss_footer_get_field('footer_brand_title', 'STONE AND SPARKLE'),
];

$social_enabled = (bool) ss_footer_get_field('footer_social_enabled', 1);
$social_title   = (string) ss_footer_get_field('footer_social_title', 'Follow Us');

$copyright_enabled = (bool) ss_footer_get_field('footer_copyright_enabled', 1);
$copyright_text    = trim((string) ss_footer_get_field('footer_copyright_text', '© {year} {site}. All rights reserved.'));
$copyright_text    = str_replace(
    ['{year}', '{site}'],
    [date('Y'), get_bloginfo('name')],
    $copyright_text
);
?>
<footer class="ss-site-footer">
  <div class="ss-container">

    <div class="ss-footer-inner ss-footer-grid">

<?php if ($brand_cfg['enabled']): ?>
  <div class="ss-footer-col ss-footer-section ss-footer-brand">
    <h4><?php echo esc_html($brand_cfg['title']); ?></h4>
    <div class="ss-footer-links">
      <?php ss_footer_render_link_slots('brand', 8); ?>
    </div>
  </div>
<?php endif; ?>

<?php foreach ($sections as $key => $cfg): ?>
  <?php if (!$cfg['enabled']) { continue; } ?>
  <div class="ss-footer-col ss-footer-section ss-footer-<?php echo esc_attr($key); ?>">
    <h4><?php echo esc_html($cfg['title']); ?></h4>
    <div class="ss-footer-links">
      <?php
        $rendered = false;
        // ACF Free manual slots (preferred)
        if ($key === 'product') {
            $rendered = ss_footer_render_link_slots('product', 8);
        } elseif ($key === 'about') {
            $rendered = ss_footer_render_link_slots('about', 8);
        } elseif ($key === 'support') {
            $rendered = ss_footer_render_link_slots('support', 8);
        }

        if (!$rendered) {
            if ($key === 'product') {
                // Fallback: automatically list WooCommerce categories under a parent "Product" link.
                $raw_parent_label = trim((string) $cfg['title']);
                $parent_label     = $raw_parent_label;
                if (preg_match('/^products+$/i', $raw_parent_label) || preg_match('/^product\s*type$/i', $raw_parent_label)) {
                    $parent_label = 'Product';
                }

                $shop_url = '';
                if (function_exists('wc_get_page_id')) {
                    $shop_id = (int) wc_get_page_id('shop');
                    if ($shop_id > 0) {
                        $shop_url = get_permalink($shop_id);
                    }
                }

                $cats = array();
                if (taxonomy_exists('product_cat')) {
                    $terms = get_terms(array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => false,
                        'parent'     => 0,
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                    ));

                    if (!is_wp_error($terms) && is_array($terms)) {
                        $exclude_slugs = array('uncategorized', 'products', 'product', 'productsss');
                        $exclude_names = array('uncategorized', 'products', 'product', 'productsss', 'product type');

                        foreach ($terms as $t) {
                            if (!isset($t->slug) || !isset($t->name)) continue;
                            $slug = strtolower(trim((string) $t->slug));
                            $name = strtolower(trim((string) $t->name));
                            if (in_array($slug, $exclude_slugs, true) || in_array($name, $exclude_names, true)) {
                                continue;
                            }
                            $skip = false;
                            foreach ($exclude_slugs as $ex) {
                                if ($ex !== '' && str_starts_with($slug, $ex . '-')) {
                                    $skip = true;
                                    break;
                                }
                            }
                            if ($skip) continue;
                            $cats[] = $t;
                        }
                    }
                }

                echo '<ul class="ss-footer-menu ss-footer-auto-products">';
                if ($shop_url !== '') {
                    echo '<li class="menu-item ss-footer-parent"><a href="' . esc_url($shop_url) . '">' . esc_html($parent_label) . '</a></li>';
                } else {
                    echo '<li class="menu-item ss-footer-parent"><span>' . esc_html($parent_label) . '</span></li>';
                }

                foreach ($cats as $cat) {
                    $cat_link = get_term_link($cat);
                    if (is_wp_error($cat_link)) continue;
                    echo '<li class="menu-item ss-footer-child"><a href="' . esc_url($cat_link) . '">' . esc_html($cat->name) . '</a></li>';
                }
                echo '</ul>';

            } else {
                // Fallback: render assigned WP menu if present
                if (!empty($cfg['menu']) && has_nav_menu($cfg['menu'])) {
                    wp_nav_menu(array(
                        'theme_location' => $cfg['menu'],
                        'container'      => false,
                        'menu_class'     => 'ss-footer-menu',
                        'fallback_cb'    => '__return_empty_string',
                        'depth'          => 1,
                    ));
                }
            }
          }
      ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($social_enabled): ?>
        <div class="ss-footer-col ss-footer-section ss-footer-social">
          <h4><?php echo esc_html($social_title); ?></h4>

          <div class="ss-footer-social-icons" aria-label="<?php echo esc_attr($social_title); ?>">
            <?php
            for ($i = 1; $i <= 8; $i++) {
                $enabled = (bool) ss_footer_get_field("social_{$i}_enabled", 0);
                if (!$enabled) { continue; }

                $icon    = ss_footer_get_field("social_{$i}_icon", null);
                $link    = trim((string) ss_footer_get_field("social_{$i}_link", ''));
                $tooltip = trim((string) ss_footer_get_field("social_{$i}_tooltip", ''));

                // Require icon + link to render
                $icon_url = '';
                $icon_alt = '';
                if (is_array($icon) && !empty($icon['url'])) {
                    $icon_url = (string) $icon['url'];
                    $icon_alt = !empty($icon['alt']) ? (string) $icon['alt'] : $social_title;
                } elseif (is_string($icon) && $icon !== '') {
                    // If return_format is changed to URL in ACF later
                    $icon_url = $icon;
                    $icon_alt = $social_title;
                }

                if ($icon_url === '' || $link === '') { continue; }
                ?>
                <a class="ss-social-link" href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener"
                   <?php if ($tooltip !== ''): ?>data-tooltip="<?php echo esc_attr($tooltip); ?>"<?php endif; ?>>
                  <img class="ss-social-icon" src="<?php echo esc_url($icon_url); ?>" alt="<?php echo esc_attr($icon_alt); ?>" loading="lazy" />
                </a>
                <?php
            }
            ?>
          </div>
        </div>
      <?php endif; ?>

<?php if ($contact_cfg['enabled']) : ?>
  <div class="ss-footer-col ss-footer-section ss-footer-contact">
    <h4><?php echo esc_html($contact_cfg['title']); ?></h4>
    <?php if ($contact_cfg['phone'] !== '' || $contact_cfg['email'] !== '' || trim((string) $contact_cfg['address']) !== ''): ?>
      <ul class="ss-footer-menu ss-footer-contact-details">
        <?php if ($contact_cfg['phone'] !== ''): ?>
          <li class="menu-item"><a href="<?php echo esc_url('tel:' . preg_replace('/[^0-9\+]/', '', $contact_cfg['phone'])); ?>"><?php echo esc_html($contact_cfg['phone']); ?></a></li>
        <?php endif; ?>
        <?php if ($contact_cfg['email'] !== ''): ?>
          <li class="menu-item"><a href="<?php echo esc_url('mailto:' . sanitize_email($contact_cfg['email'])); ?>"><?php echo esc_html($contact_cfg['email']); ?></a></li>
        <?php endif; ?>
        <?php if (trim((string) $contact_cfg['address']) !== ''): ?>
          <li class="menu-item"><span><?php echo wp_kses_post($contact_cfg['address']); ?></span></li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>
    <div class="ss-footer-links">
      <?php
        $rendered_contact = ss_footer_render_link_slots('contact', 8);
        if (!$rendered_contact && !empty($contact_cfg['menu']) && has_nav_menu($contact_cfg['menu'])) {
            wp_nav_menu(array(
                'theme_location' => $contact_cfg['menu'],
                'container'      => false,
                'menu_class'     => 'ss-footer-menu',
                'fallback_cb'    => '__return_empty_string',
                'depth'          => 1,
            ));
        }
      ?>
    </div>
  </div>
<?php endif; ?>

    </div>

    <?php
    $newsletter_enabled     = (bool) ss_footer_get_field('footer_newsletter_enabled', 1);
    $newsletter_title       = (string) ss_footer_get_field('footer_newsletter_title', 'Newsletter');
    $newsletter_subtitle    = (string) ss_footer_get_field('footer_newsletter_subtitle', 'Subscribe to get special offers, free giveaways, and once-in-a-lifetime deals.');
    $newsletter_placeholder = (string) ss_footer_get_field('footer_newsletter_placeholder', 'Enter your email');
    $newsletter_btn_text    = (string) ss_footer_get_field('footer_newsletter_button_text', 'Join');
    ?>

    <?php if ($newsletter_enabled): ?>
      <div class="ss-footer-newsletter">
        <?php if (trim($newsletter_title) !== ''): ?>
          <h4 class="ss-footer-newsletter__title"><?php echo esc_html($newsletter_title); ?></h4>
        <?php endif; ?>

        <?php if (trim($newsletter_subtitle) !== ''): ?>
          <p class="ss-footer-newsletter__subtitle"><?php echo wp_kses_post($newsletter_subtitle); ?></p>
        <?php endif; ?>

        <form class="ss-footer-newsletter__form" method="post" action="<?php echo esc_url(home_url('/?na=s')); ?>" novalidate>
          <input type="hidden" name="nr" value="footer-newsletter" />
          <div class="ss-footer-newsletter__row">
            <input
              type="email"
              name="ne"
              class="ss-footer-newsletter__input"
              placeholder="<?php echo esc_attr($newsletter_placeholder); ?>"
              autocomplete="email"
              required
            />
            <button type="submit" class="ss-footer-newsletter__btn">
              <?php echo esc_html($newsletter_btn_text); ?>
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($copyright_enabled && $copyright_text !== ''): ?>
      <div class="ss-footer-bottom">
        <div><?php echo esc_html($copyright_text); ?></div>
      </div>
    <?php endif; ?>

  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
