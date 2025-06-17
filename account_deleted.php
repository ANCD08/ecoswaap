<?php
// account_deleted.php
session_start();
if (isset($_SESSION['user_id'])) {
    // If somehow session still exists
    session_unset();
    session_destroy();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Deleted</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .message { background-color: #f8f9fa; padding: 20px; border-radius: 5px; display: inline-block; }
    </style>
</head>
<body>
    <div class="message">
        <h2>Account Successfully Deleted</h2>
        <p>We're sorry to see you go. All your data has been permanently removed from our systems.</p>
        <p><a href="index.php">Return to homepage</a></p>
    </div>
</body>
</html>