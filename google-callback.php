<?php
// 1. Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'vendor/autoload.php';
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';



$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

/**
 * CRITICAL FOR XAMPP:
 * This line bypasses SSL certificate verification. Without this, XAMPP 
 * often cannot talk to Google's servers, resulting in "Unauthorized".
 */
$client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

// --- AUTHENTICATION LOGIC ---
if (isset($_GET['code'])) {
    // Attempt to exchange the code for an access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    // Check for errors in the token response
    if (isset($token['error'])) {
        echo "<h1>Google Authentication Error</h1>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($token['error']) . "</p>";
        echo "<p><strong>Description:</strong> " . htmlspecialchars($token['error_description']) . "</p>";
        echo "<hr><p><em>Troubleshooting Tip: Make sure your Gmail is added to the 'Test Users' list in the Google Cloud Console.</em></p>";
        exit;
    }

    $client->setAccessToken($token);

    // Get user profile info
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    
    $email = $google_account_info->email;
    $first_name = $google_account_info->givenName;
    $last_name = $google_account_info->familyName;

    // --- DATABASE LOGIC ---
    try {
        $stmt = $pdo->prepare("SELECT * FROM customer WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Register new user (Note: Ensure password column allows NULL)
            $stmt = $pdo->prepare("INSERT INTO customer (email, first_name, last_name, is_admin) VALUES (?, ?, ?, 0)");
            $stmt->execute([$email, $first_name, $last_name]);
            
            $stmt = $pdo->prepare("SELECT * FROM customer WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        }

        // Set Session variables
        $_SESSION['user_id'] = $user['customer_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['is_admin'] = $user['is_admin'];

        // Redirect to protected home page
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }

} else {
    // If someone tries to access this file directly without a 'code' from Google
    header("Location: login.php");
    exit();
}