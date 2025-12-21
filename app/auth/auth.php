<?php
session_start();

function isLoggedIn(){
    return isset($_SESSION['user_id']);
}

function requireLogin(){
    if(!isLoggedIn()){
        header("location: /login.php");
        exit();
    }
}

function currentUser(){
    return $_SESSION['user'] ?? null;
}



?>