<?php
/**
 * Basic form submission functionality
 *
 * @package Community_Calendar
 */

/**
 * Defines basic functions for form submission and POST data processing.
 *
 * Derive from this class and override abstract functions and the static fields
 * $nonce_name and $action_name.
 *
 * To register the form call the static function 'register_form()' once.
 */
abstract class Comcal_Form {

    /**
     * Specifies the nonce name.
     *
     * @var string
     */
    protected static $nonce_name = '<set unique nonce name>';

    /**
     * Specifies the action name.
     *
     * @var string
     */
    protected static $action_name = '<set unique action name>';

    /**
     * Keeps track of already registered form classes and their action names.
     *
     * @var array( string => string )
     */
    private static $initialized_forms = array();

    public static function register_form() {
        $action_name = static::$action_name;
        if ( ! isset( self::$initialized_forms[ static::class ] ) ) {
            $existing_class = array_search( $action_name, self::$initialized_forms );
            if ( $existing_class ) {
                throw new RuntimeException(
                    "Action $action_name already registered for class $existing_class!"
                );
            }
            add_action( 'wp_ajax_nopriv_' . $action_name, array( static::class, 'submit_data' ) );
            add_action( 'wp_ajax_' . $action_name, array( static::class, 'submit_data' ) );
            self::$initialized_forms[ static::class ] = $action_name;
        }
    }

    public static function render_empty_form() {
        $form = new static();
        echo $form->get_form_html();
    }

    public function get_form_html() : string {
        if ( ! isset( self::$initialized_forms[ static::class ] ) ) {
            throw new RuntimeException( 'action for class ' . static::class . ' not registered!' );
        }
        $admin_ajax_url = admin_url( 'admin-ajax.php' );
        $action_name    = static::$action_name;
        $nonce_field    = wp_nonce_field( $action_name, static::$nonce_name, true, false );

        $form_fields = $this->get_form_fields();

        return <<<XML
            <form action="$admin_ajax_url" data-controller="form" method="post">
                $nonce_field
                <input name="action" value="$action_name" type="hidden">

                $form_fields
            </form>
XML;
    }

    abstract protected function get_form_fields() : string;

    public static function submit_data() {
        $valid_nonce = isset( $_POST[ static::$nonce_name ] ) && wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST[ static::$nonce_name ] ) ),
            static::$action_name
        );
        if ( ! $valid_nonce ) {
            $message = 'You targeted the right function, but sorry, your nonce did not verify.';
            echo $message;
            wp_die(
                $message,
                'Error in submission',
                array( 'response' => 500 )
            );
        } else {
            $res = static::process_data( $_POST );
            if ( 200 === $res[0] ) {
                wp_die( $res[1], 'Success' );
            } else {
                wp_die( $res[1], 'Error', array( 'response' => $res[0] ) );
            }
        }
    }

    abstract protected static function process_data( $post_data ) : array;
}
