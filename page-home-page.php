<?php
/**
 * Template for the page with slug: home-page
 * WordPress will automatically use this file for /home-page/ (page-{slug}.php).
 */
if (!defined('ABSPATH')) { exit; }

get_header();
?>

<main class="ss-main">
  <?php
  while (have_posts()) : the_post();
    $content = trim(get_the_content());

    // If the page has blocks/content, render it.
    if (!empty($content)) {
      echo '<div class="ss-container entry-content">';
      the_content();
      echo '</div>';
    } else {

      $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
      if (!$shop) {
        $shop = home_url('/shop/');
      }

      // Lookbook stack: dynamic sections from meta box (with migration from legacy ACF if needed).
      $lookbook = function_exists('ss_get_home_sections') ? ss_get_home_sections(get_the_ID()) : [];

      // Fallback when no sections: show 7 placeholders so layout does not break.
      if (empty($lookbook)) {
        for ($i = 1; $i <= 7; $i++) {
          $lookbook[] = [
            'img' => '',
            'cta' => __('Shop', 'stone-sparkle'),
            'link' => $shop,
            'target' => '',
            'show_button' => true,
          ];
        }
      }
      ?>
      <section class="ss-lookbook">
        <div class="ss-container">
          <?php foreach ($lookbook as $i => $item): ?>
            <div class="ss-lookbook-item">
              <div class="ss-lookbook-card">
                <div class="ss-lookbook-media">
                  <?php if (!empty($item['img'])): ?>
                    <img
                      src="<?php echo esc_url($item['img']); ?>"
                      alt="<?php echo esc_attr__('Lookbook image', 'stone-sparkle'); ?>"
                      loading="lazy"
                      width="522"
                      height="697"
                    />
                  <?php else: ?>
                    <div class="ss-lookbook-placeholder" aria-label="<?php echo esc_attr__('Lookbook image placeholder', 'stone-sparkle'); ?>"></div>
                  <?php endif; ?>
                </div>
              </div>
              <?php if (!empty($item['show_button'])) : ?>
              <div class="ss-lookbook-btnwrap">
                <a class="ss-btn" href="<?php echo esc_url($item['link']); ?>" <?php echo !empty($item['target']) && $item['target'] === '_blank' ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($item['cta']); ?></a>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php
    }
  endwhile;
  ?>
</main>

<?php get_footer(); ?>
