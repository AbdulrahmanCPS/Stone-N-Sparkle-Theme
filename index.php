<?php
// Fallback template
get_header();
?>
<main class="ss-main ss-container">
  <?php
  if (have_posts()) {
    while (have_posts()) { the_post();
      if (is_page()) {
        get_template_part('template-parts/content', 'page');
      } else {
        get_template_part('template-parts/content', get_post_type());
      }
    }
  }
  ?>
</main>
<?php get_footer(); ?>
