<?php
require_once __DIR__ . '/../auth/auth.php';

function requireRole(array $role){
    if(!isLoggedIn() || !in_array($_SESSION['role'], $role)){
        http_response_code(403);
        die("Access denied.");
    }
}



?>