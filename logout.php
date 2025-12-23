<?php


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if(session_destroy()) // Destroying All Sessions
{
header("Location: login.php"); // Redirecting To Home Page
}
?>