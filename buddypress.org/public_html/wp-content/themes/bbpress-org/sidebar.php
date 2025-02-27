</div>

<?php

// Skip on front & 404
if ( is_front_page() || is_404() ) :
	return;
endif;

?>

<div class="sidebar">

	<?php if ( ( ! is_front_page() && is_bbpress() ) || is_page( 'new-topic' ) ) : ?>

		<?php if ( bbp_is_single_forum() || bb_base_topic_search_query( false ) ) : ?>

			<div>
				<h2><?php _e( 'Forum Info', 'bbporg' ); ?></h2>
				<ul class="forum-info">
					<?php bb_base_single_forum_description(); ?>
				</ul>
			</div>

			<?php bb_base_topic_search_form(); ?>

			<div>
				<h2><?php _e( 'Forum Feeds', 'bbporg' ); ?></h2>
				<ul>
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/"><?php _e( 'Recent Posts', 'bbporg' ); ?></a></li>
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/?type=topic"><?php _e( 'Recent Topics', 'bbporg' ); ?></a></li>
				</ul>
			</div>

		<?php elseif ( bbp_is_single_topic() || bbp_is_topic_edit() || bbp_is_reply_edit() ) : ?>

			<div>
				<h2><?php _e( 'Topic Info', 'bbporg' ); ?></h2>
				<ul class="topic-info">
					<?php bb_base_single_topic_description(); ?>
				</ul>
			</div>

			<div>
				<?php bbp_topic_tag_list( 0, array(
					'before' => '<h2>' . __( 'Topic Tags', 'bbporg' ) . '</h2><ul class="topic-tags"><li>',
					'after'  => '</li></ul>',
					'sep'    => '</li><li>',
				) ); ?>
			</div>

			<?php bb_base_reply_search_form(); ?>

			<?php if ( current_user_can( 'moderate', bbp_get_topic_id() ) ) : ?>

				<div>
					<?php bbp_topic_admin_links( array (
						'id'     => bbp_get_topic_id(),
						'before' => '<h2>' . __( 'Topic Admin', 'bbporg' ) . '</h2><ul class="topic-admin-links"><li>',
						'after'  => '</li></ul>',
						'sep'    => '</li><li>',
						'links'  => array()
					) ); ?>
				</div>

			<?php endif; ?>

		<?php else : ?>

			<div>
				<h2><?php _e( 'Forums', 'bbporg' ); ?></h2>
				<?php echo do_shortcode( '[bbp-forum-index]' ); ?>
			</div>
			<hr class="hidden" />

			<div>
				<h2><?php _e( 'Views', 'bbporg' ); ?></h2>
				<ul>

					<?php foreach ( bbp_get_views() as $view => $args ) : ?>

						<li><a class="bbp-view-title" href="<?php bbp_view_url( $view ); ?>"><?php bbp_view_title( $view ); ?></a></li>

					<?php endforeach; ?>

				</ul>
			</div>

			<div>
				<h2><?php _e( 'Feeds', 'bbporg' ); ?></h2>
				<ul>
					<li><a class="feed" href="<?php bbp_forums_url(); ?>feed/"><?php _e( 'All Recent Posts', 'bbporg' ); ?></a></li>
					<li><a class="feed" href="<?php bbp_topics_url(); ?>feed/"><?php _e( 'All Recent Topics', 'bbporg' ); ?></a></li>
				</ul>
			</div>

			<div class="bbp-topic-tag-cloud">
				<h2><?php _e( 'Tags', 'bbporg' ); ?></h2>
				<?php echo do_shortcode( '[bbp-topic-tags]' ); ?>
			</div>

		<?php endif; ?>

	<?php elseif ( is_page( array( 'plugins', 116247 ) ) ) : ?>

		<?php bb_base_plugin_search_form(); ?>

		<div>
			<h2><?php _e( 'Legacy', 'bbporg' ); ?></h2>
			<ul>
				<li><a href="<?php echo get_permalink( 116247 ); ?>"><?php _e( 'Plugins for bbPress 1.1', 'bbporg' ); ?></a></li>
			</ul>
		</div>

	<?php elseif ( ( ! is_page( 'login' ) && ! is_page( 'register' ) && ! is_page( 'lost-password' ) ) || is_home() || is_singular( 'post' ) || is_archive() ) : ?>

		<div>
			<h2><?php _e( 'Categories', 'bbporg' ); ?></h2>
			<ul>
				<?php wp_list_categories( array( 'title_li' => false ) ); ?>
			</ul>
		</div>

		<div>
			<h2><?php _e( 'Tags', 'bbporg' ); ?></h2>
			<?php wp_tag_cloud(); ?>
		</div>

	<?php endif; ?>

</div>
