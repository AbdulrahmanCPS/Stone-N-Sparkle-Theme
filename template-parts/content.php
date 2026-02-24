<?php
if (!defined('ABSPATH')) { exit; }
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
  <header style="margin-bottom:18px;">
    <h1 style="margin:0; font-family: var(--ss-font-display); font-weight: 500; letter-spacing: .02em;">
      <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    </h1>
  </header>
  <div class="entry-content">
    <?php the_excerpt(); ?>
  </div>
</article>
