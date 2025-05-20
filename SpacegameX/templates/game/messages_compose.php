<?php $this->layout('layout.game_layout', ['title' => $pageTitle ?? 'Compose Message']); ?>

<div class="container messages-container">
    <h1><?php echo htmlspecialchars($pageTitle ?? 'Compose Message'); ?></h1>

    <?php $this->insert('game._messages_navigation', ['activeTab' => $activeTab, 'unreadCount' => $unreadCount ?? 0]); ?>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <form method="post" action="/messages/send" class="message-compose-form">
        <div class="form-group mb-3">
            <label for="recipient_name">To (Username):</label>
            <input type="text" class="form-control <?php if (isset($recipientName) && !empty($recipientName) && !($recipientExists ?? true)) echo 'is-invalid'; ?>" id="recipient_name" name="recipient_name" value="<?php echo htmlspecialchars($recipientName ?? ''); ?>" required>
            <?php if (isset($recipientName) && !empty($recipientName) && !($recipientExists ?? true)): ?>
                <div class="invalid-feedback">
                    Recipient not found.
                </div>
            <?php endif; ?>
        </div>

        <div class="form-group mb-3">
            <label for="subject">Subject:</label>
            <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($subject ?? 'No Subject'); ?>" required>
        </div>

        <div class="form-group mb-3">
            <label for="content">Message:</label>
            <textarea class="form-control" id="content" name="content" rows="10" required><?php echo htmlspecialchars($content ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Send Message</button>
        <a href="/messages" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<style>
    .messages-container {
        /* Add any specific styling for the messages container if needed */
    }
    .message-compose-form .form-group {
        margin-bottom: 1rem;
    }
    .message-nav ul {
        list-style-type: none;
        padding: 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #ddd;
    }
    .message-nav li {
        display: inline-block;
        margin-right: 10px;
        padding: 10px 15px;
        border: 1px solid transparent;
        border-bottom: none;
    }
    .message-nav li.active {
        border-color: #ddd;
        border-bottom: 1px solid white; /* Or background color of content area */
        background-color: white; /* Or background color of content area */
        border-radius: 5px 5px 0 0;
    }
    .message-nav li a {
        text-decoration: none;
        color: #337ab7;
    }
    .message-nav li.active a {
        color: #333;
        font-weight: bold;
    }
</style>
