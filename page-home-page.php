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

      // Lookbook stack (ACF-driven, with safe fallbacks)
      $lookbook = [];
      for ($i = 1; $i <= 7; $i++) {
        $hide = function_exists('get_field') ? (bool) get_field('hide_section_' . $i) : false;
        if ($hide) {
          continue;
        }

        $img = function_exists('get_field') ? (string) get_field('section_' . $i . '_image') : '';
        $btn_text = function_exists('get_field') ? (string) get_field('section_' . $i . '_button_text') : '';
        $btn_link = function_exists('get_field') ? get_field('section_' . $i . '_button_link') : null;

        if ($btn_text === '') {
          $btn_text = __('Shop', 'stone-sparkle');
        }

        $url = $shop;
        $target = '';
        if (is_array($btn_link) && !empty($btn_link['url'])) {
          $url = $btn_link['url'];
          $target = !empty($btn_link['target']) ? $btn_link['target'] : '';
        }

        $lookbook[] = [
          'img' => $img,
          'cta' => $btn_text,
          'link' => $url,
          'target' => $target,
        ];
      }

      // If everything is hidden or ACF isn't present, show 7 placeholders.
      if (empty($lookbook)) {
        for ($i = 1; $i <= 7; $i++) {
          $lookbook[] = [
            'img' => '',
            'cta' => __('Shop', 'stone-sparkle'),
            'link' => $shop,
            'target' => '',
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
              <div class="ss-lookbook-btnwrap">
                <a class="ss-btn" href="<?php echo esc_url($item['link']); ?>" <?php echo $item['target'] ? 'target="'.esc_attr($item['target']).'" rel="noopener"' : ''; ?>><?php echo esc_html($item['cta']); ?></a>
              </div>
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
