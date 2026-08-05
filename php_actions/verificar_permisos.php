<?php
function puede_añadir_datasets() {
    if (!isset($_SESSION['user_uuid'])) {
        return false;
    }
    
    // Admin siempre puede, o si contributes = 1
    $es_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';
    $contribuye = isset($_SESSION['contributes']) && $_SESSION['contributes'] == 1;
    
    return ($es_admin || $contribuye);
}

function solo_admin_o_contributor() {
    if (!puede_añadir_datasets()) {
        header("Location: index.php");
        exit();
    }
}
?>
