// Custom authentication logic for frontend
// Redirect to login.html if not logged in
(function() {
    if (!localStorage.getItem('user')) {
        window.location.href = 'login.html';
    } else {
        try {
            const user = JSON.parse(localStorage.getItem('user'));
            if (!user || !user.isLoggedIn) {
                window.location.href = 'login.html';
            }
        } catch (e) {
            window.location.href = 'login.html';
        }
    }
})();

// ...existing code...