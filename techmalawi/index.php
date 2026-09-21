<?php get_header(); ?><div class="container section"><?php if ( have_posts() ) while ( have_posts() ) : the_post(); the_content(); endwhile; ?></div><?php get_footer(); ?>
