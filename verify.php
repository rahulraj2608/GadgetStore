<?php
require_once 'includes/db_connect.php';

if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];

    // 1. Look for a matching row in your verification table
    $stmt = $pdo->prepare("SELECT * FROM verification WHERE email = ? AND token = ?");
    $stmt->execute([$email, $token]);
    $entry = $stmt->fetch();

    if ($entry) {
        // 2. Token is valid! Update the customer table to is_verified = 1
        $update = $pdo->prepare("UPDATE customer SET is_verified = 1 WHERE email = ?");
        
        if ($update->execute([$email])) {
            // 3. Cleanup: Remove the token from the verification table so it can't be used again
            $delete = $pdo->prepare("DELETE FROM verification WHERE email = ?");
            $delete->execute([$email]);

            header("Location: login.php?status=verified");
            exit();
        }
    }
}

// If something went wrong
header("Location: login.php?status=error");
exit();