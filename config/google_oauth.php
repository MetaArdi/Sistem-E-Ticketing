<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Replace these with your actual Google OAuth 2.0 Client ID and Secret
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');

// Redirect URI (Must match the one configured in Google Cloud Console)
define('GOOGLE_REDIRECT_URI', 'http://localhost/Halo_Tiket/auth/google_callback.php');

// Initialize Google Client
$google_client = new Google\Client();
$google_client->setClientId(GOOGLE_CLIENT_ID);
$google_client->setClientSecret(GOOGLE_CLIENT_SECRET);
$google_client->setRedirectUri(GOOGLE_REDIRECT_URI);
$google_client->addScope("email");
$google_client->addScope("profile");
?>
