<?php
session_start();

function isLoggedIn(){
    return isset($_SESSION['user_id']);
}

function requireLogin(){
    if(!isLoggedIn()){
        header("location: ../public/login.php");
        exit();
    }
}

function currentUser(){
    return $_SESSION['user'] ?? null;
}

function require_role(array $roles){
    if(!isset($_SESSION['role'])){
        header("location: ../public/login.php");
        exit();

    }   
    $userRole = strtolower(trim($_SESSION['role']));

    if (!in_array($userRole, $roles)) {
        // Unauthorized
        http_response_code(403);
        echo "Access denied";
        exit;
    }
}

?>