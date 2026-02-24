<?php
/**
 * Template: Contact Us (Unified)
 *
 * This file name matches the page slug: /contact-us
 * WordPress will auto-pick page-contact-us.php for the "contact-us" page.
 */

if (!defined('ABSPATH')) { exit; }

get_header();

$post_id = get_the_ID();

// If ACF is missing, fall back to normal page content.
if (!function_exists('get_field')) {
  echo '<main class="ss-page ss-contact" role="main">';
  while (have_posts()) { the_post(); get_template_part('template-parts/content', 'page'); }
  echo '</main>';
  get_footer();
  return;
}

// Global gate.
$cu_page_enabled = (bool) get_field('cu_page_enabled', $post_id);

echo '<main class="ss-page ss-contact" role="main">';

if (!$cu_page_enabled) {
  // Intentionally render nothing inside main (header/footer remain).
  echo '</main>';
  get_footer();
  return;
}

// Helpers.
$esc_textarea = function($val) {
  $val = is_string($val) ? trim($val) : '';
  if ($val === '') return '';
  return wp_kses_post($val);
};

$render_heading = function($title, $subtitle) use ($esc_textarea) {
  $title = is_string($title) ? trim($title) : '';
  $subtitle = is_string($subtitle) ? trim($subtitle) : '';
  if ($title === '' && $subtitle === '') return;
  echo '<div class="ss-contact__head">';
  if ($title !== '') echo '<h2 class="ss-contact__title">' . esc_html($title) . '</h2>';
  if ($subtitle !== '') echo '<div class="ss-contact__subtitle">' . $esc_textarea($subtitle) . '</div>';
  echo '</div>';
};

// Optional color helpers (hex). Applies as inline CSS vars to avoid global overrides.
$section_bg = trim((string) get_field('cu_section_bg_color', $post_id));
$form_bg    = trim((string) get_field('cu_form_bg_color', $post_id));
$style_vars = '';
if ($section_bg !== '') $style_vars .= '--ss-contact-section-bg:' . esc_attr($section_bg) . ';';
if ($form_bg !== '')    $style_vars .= '--ss-contact-form-bg:' . esc_attr($form_bg) . ';';

echo '<div class="ss-contact__wrap"' . ($style_vars ? ' style="' . $style_vars . '"' : '') . '>';

/**
 * A) HERO (optional)
 */
$hero_enabled = (bool) get_field('cu_hero_enabled', $post_id);
if ($hero_enabled) {
  $hero_img = get_field('cu_hero_image', $post_id);
  $hero_title = (string) get_field('cu_hero_title', $post_id);
  $hero_sub   = (string) get_field('cu_hero_subtitle', $post_id);

  echo '<section class="ss-contact__hero">';
  echo '<div class="ss-contact__hero-inner">';

  if (is_array($hero_img) && !empty($hero_img['url'])) {
    $alt = !empty($hero_img['alt']) ? $hero_img['alt'] : '';
    echo '<div class="ss-contact__hero-media">';
    echo '<img class="ss-contact__hero-img" src="' . esc_url($hero_img['url']) . '" alt="' . esc_attr($alt) . '" loading="lazy" />';
    echo '</div>';
  }

  echo '<div class="ss-contact__hero-copy">';
  // If hero title is empty, fall back to page title.
  $fallback_title = get_the_title($post_id);
  $final_title = trim($hero_title) !== '' ? $hero_title : $fallback_title;
  if (trim($final_title) !== '') {
    echo '<h1 class="ss-contact__hero-title">' . esc_html($final_title) . '</h1>';
  }
  if (trim($hero_sub) !== '') {
    echo '<div class="ss-contact__hero-subtitle">' . $esc_textarea($hero_sub) . '</div>';
  }
  echo '</div>';

  echo '</div>';
  echo '</section>';
}

/**
 * B) CONTACT INFO (optional)
 */
$info_enabled = (bool) get_field('cu_info_enabled', $post_id);
if ($info_enabled) {
  echo '<section class="ss-contact__section ss-contact__info">';
  $render_heading(
    get_field('cu_info_title', $post_id),
    get_field('cu_info_subtitle', $post_id)
  );

  $cards = [];
  for ($i = 1; $i <= 4; $i++) {
    $enabled = (bool) get_field('cu_card' . $i . '_enabled', $post_id);
    if (!$enabled) continue;
    $icon = get_field('cu_card' . $i . '_icon', $post_id);
    $title = (string) get_field('cu_card' . $i . '_title', $post_id);
    $text  = (string) get_field('cu_card' . $i . '_text', $post_id);
    if (trim($title) === '' && trim($text) === '' && (!is_array($icon) || empty($icon['url']))) {
      continue;
    }
    $cards[] = compact('icon','title','text');
  }

  if (!empty($cards)) {
    echo '<div class="ss-contact__grid">';
    foreach ($cards as $c) {
      echo '<article class="ss-contact__card">';
      if (is_array($c['icon']) && !empty($c['icon']['url'])) {
        $alt = !empty($c['icon']['alt']) ? $c['icon']['alt'] : '';
        echo '<div class="ss-contact__card-ico">';
        echo '<img src="' . esc_url($c['icon']['url']) . '" alt="' . esc_attr($alt) . '" loading="lazy" />';
        echo '</div>';
      }
      if (trim($c['title']) !== '') {
        echo '<h3 class="ss-contact__card-title">' . esc_html($c['title']) . '</h3>';
      }
      if (trim($c['text']) !== '') {
        echo '<div class="ss-contact__card-text">' . $esc_textarea($c['text']) . '</div>';
      }
      echo '</article>';
    }
    echo '</div>';
  }
  echo '</section>';
}

/**
 * C) GET IN TOUCH (optional)
 */
$form_enabled = (bool) get_field('cu_form_enabled', $post_id);
if ($form_enabled) {
  echo '<section class="ss-contact__section ss-contact__form">';
  $render_heading(
    get_field('cu_form_title', $post_id),
    get_field('cu_form_subtitle', $post_id)
  );

  // IMPORTANT: Keep the form fully inside Contact Form 7.
  // The theme should only render the CF7 shortcode stored in ACF.
  $cf7_sc = trim((string) get_field('cu_cf7_shortcode', $post_id));

  echo '<div class="ss-contact__form-shell">';
  if ($cf7_sc !== '') {
    echo do_shortcode($cf7_sc);
  } else {
    // Show a note for admins only; visitors see nothing.
    if (current_user_can('manage_options')) {
      echo '<div class="ss-contact__admin-note">';
      echo esc_html__('Admin note: Set the Contact Form 7 shortcode in ACF field “cu_cf7_shortcode” to render the form.', 'stone-sparkle');
      echo '</div>';
    }
  }
  echo '</div>';
  echo '</section>';
}

echo '</div>'; // wrap
echo '</main>';

get_footer();
