<?php if ( ! bbp_is_single_forum() && ! bbp_is_single_view() ) : ?>

<div id="bbpress-forums">

<?php endif; ?>

<?php if ( bbp_current_user_can_access_create_topic_form() ) : ?>

	<div id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form <?php echo esc_attr( bbp_get_view_id() ); ?>">

		<form id="new-post" name="new-post" method="post" action="<?php ( bbp_is_topic_edit() ) ? bbp_topic_permalink() : '#new-post'; ?>">

			<?php do_action( 'bbp_theme_before_topic_form' ); ?>

			<?php if ( bbp_is_topic_edit() ) { ?>

				<h1>
					<?php printf( __( 'Now Editing &ldquo;%s&rdquo;', 'wporg-forums' ), bbp_get_topic_title() ); ?>
				</h1>

			<?php } else { ?>

				<h2>
					<?php if ( bbp_is_single_forum() ) {
						_e( 'Create a new topic in this forum', 'wporg-forums' );
					} elseif ( bbp_is_single_view() && 'reviews' === bbp_get_view_id() ) {
						_e( 'Create a new review', 'wporg-forums' );
					} else {
						_e( 'Create a new topic', 'wporg-forums' );
					} ?>
				</h2>

				<?php
				if (
					defined( 'WPORG_ON_HOLIDAY' ) && WPORG_ON_HOLIDAY &&
					bbp_is_single_view() && 'reviews' === bbp_get_view_id()
				) {
					echo do_blocks(
						sprintf(
							'<!-- wp:wporg/notice {"type":"warning"} -->
							<div class="wp-block-wporg-notice is-warning-notice">
								<div class="wp-block-wporg-notice__icon"></div>
								<div class="wp-block-wporg-notice__content">
									<p>%s</p>
								</div>
							</div>
							<!-- /wp:wporg/notice -->',
							sprintf(
								__( 'New reviews are currently disabled. Please check back after the <a href="%s">holiday break.</a>', 'wporg-forums' ),
								'https://wordpress.org/news/2024/12/holiday-break/'
							)
						)
					);
					echo '</form></div>';
					return;
				}
				?>

			<?php } ?>

			<fieldset class="bbp-form">

				<?php do_action( 'bbp_theme_before_topic_form_notices' ); ?>

				<?php if ( ! bbp_is_topic_edit() && ! bbp_is_forum_closed() ) : ?>

					<div class="bbp-template-notice info">

						<?php if ( bbp_is_single_view() && 'reviews' === bbp_get_view_id() ) : ?>

							<?php do_action( 'wporg_compat_new_review_notice' ); ?>

						<?php else : ?>

							<p><?php _e( 'Before posting a new topic, follow these steps:', 'wporg-forums' ); ?></p>
							<ul>
								<li><?php
									/* translators: %s: Handbook URL for forum welcome */
									printf( __( '<strong><a href="%s">Read the Welcome Guide</a></strong> to maximize your odds of getting help.', 'wporg-forums' ),
										esc_url( __( 'https://wordpress.org/support/welcome/', 'wporg-forums' ) )
									);
								?></li>
								<li><?php
									/* translators: %s: URL to search */
									printf( __( '<strong><a href="%s">Search the forums</a></strong> for similar inquiries.', 'wporg-forums' ),
										esc_url( bbp_get_search_url() )
									);
								?></li>
								<li><?php _e( '<strong>Update your plugins, themes, and WordPress site</strong> to the latest versions.', 'wporg-forums' ); ?></li>
								<li><?php _e( '<strong>Describe the steps</strong> needed to reproduce an issue.', 'wporg-forums' ); ?></li>
								<li><?php _e( '<strong>Provide relevant information</strong>, such as your browser, operating system, or server environment.', 'wporg-forums' ); ?></li>
								<?php if ( ! bbp_is_single_view() || ! in_array( bbp_get_view_id(), array( 'theme', 'plugin' ) ) ) : ?>
								<li><?php
									/* translators: 1: Theme Directory URL, 2: Appearance icon, 3: Plugin Directory URL, 4: Plugins icon */
									printf( __( '<strong>Don\'t use this forum to ask for help with <a href="%1$s">themes</a> or <a href="%2$s">plugins</a></strong>.  Instead, head to the theme or plugin\'s page and find the "View support forum" link for its specific forum.', 'wporg-forums' ),
										esc_url( __( 'https://wordpress.org/themes/', 'wporg-forums' ) ),
										esc_url( __( 'https://wordpress.org/plugins/', 'wporg-forums' ) )
									);
								?></li>
								<?php endif; ?>
								<li><?php
									/* translators: %s: Handbook URL for reporting security issues */
									printf( __( '<strong>Reporting a security issue?</strong> Please follow the guidelines on <a href="%s">reporting vulnerabilities responsibly</a>.', 'wporg-forums' ),
										esc_url( __( 'https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/', 'wporg-forums' ) )
									);
								?></li>
							</ul>

						<?php endif; ?>

					</div>

				<?php endif; ?>

				<?php if ( !bbp_is_topic_edit() && bbp_is_forum_closed() ) : ?>

					<div class="bbp-template-notice">
						<p><?php _e( 'This forum is marked as closed to new topics, however your posting capabilities still allow you to create a topic.', 'wporg-forums' ); ?></p>
					</div>

				<?php endif; ?>

				<?php if ( current_user_can( 'unfiltered_html' ) ) : ?>

					<div class="bbp-template-notice">
						<p><?php _e( 'Your account has the ability to post unrestricted HTML content.', 'wporg-forums' ); ?></p>
					</div>

				<?php endif; ?>

				<?php do_action( 'bbp_template_notices' ); ?>

				<div>

					<?php bbp_get_template_part( 'form', 'anonymous' ); ?>

					<?php do_action( 'bbp_theme_before_topic_form_title' ); ?>

					<p>
						<label for="bbp_topic_title"><?php
							if ( bbp_is_single_view() && 'reviews' === bbp_get_view_id() ) {
								printf( __( 'Review Title (Maximum Length: %d):', 'wporg-forums' ), bbp_get_title_max_length() );
							} else {
								printf( __( 'Topic Title (Maximum Length: %d):', 'wporg-forums' ), bbp_get_title_max_length() );
							}
						?></label><br />
						<input type="text" id="bbp_topic_title" value="<?php bbp_form_topic_title(); ?>" size="40" name="bbp_topic_title" maxlength="<?php bbp_title_max_length(); ?>" />
					</p>

					<?php do_action( 'bbp_theme_after_topic_form_title' ); ?>

					<?php do_action( 'bbp_theme_before_topic_form_content' ); ?>

					<label for="bbp_topic_content"><?php esc_html_e( 'Your message:', 'wporg-forums' ); ?></label>
					<?php bbp_the_content( array( 'context' => 'topic' ) ); ?>

					<?php do_action( 'bbp_theme_after_topic_form_content' ); ?>

					<?php do_action( 'bbp_theme_before_topic_form_tags' ); ?>

					<p>
						<label for="bbp_topic_tags"><?php
							if ( bbp_is_single_view() && 'reviews' === bbp_get_view_id() ) {
								_e( 'Review Tags:', 'wporg-forums' );
							} else {
								_e( 'Topic Tags:', 'wporg-forums' );
							}
						?></label><br />
						<input type="text" value="<?php bbp_form_topic_tags(); ?>" size="40" name="bbp_topic_tags" id="bbp_topic_tags" aria-describedby="bbp_topic_tags_description" <?php disabled( bbp_is_topic_spam() ); ?> /><br />
						<em id="bbp_topic_tags_description"><?php esc_html_e( 'Separate tags with commas', 'wporg-forums' ); ?></em>
					</p>

					<?php do_action( 'bbp_theme_after_topic_form_tags' ); ?>

					<?php if ( ! bbp_is_single_forum() && ! bbp_is_single_view() ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_forum' ); ?>

						<p>
							<label for="bbp_forum_id"><?php _e( 'Forum:', 'wporg-forums' ); ?></label><br />
							<?php bbp_dropdown( array( 'selected' => bbp_get_form_topic_forum() ) ); ?>
						</p>

						<?php do_action( 'bbp_theme_after_topic_form_forum' ); ?>

					<?php endif; ?>

					<?php if ( bbp_is_subscriptions_active() && !bbp_is_anonymous() && ( !bbp_is_topic_edit() || ( bbp_is_topic_edit() && !bbp_is_topic_anonymous() ) ) ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_subscriptions' ); ?>

						<p>
							<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe" <?php bbp_form_topic_subscribed(); ?> />

							<?php if ( bbp_is_topic_edit() && ( get_the_author_meta( 'ID' ) != bbp_get_current_user_id() ) ) : ?>

								<label for="bbp_topic_subscription"><?php _e( 'Notify the author of follow-up replies via email', 'wporg-forums' ); ?></label>

							<?php else : ?>

								<label for="bbp_topic_subscription"><?php _e( 'Notify me of follow-up replies via email', 'wporg-forums' ); ?></label>

							<?php endif; ?>
						</p>

						<?php do_action( 'bbp_theme_after_topic_form_subscriptions' ); ?>

					<?php endif; ?>

					<?php if ( bbp_allow_revisions() && bbp_is_topic_edit() ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_revisions' ); ?>

						<fieldset class="bbp-form log-edit">
							<legend>
								<input name="bbp_log_topic_edit" id="bbp_log_topic_edit" type="checkbox" value="1" <?php bbp_form_topic_log_edit(); ?> />
								<label for="bbp_log_topic_edit"><?php _e( 'Keep a log of this edit:', 'wporg-forums' ); ?></label><br />
							</legend>

							<div>
								<label for="bbp_topic_edit_reason"><em><?php _e( 'Optional reason for editing:', 'wporg-forums' ); ?></em></label><br />
								<input type="text" value="<?php bbp_form_topic_edit_reason(); ?>" size="40" name="bbp_topic_edit_reason" id="bbp_topic_edit_reason" />
							</div>
						</fieldset>

						<?php do_action( 'bbp_theme_after_topic_form_revisions' ); ?>

					<?php endif; ?>

					<?php do_action( 'bbp_theme_before_topic_form_submit_wrapper' ); ?>

					<div class="bbp-submit-wrapper">

						<?php do_action( 'bbp_theme_before_topic_form_submit_button' ); ?>

						<button type="submit" id="bbp_topic_submit" name="bbp_topic_submit" class="button button-primary submit"><?php _e( 'Submit', 'wporg-forums' ); ?></button>

						<?php do_action( 'bbp_theme_after_topic_form_submit_button' ); ?>

					</div>

					<?php do_action( 'bbp_theme_after_topic_form_submit_wrapper' ); ?>

				</div>

				<?php bbp_topic_form_fields(); ?>

			</fieldset>

			<?php do_action( 'bbp_theme_after_topic_form' ); ?>

		</form>
	</div>

<?php elseif ( bbp_is_forum_closed() ) : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<div class="bbp-template-notice">
			<p><?php printf( __( 'The forum &#8216;%s&#8217; is closed to new topics and replies.', 'wporg-forums' ), bbp_get_forum_title() ); ?></p>
		</div>
	</div>

<?php elseif ( is_user_logged_in() ) : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<div class="bbp-template-notice">
			<p><?php _e( 'You cannot create new topics at this time.', 'wporg-forums' ); ?></p>
		</div>
	</div>

<?php endif; ?>

<?php if ( ! bbp_is_single_forum() && ! bbp_is_single_view() ) : ?>

</div>

<?php endif; ?>
