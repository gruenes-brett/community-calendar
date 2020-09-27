<?php

function _evtcal_editCategoryForm($category, $index) {
    $suffix = "_$index";
    $name = $category->getField('name');
    $categoryId = $category->getField('categoryId');
    return <<<XML
    <div class="form-group">
        <input name="categoryId$suffix" id="categoryId" value="$categoryId" type="hidden">
        <label for="categoryName">Name</label>
        <input type="text" class="form-control" name="name$suffix" id="categoryName" placeholder="" value="$name" required>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="delete$suffix" id="categoryDelete" value="$categoryId" unchecked>
            <label class="form-check-label" for="categoryDelete">Kategorie löschen?</label>
        </div>
    </div>
XML;
}

function _evtcal_allCategoryForms() {
    $all = evtcal_Category::getAll();
    $out = '';
    $index = 0;
    foreach ($all as $c) {
        $out .= _evtcal_editCategoryForm($c, $index);
        $index++;
    }
    return $out;
}

function evtcal_getEditCategoriesDialog() {
    $adminPost = admin_url('admin-post.php');
    $nonceField = wp_nonce_field('evtcal_edit_categories','verification-code', true, false);
    $allForms = _evtcal_allCategoryForms();
    return <<<XML
    <div class="evtcal-modal-wrapper edit-cats-dialog">
        <div class="close">X</div>
        <div class="form-popup" id="editCategories">
            <h2>Kategorien bearbeiten</h2>
            <form id="editCategories" action="$adminPost" method="post">
                <fieldset>
                    <input name="action" value="evtcal_edit_categories" type="hidden">
                    $nonceField
                    $allForms

                    <div class="form-group">
                        <label for="categoryName">Neue Kategorie</label>
                        <input type="text" class="form-control" name="name_new" id="categoryName_new" placeholder="" value="">
                    </div>
                    <div class="btn-group">
                        <input type="button" class="btn btn-secondary" id="cancel" value="Zurück">
                        <input type="submit" class="btn btn-success" id="send" value="Senden">
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
XML;
}


function evtcal_submitEditCategories_func() {
    if ( empty($_POST) || !wp_verify_nonce($_POST['verification-code'], 'evtcal_edit_categories') ) {
        echo 'You targeted the right function, but sorry, your nonce did not verify.';
        wp_die('You targeted the right function, but sorry, your nonce did not verify.', 'Error in submission',
            array('response' => 500));
    } else {
        $res = evtcal_processEditCategories($_POST);
        if ($res[0] == 200) {
            wp_die($res[1], 'Datenübertragung erfolgreich');
        } else {
            wp_die($res[1], 'Fehler', array('response' => $res[0]));
        }
    }
}
add_action('admin_post_evtcal_edit_categories', 'evtcal_submitEditCategories_func');


function evtcal_processEditCategories($post) {
    $messages = array();
    $result = 200;

    // Create new
    if (isset($post['name_new']) && !empty(trim($post['name_new']))) {
        $c = evtcal_Category::create($post['name_new']);
        if ($c->store()) {
            $messages[] = "Neue Kategorie '{$c->getField('name')}' mit ID {$c->getField('categoryId')} angelegt";
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
        $c = evtcal_Category::queryFromCategoryId($categoryId);
        if ($delete) {
            if ($c->delete()) {
                $messages[] = "Kategorie '$name' wurde gelöscht!";
            } else {
                $messages[] = "Kategorie '$name' konnte nicht gelöscht werden!";
                $result = 500;
            }
        } else {
            $oldName = $c->getField('name');
            if ($c->setField('name', $name)) {
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