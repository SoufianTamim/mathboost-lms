<?php
/**
 * Template for single mb_qcm posts
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="mb-main" class="mb-page-main">
  <?php while ( have_posts() ) : the_post(); ?>
    <?php echo do_shortcode( '[mathboost_qcm id="' . get_the_ID() . '"]' ); ?>
  <?php endwhile; ?>
</main>

<?php get_footer(); ?>
