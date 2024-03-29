<?php
/**
 * Helper for checking user capabilities.
 *
 * @package GruenesBrett
 */

/**
 * Class for checking user caps.
 */
class Comcal_User_Capabilities {

    /**
     * Returns whether the currently logged in user may edit other user's events.
     *
     * This is admins and editors.
     *
     * @return bool
     */
    public static function administer_events() : bool {
        return current_user_can( 'edit_others_posts' );
    }

    /**
     * Returns whether the currently logged in user may edit categories.
     *
     * This is admins only.
     *
     * @return bool
     */
    public static function edit_categories() : bool {
        return current_user_can( 'edit_users' );
    }

    /**
     * Returns whether the currently logged in user may edit own events.
     *
     * This is admins, editors and authors.
     *
     * @return bool
     */
    public static function edit_own_events() : bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Returns the current user id.
     *
     * @return int User ID or 0 if not logged in.
     */
    public static function current_user_id() : int {
        return get_current_user_id();
    }

    /**
     * Returns whether a user is logged in.
     */
    public static function is_logged_in() {
        return 0 !== static::current_user_id();
    }

    /**
     * Returns whether the currently logged in user may edit events.
     * Either his own or others.
     *
     * This is admins, editors and authors.
     */
    public static function has_edit_privileges() {
        return static::edit_own_events();
    }
}
