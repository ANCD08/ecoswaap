<?php include 'header.php'; ?>

<main class="container">
    <h1>Contact Us</h1>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
        <div class="card" style="padding: 20px;">
            <h2>Get in Touch</h2>
            <form method="POST" action="send_contact.php">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" class="form-control" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn">Send Message</button>
            </form>
        </div>

        <div class="card" style="padding: 20px;">
            <h2>Our Information</h2>
            <p><strong>Email:</strong> support@ecoswap.com</p>
            <p><strong>Phone:</strong> +1 (555) 123-4567</p>
            <p><strong>Address:</strong> 123 Green St, Sustainability City</p>
            
            <h3 style="margin-top: 20px;">Support Hours</h3>
            <p>Monday-Friday: 9AM - 5PM</p>
            <p>Weekends: Closed</p>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>