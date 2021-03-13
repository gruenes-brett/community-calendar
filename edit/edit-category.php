<?php

function _comcal_editCategoryForm($category, $index) {
    $suffix = "_$index";
    $name = $category->get_field('name');
    $categoryId = $category->get_field('categoryId');
    return <<<XML
    <div class="form-group">
        <input name="categoryId$suffix" id="categoryId$suffix" value="$categoryId" type="hidden">
        <label for="categoryName$suffix">Name</label>
        <input type="text" class="form-control" name="name$suffix" id="categoryName$suffix" placeholder="" value="$name" required>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="delete$suffix" id="categoryDelete$suffix" value="$categoryId" unchecked>
            <label class="form-check-label" for="categoryDelete$suffix">Kategorie löschen?</label>
        </div>
    </div>
XML;
}

function _comcal_allCategoryForms() {
    $all = comcal_Category::get_all();
    $out = '';
    $index = 0;
    foreach ($all as $c) {
        $out .= _comcal_editCategoryForm($c, $index);
        $index++;
    }
    return $out;
}

function comcal_getEditCategoriesDialog() {
    $postUrl = admin_url('admin-ajax.php');
    $nonceField = wp_nonce_field('comcal_edit_categories','verification-code-categories', true, false);
    $allForms = _comcal_allCategoryForms();
    return <<<XML
    <div class="comcal-modal-wrapper edit-cats-dialog">
        <div class="comcal-close">X</div>
        <div class="form-popup" id="editCategories">
            <h2>Kategorien bearbeiten</h2>
            <form id="editCategories" action="$postUrl" method="post">
                <fieldset>
                    <input name="action" value="comcal_edit_categories" type="hidden">
                    $nonceField
                    $allForms

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


function comcal_submitEditCategories_func() {
    if ( empty($_POST) || !wp_verify_nonce($_POST['verification-code-categories'], 'comcal_edit_categories') ) {
        echo 'You targeted the right function, but sorry, your nonce did not verify.';
        wp_die('You targeted the right function, but sorry, your nonce did not verify.', 'Error in submission',
            array('response' => 500));
    } else {
        $res = comcal_processEditCategories($_POST);
        if ($res[0] == 200) {
            wp_die($res[1], 'Success', array('response' => $res[0]));
        } else {
            wp_die($res[1], 'Error', array('response' => $res[0]));
        }
    }
}
add_action('wp_ajax_comcal_edit_categories', 'comcal_submitEditCategories_func');


function comcal_processEditCategories($post) {
    $messages = array();
    $result = 200;

    // Create new
    if (isset($post['name_new']) && !empty(trim($post['name_new']))) {
        $c = comcal_Category::create($post['name_new']);
        if ($c->store()) {
            $messages[] = "Neue Kategorie '{$c->get_field('name')}' mit ID {$c->get_field('categoryId')} angelegt";
        } else {
            $messages[] = "Konnte Kategorie '{$post['name']}' nicht anlegen.";
        }
    }

    // Modify existing
    $index = 0;
    while (true) {
        $suffix = "_$index";
        if (!isset($post["name$suffix"])) {
            break;
        }
        $delete = isset($post["delete$suffix"]);
        $categoryId = $post["categoryId$suffix"];
        $name = $post["name$suffix"];
        $c = comcal_Category::query_from_category_id($categoryId);
        if ($delete) {
            if ($c->delete()) {
                $messages[] = "Kategorie '$name' wurde gelöscht!";
            } else {
                $messages[] = "Kategorie '$name' konnte nicht gelöscht werden!";
                $result = 500;
            }
        } else {
            $oldName = $c->get_field('name');
            if ($c->set_field('name', $name)) {
                if ($c->store()) {
                    $messages[] = "Kategorie umbenannt: '$oldName' -> $name'";
                } else {
                    $messages[] = "Kategorie '$oldName' konnte nicht in '$name' umbenannt werden!";
                    $result = 500;
                }
            }
        }
        $index++;
    }
    if (empty($messages)) {
        $messages[] = 'Keine Aktion';
    }
    return array($result, implode('<br>', $messages));
}