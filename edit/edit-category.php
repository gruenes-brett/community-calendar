<?php
/**
 * Functions for showing the category edit form.
 *
 * @package Community_Calendar
 */

/**
 * Creates a form for editing one category.
 *
 * @param Comcal_Category $category Category instance.
 * @param int             $index Number that is used as a suffix to the name and id fields.
 * @return string HTML of the form.
 */
function _comcal_edit_category_form( $category, $index ) {
    $suffix      = "_$index";
    $name        = $category->get_field( 'name' );
    $category_id = $category->get_field( 'categoryId' );
    return <<<XML
    <div class="form-group">
        <input name="categoryId$suffix" id="categoryId$suffix" value="$category_id" type="hidden">
        <label for="categoryName$suffix">Name</label>
        <input type="text" class="form-control" name="name$suffix" id="categoryName$suffix" placeholder="" value="$name" required>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="delete$suffix" id="categoryDelete$suffix" value="$category_id" unchecked>
            <label class="form-check-label" for="categoryDelete$suffix">Kategorie löschen?</label>
        </div>
    </div>
XML;
}

/**
 * Produces a form that contains all existing categories.
 *
 * @return string HTML of the form.
 */
function _comcal_all_category_forms() {
    $all   = Comcal_Category::get_all();
    $out   = '';
    $index = 0;
    foreach ( $all as $c ) {
        $out .= _comcal_edit_category_form( $c, $index );
        $index++;
    }
    return $out;
}

/**
 * Produces div that contains the forms for editing all categories.
 *
 * @return string HTML of the dialog box.
 */
function comcal_get_edit_categories_dialog() {
    $post_url    = admin_url( 'admin-ajax.php' );
    $nonce_field = wp_nonce_field( 'comcal_edit_categories', 'verification-code-categories', true, false );
    $all_forms   = _comcal_all_category_forms();
    return <<<XML
    <div class="comcal-modal-wrapper edit-cats-dialog">
        <div class="comcal-close">X</div>
        <div class="form-popup" id="editCategories">
            <h2>Kategorien bearbeiten</h2>
            <form id="editCategories" action="$post_url" method="post">
                <fieldset>
                    <input name="action" value="comcal_edit_categories" type="hidden">
                    $nonce_field
                    $all_forms

                    <div class="form-group">
                        <label for="categoryName_new">Neue Kategorie</label>
                        <input type="text" class="form-control" name="name_new" id="categoryName_new" placeholder="" value="">
                    </div>
                    <div class="btn-group">
                        <input type="button" class="btn btn-secondary comcal-cancel" value="Zurück">
                        <input type="submit" class="btn btn-success comcal-send" value="Senden">
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
XML;
}

/**
 * Handles the edit categories form submission.
 */
function comcal_submit_edit_categories_func() {
    $nonce_field = 'verification-code-categories';
    $action      = 'comcal_edit_categories';
    $valid_nonce = isset( $_POST[ $nonce_field ] ) && wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ),
        $action
    );
    if ( ! $valid_nonce ) {
        echo 'You targeted the right function, but sorry, your nonce did not verify.';
        wp_die(
            'You targeted the right function, but sorry, your nonce did not verify.',
            'Error in submission',
            array( 'response' => 500 )
        );
    } else {
        $res = comcal_process_edit_categories( $_POST );
        if ( 200 === $res[0] ) {
            wp_die( $res[1], 'Success', array( 'response' => $res[0] ) );
        } else {
            wp_die( $res[1], 'Error', array( 'response' => $res[0] ) );
        }
    }
}
add_action( 'wp_ajax_comcal_edit_categories', 'comcal_submit_edit_categories_func' );

/**
 * Validates and processes data from the edit categories form.
 *
 * @param array $post The posted data.
 * @return array with two elements:
 *               [0]: int    result
 *               [1]: string Message that describes what was changed.
 */
function comcal_process_edit_categories( $post ) {
    $messages = array();
    $result   = 200;

    // Create new category.
    if ( isset( $post['name_new'] ) && ! empty( trim( $post['name_new'] ) ) ) {
        $c = Comcal_Category::create( $post['name_new'] );
        if ( $c->store() ) {
            $messages[] = "Neue Kategorie '{$c->get_field('name')}' mit ID {$c->get_field('categoryId')} angelegt";
        } else {
            $messages[] = "Konnte Kategorie '{$post['name']}' nicht anlegen.";
        }
    }

    // Modify existing category.
    $index = 0;
    while ( true ) {
        $suffix = "_$index";
        if ( ! isset( $post[ "name$suffix" ] ) ) {
            // No more category data in the form.
            break;
        }
        $delete      = isset( $post[ "delete$suffix" ] );
        $category_id = $post[ "categoryId$suffix" ];
        $name        = $post[ "name$suffix" ];
        $style       = $post[ "style$suffix" ] ?? false;
        $c           = Comcal_Category::query_from_category_id( $category_id );
        if ( $delete ) {
            if ( $c->delete() ) {
                $messages[] = "Kategorie '$name' wurde gelöscht!";
            } else {
                $messages[] = "Kategorie '$name' konnte nicht gelöscht werden!";
                $result     = 500;
            }
        } else {
            $old_name      = $c->get_field( 'name' );
            $changed_name  = $c->set_field( 'name', $name );
            $changed_style = (bool) $style && $c->set_field( 'style', $style );
            if ( $changed_name || $changed_style ) {
                if ( $c->store() ) {
                    if ( $changed_name ) {
                        $messages[] = "Kategorie umbenannt: '$old_name' -> $name'";
                    }
                    if ( $changed_style ) {
                        $messages[] = "Stil von Kategorie $name angepasst: $style";
                    }
                } else {
                    $messages[] = "Kategorie '$old_name' konnte aktualisiert werden!";
                    $result     = 500;
                }
            }
        }
        $index++;
    }
    if ( empty( $messages ) ) {
        $messages[] = 'Keine Aktion';
    }
    return array( $result, implode( '<br>', $messages ) );
}
