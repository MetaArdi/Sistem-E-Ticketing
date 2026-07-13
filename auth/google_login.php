<?php
session_start();
require_once '../config/google_oauth.php';

// Generate a URL to request access from Google's OAuth 2.0 server
$auth_url = $google_client->createAuthUrl();

// Redirect the user to Google's OAuth 2.0 server
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit();
?>
