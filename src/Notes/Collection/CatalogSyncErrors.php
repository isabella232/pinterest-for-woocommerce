<?php
/**
 * Pinterest for WooCommerce CatalogSyncErrors class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

use Automattic\WooCommerce\Pinterest\ProductSync;
use Automattic\WooCommerce\Pinterest\Utilities;
use Automattic\WooCommerce\Pinterest\API\FeedIssues;
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Throwable;

/**
 * Class CatalogSyncErrors.
 *
 * Class responsible for admin Inbox notification after successful connection but
 * when the catalog ingestion fails.
 *
 * @since x.x.x
 */
class CatalogSyncErrors extends AbstractNote {

	const NOTE_NAME = 'pinterest-catalog-sync-error';

	/**
	 * Should the note be added to the inbox.
	 *
	 * @since x.x.x
	 * @return boolean
	 */
	public function should_be_added(): bool {
		if ( ! Pinterest_For_Woocommerce()::is_setup_complete() ) {
			return false;
		}

		if ( ! ProductSync::is_product_sync_enabled() ) {
			return false;
		}

		if ( self::note_exists() ) {
			return false;
		}

		// Are we there yet? We want to try three days after the account was connected.
		if ( time() < ( DAY_IN_SECONDS * 3 + Utilities\get_account_connection_timestamp() ) ) {
			return false;
		}

		try {
			$feed_id  = FeedRegistration::get_registered_feed_id();
			$workflow = FeedIssues::get_feed_workflow( $feed_id );
			if ( false === $workflow ) {
				// No workflow to check.
				return false;
			}
			switch ( $workflow->workflow_status ) {
				case 'COMPLETED':
				case 'COMPLETED_EARLY':
				case 'PROCESSING':
				case 'UNDER_REVIEW':
				case 'QUEUED_FOR_PROCESSING':
					return false;
					break;

				case 'FAILED':
				default:
					return true;
					break;
			}
		} catch ( Throwable $th ) {
			/*
			 *	Whatever failed we don't care about it in this process.
			 */
			return false;
		}

		// We should never get here.
		return true;
	}

	/**
	 * Get note title.
	 *
	 * @since x.x.x
	 * @return string Note title.
	 */
	protected function get_note_title(): string {
		return __( 'Review issues affecting your connection with Pinterest', 'pinterest-for-woocommerce' );
	}

	/**
	 * Get note content.
	 *
	 * @since x.x.x
	 * @return string Note content.
	 */
	protected function get_note_content(): string {
		return __( 'Your product sync to Pinterest was unsuccessful. To complete your connection, Review and resolve issues in the extension.', 'pinterest-for-woocommerce' );
	}

	/**
	 * Add button to Pinterest For WooCommerce landing page
	 *
	 * @since x.x.x
	 * @param Note $note Note to which we add an action.
	 */
	protected function add_action( $note ): void {
		$note->add_action(
			'goto-pinterest-catalog',
			__( 'Complete setup', 'pinterest-for-woocommerce' ),
			wc_admin_url( '&path=/pinterest/catalog' )
		);
	}

}