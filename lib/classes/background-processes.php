<?php

class DM_Donation_Count_Process extends WP_Background_Process {

    /**
     * Name of the process
     *
     * @var        string
     */
    protected $action = 'donation_count_process';

    /**
     * Month for the donation counts
     *
     * @var        string
     */
    public $month = '';

    /**
     * Generates a donation count for each organization
     *
     * @param      array  $item   Has values for the Org ID and month.
     *
     * @return     false  Removes the item from the background processing queue.
     */
    protected function task( $item ){
        $org = new \Organization( $item['ID'] );
        $org->save_donation_count( $item['month'] );

        return false;
    }

    /**
     * Runs when the queue is complete
     */
    protected function complete() {
        error_log( '[WP-BKGRD-PROC] Running complete() method.' );
        parent::complete();
    }
}