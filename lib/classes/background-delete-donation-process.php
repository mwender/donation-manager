<?php

class DM_Delete_Donation_Process extends WP_Background_Process {
  /**
   * @var string
   */
  protected $action = 'delete_donation';

  /**
   * Handle
   *
   * Override this method to perform any actions required
   * during the async request.
   */
  protected function task( $donation_id ) {
    $success = wp_delete_post( $donation_id, true );
    if( ! $success )
      error_log( 'Unable to delete donation #' . $donation_id );

    return false;
  }

  /**
   * Complete
   *
   * Override if applicable, but ensure that the below actions are
   * performed, or, call parent::complete().
   */
  protected function complete() {
      parent::complete();

      // Show notice to user or perform some other arbitrary task...
      error_log('[DonationDeleteProcess] Donation #' . $donation_id . ' has been deleted.');
  }
}