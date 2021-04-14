<?php
/**
 * Defines the edit category form
 *
 * @package Community_Calendar
 */

/**
 * Defines the add/edit category form fields and layout.
 */
class Comcal_Edit_Categories_Form extends Comcal_Form {
    /**
     * Specifies the nonce name.
     *
     * @var string
     */
    protected static $nonce_name = 'verify-edit-categories';
    /**
     * Specifies the action name.
     *
     * @var string
     */
    protected static $action_name = 'comcal_submit_categories_data';

    /**
     * Map that defines what form field name corresponds to which field name
     * in the Comcal_Event object.
     *
     * @var array
     */
    protected static $form_field_to_model_field = array();

    /**
     * Categories for which to display the form.
     *
     * @var Comcal_Category[]
     */
    protected $categories;

    public function __construct( $categories ) {
        $this->categories = $categories;
    }

    protected function get_form_id(): string {
        return 'edit-categories-form';
    }

    protected function get_html_before_form(): string {
        return '';
    }

    protected function get_html_after_form(): string {
        // "hack" to make sure this form can be submitted via AJAX, as
        // defined in forms.js
        $id = $this->get_form_id();
        return <<<XML
          <script>
            jQuery(document).ready(function(){
                register_form_ajax_submission('#$id');
            });
          </script>
XML;

    }

    protected function get_form_fields(): string {
        $forms = '';
        $index = 0;
        foreach ( $this->categories as $cat ) {
            $forms .= $this->get_category_form( $cat, $index );
            $index++;
        }
        $add_category_form = $this->get_add_category_form();
        return <<<XML
            <h2>Kategorien bearbeiten</h2>
            $forms
            $add_category_form

            <button type="submit">Kategorien aktualisieren</button>
XML;
    }

    protected function get_category_form( Comcal_Category $category, int $index ) {
        $suffix      = "_$index";
        $name        = $category->get_field( 'name' );
        $category_id = $category->get_field( 'categoryId' );
        $style_form  = $this->get_style_form( $category, $suffix );
        return <<<XML
            <input name="categoryId$suffix" id="categoryId$suffix" value="$category_id" type="hidden">

            <label for="categoryName$suffix">Name</label>
            <input type="text" name="name$suffix" id="categoryName$suffix" placeholder="" value="$name" required>
            $style_form

            <div class="formgroup">
                <div class="row">
                    <input type="checkbox" name="delete$suffix" id="categoryDelete$suffix" value="$category_id" unchecked>
                    <label for="categoryDelete$suffix">Kategorie löschen?</label>
                </div>
            </div>
            <br>
XML;
    }

    protected function get_style_form( $category, string $suffix ) {
        $style = $category->get_field( 'style' );
        if ( ! $style ) {
            $style = $this->generate_style( $category->get_field( 'name' ) );
        }
        return <<<XML
            <label for="categoryStyle$suffix">Farbe</label>
            <input type="text" name="style$suffix" id="categoryStyle$suffix" placeholder="z.B. '#309000, white' (Hintergrundfarbe, Textfarbe)" value="$style">
XML;
    }

    protected function get_add_category_form() {
        $suffix = '_new';
        return <<<XML
            <input type="text" name="name$suffix" id="categoryName$suffix" placeholder="Hier neuen Kategorienamen eintragen" value="">
XML;
    }

    protected static function process_data( $post_data ) : array {
        return static::process_categories_data( $post_data );
    }

    protected static function process_categories_data( $post_data ) {
        $messages = array();
        $result   = 200;

        // Create new category.
        if ( isset( $post_data['name_new'] ) && ! empty( trim( $post_data['name_new'] ) ) ) {
            $new_style = static::generate_style( $post_data['name_new'] );
            $c         = Comcal_Category::create( $post_data['name_new'], $new_style );
            if ( $c->store() ) {
                $messages[] = "Neue Kategorie '{$c->get_field('name')}' mit ID {$c->get_field('categoryId')} angelegt";
            } else {
                $messages[] = "Konnte Kategorie '{$post_data['name']}' nicht anlegen.";
            }
        }

        // Modify existing category.
        $index = 0;
        while ( true ) {
            $suffix = "_$index";
            if ( ! isset( $post_data[ "name$suffix" ] ) ) {
                // No more category data in the form.
                break;
            }
            $delete      = isset( $post_data[ "delete$suffix" ] );
            $category_id = $post_data[ "categoryId$suffix" ];
            $name        = $post_data[ "name$suffix" ];
            $style       = $post_data[ "style$suffix" ];
            $c           = Comcal_Category::query_from_category_id( $category_id );
            if ( $delete ) {
                if ( $c->delete() ) {
                    $messages[] = "Kategorie '$name' wurde gelöscht!";
                } else {
                    $messages[] = "Kategorie '$name' konnte nicht gelöscht werden!";
                    $result     = 500;
                }
            } else {
                $validated_style = static::validate_style( $style );
                if ( false === $validated_style ) {
                    $result     = 500;
                    $messages[] = "Ungültiger Stil '$style' für Kategorie $name!";
                } else {
                    $old_name      = $c->get_field( 'name' );
                    $changed_name  = $c->set_field( 'name', $name );
                    $changed_style = $c->set_field( 'style', $validated_style );
                    if ( $changed_name || $changed_style ) {
                        if ( $c->store() ) {
                            if ( $changed_name ) {
                                $messages[] = "Kategorie umbenannt: '$old_name' -> $name'";
                            }
                            if ( $changed_style ) {
                                $messages[] = "Stil von Kategorie $name angepasst: $validated_style";
                            }
                        } else {
                            $messages[] = "Kategorie '$old_name' konnte aktualisiert werden!";
                            $result     = 500;
                        }
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

    protected static function validate_style( string $style ) {
        $background = strtok( $style, ',' );
        if ( false === $background ) {
            // empty string.
            return false;
        }
        $foreground = strtok( ',' );
        if ( false === $foreground ) {
            // foreground not defined.
            return false;
        }
        $false = strtok( ',' );
        if ( false !== $false ) {
            // expecting exactly one comma.
            return false;
        }
        $background = trim( $background );
        $foreground = trim( $foreground );
        return "$background,$foreground";
    }

    protected static function generate_style( string $name ) {
        list(
          $background,
          $foreground
          ) = comcal_create_unique_colors( $name );
        return "$background,$foreground";
    }

}

Comcal_Edit_Categories_Form::register_form();
