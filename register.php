<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/mail_helper.php'; 

$errors = [];
$success = false;
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name  = sanitize($_POST['last_name']);
    $email      = sanitize($_POST['email']);
    $password   = $_POST['password'];
    $confirm    = $_POST['confirm_password'];
    $phone      = sanitize($_POST['phone']);
    $address    = sanitize($_POST['address']);

    // 1. Validation
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name))  $errors[] = "Last name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm) $errors[] = "Passwords do not match";

    // 2. Check if email exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email already registered";
    }

    // 3. Process Registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(16)); 

        try {
            $pdo->beginTransaction();

            // A. Insert main customer data
            $sqlCustomer = "INSERT INTO customer (first_name, last_name, email, password, phone_number, address, is_admin, is_verified) 
                            VALUES (?, ?, ?, ?, ?, ?, 0, 0)";
            $stmtCust = $pdo->prepare($sqlCustomer);
            $stmtCust->execute([$first_name, $last_name, $email, $hashed_password, $phone, $address]);

            // B. Insert into your 'verification' table
            $sqlVerify = "INSERT INTO verification (email, token) VALUES (?, ?)";
            $stmtVerify = $pdo->prepare($sqlVerify);
            $stmtVerify->execute([$email, $token]);

            $pdo->commit();

            // 4. Send Mail (Only after successful DB commit)
            $verify_link = "http://" . $_SERVER['HTTP_HOST'] . "/ge/verify.php?token=" . $token . "&email=" . urlencode($email);
            
            $subject = "Verify your account - Gadget Store";
            $body = "
                <div style='font-family: Arial, sans-serif; padding: 20px;'>
                    <h2>Welcome, $first_name!</h2>
                    <p>Click the button below to verify your account and start shopping:</p>
                    <a href='$verify_link' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify My Account</a>
                </div>";

            $mailResult = sendCustomMail($email, $subject, $body);

            $success = true;
            $success_message = "Registration successful! We've sent a verification link to your email.";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "System error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Gadget Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="text-center mb-0">Create Account</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?> <br>
                                <a href="login.php" class="alert-link">Go to Login</a>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $error): ?>
                                        <p class="mb-0">• <?php echo htmlspecialchars($error); ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Password *</label>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm Password *</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Register</button>
                                    <a href="login.php" class="btn btn-outline-secondary">Already have an account? Login</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>