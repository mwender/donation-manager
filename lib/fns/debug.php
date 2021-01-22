<?php
if ( !function_exists( 'write_log' ) ) {
    /**
     * Writes a log.
     *
     * @param      mixed  $log    Array or string to log.
     * @param      string $label  Label applied to the log output.
     */
    function write_log( $message, $label = null ) {
        if ( true === WP_DEBUG ) {
            /*
            if( ! is_null( $label ) )
                $label = ':' . $label;
            $label = '[DM' . $label . '] ';
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( $label . print_r( $log, true ) );
            } else {
                error_log( $label . $log );
            }
            */
          static $counter = 1;

          $bt = debug_backtrace();
          $caller = array_shift( $bt );

          if( 1 == $counter )
            error_log( "\n\n" . str_repeat('-', 25 ) . ' STARTING DEBUG [' . date('h:i:sa', current_time('timestamp') ) . '] ' . str_repeat('-', 25 ) . "\n\n" );
          error_log( "\n" . $counter . '. ' . basename( $caller['file'] ) . '::' . $caller['line'] . "\n" . $message . "\n---\n" );
          $counter++;
        }
    }
}
?>