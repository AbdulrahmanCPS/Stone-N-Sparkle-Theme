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
            'images' => [],
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
          <?php foreach ($lookbook as $i => $item):
            $images = isset($item['images']) && is_array($item['images']) ? $item['images'] : [];
            $images = array_values(array_filter($images, function ($im) { return !empty($im['url']); }));
            $img_count = count($images);
          ?>
            <div class="ss-lookbook-item">
              <div class="ss-lookbook-card">
                <div class="ss-lookbook-media">
                  <?php if ($img_count === 0): ?>
                    <div class="ss-lookbook-placeholder" aria-label="<?php echo esc_attr__('Lookbook image placeholder', 'stone-sparkle'); ?>"></div>
                  <?php elseif ($img_count === 1):
                    $one = $images[0];
                    $src = isset($one['url']) ? $one['url'] : '';
                    $alt = isset($one['alt']) && (string) $one['alt'] !== '' ? $one['alt'] : esc_attr__('Lookbook image', 'stone-sparkle');
                    if ($src !== ''):
                    ?>
                    <img
                      src="<?php echo esc_url($src); ?>"
                      alt="<?php echo esc_attr($alt); ?>"
                      loading="lazy"
                      width="522"
                      height="697"
                    />
                    <?php else: ?>
                    <div class="ss-lookbook-placeholder" aria-label="<?php echo esc_attr__('Lookbook image placeholder', 'stone-sparkle'); ?>"></div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="ss-section-slider" role="region" aria-label="<?php echo esc_attr__('Section image slider', 'stone-sparkle'); ?>" tabindex="0">
                      <div class="ss-section-slider__track">
                        <div class="ss-section-slider__slides">
                          <?php foreach ($images as $k => $slide):
                            $surl = isset($slide['url']) ? $slide['url'] : '';
                            $salt = isset($slide['alt']) && (string) $slide['alt'] !== '' ? $slide['alt'] : esc_attr__('Lookbook image', 'stone-sparkle');
                          ?>
                          <div class="ss-section-slider__slide" <?php echo $k === 0 ? ' aria-current="true"' : ''; ?>>
                            <img
                              src="<?php echo esc_url($surl); ?>"
                              alt="<?php echo esc_attr($salt); ?>"
                              loading="<?php echo $k === 0 ? 'eager' : 'lazy'; ?>"
                              width="522"
                              height="697"
                            />
                          </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <button type="button" class="ss-section-slider__prev" aria-label="<?php echo esc_attr__('Previous slide', 'stone-sparkle'); ?>">&larr;</button>
                      <button type="button" class="ss-section-slider__next" aria-label="<?php echo esc_attr__('Next slide', 'stone-sparkle'); ?>">&rarr;</button>
                      <div class="ss-section-slider__counter" aria-live="polite">1 / <?php echo (int) $img_count; ?></div>
                    </div>
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
