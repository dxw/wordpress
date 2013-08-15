<div class="featured-content-wrapper">
	<div id="featured-content" class="featured-content">

		<?php
			do_action( 'twentyfourteen_featured_posts_before' );

			$featured_posts = twentyfourteen_get_featured_posts();
			foreach ( (array) $featured_posts as $order => $post ) :
				setup_postdata( $post );

				get_template_part( 'content', 'featured-post' );
			endforeach;

			do_action( 'twentyfourteen_featured_posts_after' );

			wp_reset_postdata();
		?>

	</div><!-- .featured-content -->
</div><!-- .featured-content-wrapper -->
