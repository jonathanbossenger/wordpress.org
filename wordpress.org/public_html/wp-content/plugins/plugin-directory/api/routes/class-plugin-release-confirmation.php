<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WP_REST_Response;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Import;
use WordPressdotorg\Plugin_Directory\Email\Release_Confirmation_Enabled as Release_Confirmation_Enabled_Email;
use Two_Factor_Core;
use function WordPressdotorg\Two_Factor\Revalidation\{
	get_status as get_revalidation_status,
	get_url as get_revalidation_url,
};

/**
 * An API endpoint for closing a particular plugin.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Release_Confirmation extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/release-confirmation', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'enable_release_confirmation' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
			],
			'permission_callback' => function( $request ) {
				$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

				return current_user_can( 'plugin_manage_releases', $plugin );
			},
		] );

		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/release-confirmation/(?P<plugin_tag>[^/]+)', [
			'methods'             => \WP_REST_Server::READABLE, // TODO: This really should be a POST
			'callback'            => [ $this, 'confirm_release' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
				'plugin_tag' => [
					'validate_callback' => [ $this, 'validate_plugin_tag_callback' ],
				]
			],
			'permission_callback' => [ $this, 'permission_can_access_plugin' ],
		] );

		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/release-confirmation/(?P<plugin_tag>[^/]+)/discard', [
			'methods'             => \WP_REST_Server::READABLE, // TODO: This really should be a POST
			'callback'            => [ $this, 'discard_release' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
				'plugin_tag' => [
					'validate_callback' => [ $this, 'validate_plugin_tag_callback' ],
				]
			],
			'permission_callback' => [ $this, 'permission_can_access_plugin' ],
		] );

		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/release-confirmation/(?P<plugin_tag>[^/]+)/undo-discard', [
			'methods'             => \WP_REST_Server::READABLE, // TODO: This really should be a POST
			'callback'            => [ $this, 'undo_discard_release' ],
			'args'                => [
				'plugin_slug' => [
					'validate_callback' => [ $this, 'validate_plugin_slug_callback' ],
				],
				'plugin_tag' => [
					'validate_callback' => [ $this, 'validate_plugin_tag_callback' ],
				]
			],
			'permission_callback' => function( $request ) {
				if ( current_user_can( 'plugin_review' ) ) {
					return $this->permission_can_access_plugin( $request );
				}

				return false;
			},
		] );

		add_filter( 'rest_pre_echo_response', [ $this, 'override_cookie_expired_message' ], 10, 3 );
	}

	/**
	 * Redirect back to the plugins page when this endpoint is accessed with an invalid nonce.
	 */
	function override_cookie_expired_message( $result, $obj, $request ) {
		if (
			is_array( $result ) && isset( $result['code'] ) &&
			(
				preg_match( '!^/plugins/v1/plugin/([^/]+)/release-confirmation(/[^/]+)?$!', $request->get_route(), $m )
				||
				'/plugins/v1/release-confirmation-access' === $request->get_route()
			)
		) {
			if ( 'rest_cookie_invalid_nonce' == $result['code'] || 'rest_forbidden' == $result['code'] ) {
				wp_die( 'The link you have followed has expired.' );
			}
		}

		return $result;
	}

	/**
	 * Validate that the user can manage releases for the given plugin.
	 */
	public function permission_can_access_plugin( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		if ( ! $plugin || ! current_user_can( 'plugin_manage_releases', $plugin ) ) {
			return false;
		}

		// Check to see if they've confirmed their 2FA status recently..
		$status = get_revalidation_status();
		if ( $status && $status['can_save'] ) {
			return true;
		}

		// Before we say no, check if the user just needs to validate their 2FA.
		if ( $status && $status['needs_revalidate'] && 'GET' === $request->get_method() ) {
			$current_rest_url = add_query_arg(
				array(
					'_wpnonce'         => wp_create_nonce( 'wp_rest' ),
					'_wp_http_referer' => wp_get_referer(),
				),
				get_rest_url( null, $request->get_route() )
			);

			wp_safe_redirect( get_revalidation_url( $current_rest_url ) );
			exit;
		}

		return false;
	}

	/**
	 * Endpoint to self-close a plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public function enable_release_confirmation( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$result = [
			'location' => wp_get_referer() ?: get_permalink( $plugin ),
		];

		$confirmations_required = $request['confirmations_required'] ?? 1;

		// Only redirect if we've been called via the rest api, and not an internal api request.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			header( 'Location: ' . $result['location'] );

			// When requested via the REST API, confirmations can only be increased.
			$confirmations_required = max( (int)$confirmations_required, (int)$plugin->release_confirmation );
		}

		// Abort early if needed.
		if ( $plugin->release_confirmation == $confirmations_required ) {
			return $result;
		}

		// Fetch the releases first, to prefill them if needed with the old release_confirmation count.
		Plugin_Directory::get_releases( $plugin );

		// Update the Metadata.
		update_post_meta( $plugin->ID, 'release_confirmation', $confirmations_required );

		// Add an audit-log entry.
		Tools::audit_log(
			sprintf(
				'Release Confirmations Enabled. %d confirmations required',
				$confirmations_required
			),
			$plugin
		);

		$email = new Release_Confirmation_Enabled_Email(
			$plugin,
			Tools::get_plugin_committers( $plugin->post_name ),
			[
				// Don't include the plugin reviewer who triggered this.
				'who' => is_admin() ? 'WordPress.org' : null,
			]
		);
		$email->send();

		return $result;
	}

	/**
	 * A simple endpoint to confirm a release.
	 */
	public function confirm_release( $request ) {
		$user_login = wp_get_current_user()->user_login;
		$plugin     = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$tag        = $request['plugin_tag'];
		$release    = Plugin_Directory::get_release( $plugin, $tag );
		$result     = [
			'location' => wp_get_referer() ?: home_url( '/developers/releases/' ),
		];
		header( 'Location: ' . $result['location'] );

		if ( ! $release || ! empty( $release['confirmed'][ $user_login ] ) || ! empty( $release['discarded'] ) ) {
			// Already confirmed, or unable to be confirmed.
			$result['confirmed'] = false;
			return $result;
		}

		// Record this user as confirming the release.
		$release['confirmations'][ $user_login ] = time();
		$result['confirmed']                     = true;

		// Mark the release as confirmed if enough confirmations.
		if ( count( $release['confirmations'] ) >= $release['confirmations_required'] ) {
			$release['confirmed']      = true;
			$result['fully_confirmed'] = true;
		}

		Plugin_Directory::add_release( $plugin, $release );

		// Trigger the import for the plugin.
		Plugin_Import::queue(
			$plugin->post_name,
			// TODO this is not 100% right... but will probably work.
			[
				'tags_touched'   => [
					'trunk',
					$tag
				],
				// Assume everything was modified.
				'readme_touched' => true,
				'code_touched'   => true,
				'assets_touched' => true,
				'revisions'      => $release['revision'],
			]
		);

		return $result;
	}

	/**
	 * A simple endpoint to decline/discard a release.
	 */
	public function discard_release( $request ) {
		$user_login = wp_get_current_user()->user_login;
		$plugin     = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$tag        = $request['plugin_tag'];
		$release    = Plugin_Directory::get_release( $plugin, $tag );
		$result     = [
			'location' => wp_get_referer() ?: home_url( '/developers/releases/' ),
		];
		header( 'Location: ' . $result['location'] );

		if ( ! $release || $release['confirmed'] || ! empty( $release['discarded'] ) ) {
			// Already confirmed, or other error encountered.
			$result['confirmed'] = false;
			return $result;
		}

		// Record this user as discarding the release.
		$release['confirmed'] = false; // Already false, just noting it here explicitely.
		$release['discarded'] = [
			'user' => $user_login,
			'time' => time(),
		];

		Plugin_Directory::add_release( $plugin, $release );

		return $result;
	}

	/**
	 * A simple endpoint to undo discarding a release.
	 */
	public function undo_discard_release( $request ) {
		$plugin     = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );
		$tag        = $request['plugin_tag'];
		$release    = Plugin_Directory::get_release( $plugin, $tag );
		$result     = [
			'location' => wp_get_referer() ?: home_url( '/developers/releases/' ),
		];
		header( 'Location: ' . $result['location'] );

		if ( ! $release || empty( $release['discarded'] ) ) {
			// Not found or not discarded.
			$result['confirmed'] = false;
			return $result;
		}

		// Log this action.
		Tools::audit_log(
			sprintf(
				'Release %s discard reverted. Originally discarded by %s at %s',
				$tag,
				$release['discarded']['user'],
				date( 'Y-m-d H:i:s', $release['discarded']['time'] )
			),
			$plugin
		);

		// Remove the discard state.
		unset( $release['discarded'] );
		$release['undo-discard'] = true;

		Plugin_Directory::add_release( $plugin, $release );

		return $result;
	}

	public function validate_plugin_tag_callback( $tag, $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		return $plugin && (bool) Plugin_Directory::get_release( $plugin, $tag );
	}
}
