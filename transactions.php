<?php
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get transaction ID from URL if specified
$transactionId = $_GET['id'] ?? null;

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_swap'])) {
    $rating = $_POST['rating'];
    $transactionId = $_POST['transaction_id'];
    
    // Validate rating
    if ($rating >= 1 && $rating <= 5) {
        // Verify user can rate this transaction
        $stmt = $pdo->prepare("
            SELECT 1 FROM transactions t
            JOIN swap_requests sr ON t.swap_request_id = sr.request_id
            JOIN items ri ON sr.requested_item_id = ri.item_id
            WHERE t.transaction_id = ? AND 
                  (sr.requester_id = ? OR ri.user_id = ?) AND
                  ((sr.requester_id = ? AND t.requester_rating IS NULL) OR 
                   (ri.user_id = ? AND t.requestee_rating IS NULL))
        ");
        $stmt->execute([
            $transactionId,
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id']
        ]);
        
        if ($stmt->fetch()) {
            $pdo->beginTransaction();
            try {
                // Determine which rating to update
                $stmt = $pdo->prepare("
                    SELECT sr.requester_id, ri.user_id as requestee_id
                    FROM swap_requests sr
                    JOIN items ri ON sr.requested_item_id = ri.item_id
                    WHERE sr.request_id IN (
                        SELECT swap_request_id FROM transactions WHERE transaction_id = ?
                    )
                ");
                $stmt->execute([$transactionId]);
                $swap = $stmt->fetch();
                
                if ($_SESSION['user_id'] == $swap['requester_id']) {
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET requester_rating = ?
                        WHERE transaction_id = ?
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET requestee_rating = ?
                        WHERE transaction_id = ?
                    ");
                }
                $stmt->execute([$rating, $transactionId]);
                
                // Update user's average rating
                $ratedUserId = ($_SESSION['user_id'] == $swap['requester_id']) ? $swap['requestee_id'] : $swap['requester_id'];
                
                $stmt = $pdo->prepare("
                    UPDATE users u
                    SET u.rating = (
                        SELECT AVG(
                            CASE 
                                WHEN t.requestee_rating IS NOT NULL AND sr.requester_id = u.user_id THEN t.requestee_rating
                                WHEN t.requester_rating IS NOT NULL AND ri.user_id = u.user_id THEN t.requester_rating
                                ELSE NULL
                            END
                        )
                        FROM transactions t
                        JOIN swap_requests sr ON t.swap_request_id = sr.request_id
                        JOIN items ri ON sr.requested_item_id = ri.item_id
                        WHERE (sr.requester_id = u.user_id OR ri.user_id = u.user_id)
                    )
                    WHERE u.user_id = ?
                ");
                $stmt->execute([$ratedUserId]);
                
                $pdo->commit();
                $_SESSION['success'] = "Rating submitted successfully!";
                header("Location: transactions.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to submit rating: " . $e->getMessage();
            }
        } else {
            $error = "You cannot rate this transaction";
        }
    } else {
        $error = "Please provide a valid rating (1-5 stars)";
    }
}

// Fetch user's transactions
$stmt = $pdo->prepare("
    SELECT t.*, sr.request_id,
           ri.title as requested_item_title,
           oi.title as offered_item_title,
           CASE 
               WHEN sr.requester_id = ? THEN u2.username
               ELSE u1.username
           END as other_user
    FROM transactions t
    JOIN swap_requests sr ON t.swap_request_id = sr.request_id
    JOIN items ri ON sr.requested_item_id = ri.item_id
    JOIN items oi ON sr.offered_item_id = oi.item_id
    JOIN users u1 ON ri.user_id = u1.user_id
    JOIN users u2 ON oi.user_id = u2.user_id
    WHERE sr.requester_id = ? OR ri.user_id = ?
    ORDER BY t.completed_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - EcoSwap</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h1>Your Swap History</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (empty($transactions)): ?>
            <div class="card" style="padding: 30px; text-align: center;">
                <h3>No transactions yet</h3>
                <p>Your completed swaps will appear here</p>
                <a href="browse.php" class="btn">Browse Items</a>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 20px; margin-top: 20px;">
                <?php foreach ($transactions as $transaction): ?>
                    <div class="card" style="padding: 20px;">
                        <div style="display: flex; justify-content: space-between;">
                            <h3>Swap with <?= htmlspecialchars($transaction['other_user']) ?></h3>
                            <span><?= date('M j, Y', strtotime($transaction['completed_at'])) ?></span>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                            <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                <h4>Your Item</h4>
                                <p><?= htmlspecialchars($transaction['offered_item_title']) ?></p>
                            </div>
                            <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                <h4>Their Item</h4>
                                <p><?= htmlspecialchars($transaction['requested_item_title']) ?></p>
                            </div>
                        </div>
                        
                        <?php 
                        // Check if user needs to rate this transaction
                        $stmt = $pdo->prepare("
                            SELECT sr.requester_id, ri.user_id as requestee_id
                            FROM swap_requests sr
                            JOIN items ri ON sr.requested_item_id = ri.item_id
                            WHERE sr.request_id = ?
                        ");
                        $stmt->execute([$transaction['swap_request_id']]);
                        $swap = $stmt->fetch();
                        
                        $canRate = false;
                        $ratingField = null;
                        
                        if ($_SESSION['user_id'] == $swap['requester_id'] && empty($transaction['requester_rating'])) {
                            $canRate = true;
                            $ratingField = 'requester_rating';
                        } elseif ($_SESSION['user_id'] == $swap['requestee_id'] && empty($transaction['requestee_rating'])) {
                            $canRate = true;
                            $ratingField = 'requestee_rating';
                        }
                        ?>
                        
                        <?php if ($canRate): ?>
                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                                <h4>Rate This Swap</h4>
                                <form method="POST">
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star" data-rating="<?= $i ?>"><?= $i <= 3 ? '★' : '☆' ?></span>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" name="rating" id="ratingInput" value="3">
                                        <input type="hidden" name="transaction_id" value="<?= $transaction['transaction_id'] ?>">
                                        <button type="submit" name="rate_swap" class="btn">Submit Rating</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 15px; display: flex; justify-content: space-between;">
                                <div>
                                    <?php if ($_SESSION['user_id'] == $swap['requester_id'] && isset($transaction['requester_rating'])): ?>
                                        <p>Your rating: <?= str_repeat('★', $transaction['requester_rating']) . str_repeat('☆', 5 - $transaction['requester_rating']) ?></p>
                                    <?php elseif ($_SESSION['user_id'] == $swap['requestee_id'] && isset($transaction['requestee_rating'])): ?>
                                        <p>Your rating: <?= str_repeat('★', $transaction['requestee_rating']) . str_repeat('☆', 5 - $transaction['requestee_rating']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <a href="messages.php?request=<?= $transaction['swap_request_id'] ?>" class="btn btn-outline">View Conversation</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    
    <script>
    // Star rating functionality
    document.addEventListener('DOMContentLoaded', function() {
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('ratingInput');
        
        if (stars.length && ratingInput) {
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.getAttribute('data-rating');
                    ratingInput.value = rating;
                    
                    // Update star display
                    stars.forEach((s, index) => {
                        s.textContent = index < rating ? '★' : '☆';
                    });
                });
            });
        }
    });
    </script>
</body>
</html>