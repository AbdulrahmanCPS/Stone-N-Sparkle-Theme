<?php
if (!defined('ABSPATH')) { exit; }

$post_id = (int) get_the_ID();
$post_slug = (string) get_post_field('post_name', $post_id);
// About Us: styled heading lives in the block editor; skip duplicate theme H1.
$hide_theme_h1 = (1104 === $post_id) || ('about-us' === $post_slug);
/**
 * Whether to omit the theme’s automatic page H1 (use an in-content title block instead).
 *
 * @param bool $hide    Default decision.
 * @param int  $post_id Current page ID.
 */
$hide_theme_h1 = (bool) apply_filters('stone_sparkle_hide_page_h1', $hide_theme_h1, $post_id);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class($hide_theme_h1 ? 'ss-page-no-theme-title' : ''); ?>>
  <?php if (!$hide_theme_h1) : ?>
  <header style="margin-bottom:18px;">
    <h1 style="margin:0; font-family: var(--ss-font-display); font-weight: 500; letter-spacing: .02em;">
      <?php the_title(); ?>
    </h1>
  </header>
  <?php endif; ?>
  <div class="entry-content">
    <?php the_content(); ?>
  </div>
</article>
