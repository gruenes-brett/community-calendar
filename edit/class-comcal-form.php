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
     * ID of the form tag.
     */
    protected function get_form_id() : string {
        return '';
    }

    /**
     * Map that defines what form field name corresponds to which field name
     * in the internal model.
     *
     * @var array
     */
    protected static $form_field_to_model_field = array();

    /**
     * Names of fields that represent booleans (normally checkboxes).
     * Values will be translated to 0 or 1 before process_data() is called.
     *
     * @var array( str )
     */
    protected static $boolean_fields = array();

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

    /**
     * Gets a string of the full form HTML. Should not be overridden normally.
     * Implement specific form fields in get_form_fields().
     *
     * @throws RuntimeException If the form actions were not registered.
     * @return string Form HTML.
     */
    public function get_form_html() : string {
        if ( ! isset( self::$initialized_forms[ static::class ] ) ) {
            throw new RuntimeException(
                'action for class ' . static::class . ' not registered! Call "' . static::class . '::register_form()" '
                . 'globally first.'
            );
        }
        $admin_ajax_url = admin_url( 'admin-ajax.php' );
        $action_name    = static::$action_name;
        $nonce_field    = wp_nonce_field( $action_name, static::$nonce_name, true, false );

        $form_fields = $this->get_form_fields();
        $form_id     = $this->get_form_id();
        $before_html = $this->get_html_before_form();
        $after_html  = $this->get_html_after_form();

        $form_id_attribute = '' !== $form_id ? "id='$form_id'" : '';

        return <<<XML
            $before_html
            <form action="$admin_ajax_url" $form_id_attribute data-controller="form" method="post">
                $nonce_field
                <input name="action" value="$action_name" type="hidden">

                $form_fields
            </form>
            $after_html
XML;
    }

    protected function get_html_before_form() : string {
        return '';
    }

    protected function get_html_after_form() : string {
        return '';
    }

    /**
     * Override to implement custom form fields.
     */
    abstract protected function get_form_fields() : string;

    /**
     * Receives posted data and validates nonce. If successful, process_data() is called.
     */
    public static function submit_data() {
        $valid_nonce = isset( $_POST[ static::$nonce_name ] ) && wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST[ static::$nonce_name ] ) ),
            static::$action_name
        );
        if ( ! $valid_nonce ) {
            $message = 'You targeted the right function, but sorry, your nonce did not verify.';
            wp_die(
                $message,
                'Error in submission',
                array( 'response' => 500 )
            );
        } else {
            $data = static::transform_post_data( $_POST );
            $res  = static::process_data( $data );
            if ( 200 === $res[0] ) {
                wp_die( $res[1], 'Success' );
            } else {
                wp_die( $res[1], 'Error', array( 'response' => $res[0] ) );
            }
        }
    }

    /**
     * Transforms post data as specified by static::$form_field_to_model_field and
     * static::$boolean_fields.
     *
     * @param array $post_data $_POST content.
     * @return array Transformed post data.
     */
    protected static function transform_post_data( $post_data ) {
        $data_out = array();
        foreach ( $post_data as $key => $value ) {
            $key = static::$form_field_to_model_field[ $key ] ?? $key;
            if ( in_array( $key, static::$boolean_fields, true ) ) {
                $value = 'on' === $value ? 1 : 0;
            }
            $data_out[ $key ] = $value;
        }
        return $data_out;
    }

    /**
     * Override to implement what to do with posted data.
     *
     * @param array $post_data Content of $_POST.
     * @return array Tuple of return code and return message, e.g., array( 200, 'Success' )
     */
    abstract protected static function process_data( $post_data ) : array;
}
