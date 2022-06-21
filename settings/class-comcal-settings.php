<?php
/**
 * Admin settings page for Community Calendar.
 *
 * @package Community_Calendar
 */

/**
 * Base class with helpers for admin settings page.
 */
class Comcal_Settings {
    /**
     * Singleton instance.
     *
     * @var Comcal_Settings
     */
    private static $instance = null;

    /**
     * All subclasses that define settings.
     *
     * @var array(Comcal_Settings)
     */
    private static $sections = array();

    public static function initialize() {
        if ( null !== self::$instance ) {
            throw new RuntimeException( 'Comcal_Settings instance already set' );
        }
        self::$instance = new Comcal_Settings();
        add_action( 'admin_menu', array( self::$instance, 'comcal_settings_add_plugin_page' ) );
        add_action( 'admin_init', array( self::$instance, 'comcal_settings_page_init' ) );
    }

    public static function get_instance(): Comcal_Settings {
        return self::$instance;
    }

    public static function add_settings_instance( Comcal_Settings $section ) {
        self::$sections[] = $section;
    }

    public static function get_option_value( $name ) {
        $option_map = static::get_option_ids();
        $options    = get_option( 'comcal_settings_option_name' ); // Array of All Options.
        if ( false === $options || ! isset( $options[ $option_map[ $name ] ] ) ) {
            // Default value.
            $options = array(
                $option_map[ $name ] => '',
            );
        }
        return $options[ $option_map[ $name ] ];
    }

    // Override the following functions.

    public static function get_section_title() {
        throw new RuntimeException( 'must be overridden' );
    }

    public static function get_section_tag() {
        throw new RuntimeException( 'must be overridden' );
    }

    public static function get_option_ids() {
        throw new RuntimeException( 'must be overridden' );
    }

    public static function get_option_labels() {
        throw new RuntimeException( 'must be overridden' );
    }

    public function __construct() {
    }

    public function comcal_settings_add_plugin_page() {
        add_options_page(
            'Community Calendar Settings', // page_title.
            'Community Calendar', // menu_title.
            'manage_options', // capability.
            'comcal-settings', // menu_slug.
            array( $this, 'comcal_settings_create_admin_page' ) // function.
        );
    }

    public function comcal_settings_create_admin_page() {
        ?>

            <div class="wrap">
                <h2>Community Calendar Einstellungen</h2>
                <p></p>
                <?php settings_errors(); ?>

                <form method="post" action="options.php">
                    <?php
                        settings_fields( 'comcal_settings_option_group' );
                        do_settings_sections( 'comcal-settings-admin' );
                        submit_button();
                    ?>
                </form>
            </div>
        <?php
    }

    public function comcal_settings_page_init() {
        if ( $this !== self::$instance ) {
            throw new RuntimeException( 'Must only be called on base class' );
        }

        register_setting(
            'comcal_settings_option_group', // option_group.
            'comcal_settings_option_name', // option_name.
            array( $this, 'comcal_settings_sanitize' ) // sanitize_callback.
        );

        foreach ( $this::$sections as $section ) {
            $section->register_settings();
        }
    }

    protected function register_settings() {

        $section_id = "comcal_settings_section_{$this->get_section_tag()}";
        add_settings_section(
            $section_id, // id.
            $this->get_section_title(), // title.
            array( $this, 'comcal_settings_section_info' ), // callback.
            'comcal-settings-admin' // page.
        );

        foreach ( $this->get_option_ids() as $option_name => $option_id ) {
            add_settings_field(
                $option_name, // id.
                $this->get_option_labels()[ $option_name ], // title.
                array( $this, "{$option_id}_callback" ), // callback.
                'comcal-settings-admin', // page.
                $section_id // section.
            );
        }
    }

    public function comcal_settings_sanitize( $input ) {
        $sanitary_values = array();
        foreach ( self::$sections as $section ) {
            foreach ( $section->get_option_ids() as $option_id ) {
                if ( isset( $input[ $option_id ] ) ) {
                    $sanitary_values[ $option_id ] = sanitize_text_field( $input[ $option_id ] );
                }
            }
        }
        return $sanitary_values;
    }

    public function comcal_settings_section_info() {

    }
}

if ( is_admin() ) {
    Comcal_Settings::initialize();
}
