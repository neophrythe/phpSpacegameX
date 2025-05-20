<?php $this->layout('layout.game_layout', ['title' => $pageTitle ?? 'View Message']); ?>

<div class="container messages-container">
    <h1><?php echo htmlspecialchars($pageTitle ?? 'View Message'); ?></h1>

    <?php $this->insert('game._messages_navigation', ['activeTab' => $activeTab, 'unreadCount' => $unreadCount ?? 0]); ?>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <?php if (isset($message) && $message): ?>
        <div class="message-view">
            <div class="message-header">
                <p><strong>Subject:</strong> <?php echo htmlspecialchars($message->subject); ?></p>
                <p>
                    <strong>From:</strong> 
                    <?php if ($message->sender_id == 0 || $message->sender_id === null): ?>
                        System
                    <?php else: ?>
                        <?php echo htmlspecialchars($message->sender_username ?? 'Unknown User'); ?>
                    <?php endif; ?>
                </p>
                <?php if ($message->sender_id != $_SESSION['user_id']): // Only show recipient if current user is not the sender ?>
                <p><strong>To:</strong> <?php echo htmlspecialchars($message->recipient_username ?? 'N/A'); ?></p>
                <?php endif; ?>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($message->sent_at))); ?></p>
            </div>
            <hr>
            <div class="message-content">
                <?php echo nl2br(htmlspecialchars($message->content)); ?>
            </div>
            <hr>
            <div class="message-actions">
                <?php if ($message->player_id == $_SESSION['user_id']): // If current user is the recipient ?>
                    <a href="/messages/compose?reply_to=<?php echo $message->id; ?>" class="btn btn-primary">Reply</a>
                    <form method="post" action="/messages/delete" style="display: inline-block;">
                        <input type="hidden" name="message_id" value="<?php echo $message->id; ?>">
                        <input type="hidden" name="from_tab" value="inbox">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this message?');">Delete</button>
                    </form>
                <?php elseif ($message->sender_id == $_SESSION['user_id']): // If current user is the sender ?>
                     <a href="/messages/compose?recipient_name=<?php echo urlencode($message->recipient_username ?? ''); ?>&reply_to=<?php echo $message->id; ?>" class="btn btn-primary">Reply to Recipient</a>
                    <form method="post" action="/messages/delete" style="display: inline-block;">
                        <input type="hidden" name="message_id" value="<?php echo $message->id; ?>">
                        <input type="hidden" name="from_tab" value="sent">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to remove this message from your sent items? The recipient will still be able to see it.');">Remove from Sent</button>
                    </form>
                <?php endif; ?>
                <a href="/messages<?php echo ($activeTab == 'sent') ? '/sent' : ''; ?>" class="btn btn-secondary">Back to <?php echo ($activeTab == 'sent') ? 'Sent' : 'Inbox'; ?></a>
            </div>
        </div>
    <?php else: ?>
        <p>Message not found or you do not have permission to view it.</p>
        <a href="/messages" class="btn btn-primary">Back to Inbox</a>
    <?php endif; ?>
</div>

<style>
    .messages-container {
        /* Add any specific styling for the messages container if needed */
    }
    .message-view {
        border: 1px solid #ddd;
        padding: 20px;
        border-radius: 5px;
        background-color: #f9f9f9;
    }
    .message-header p {
        margin-bottom: 5px;
    }
    .message-content {
        white-space: pre-wrap; /* Preserve line breaks and spacing */
        word-wrap: break-word;
        padding: 15px 0;
    }
    .message-actions .btn {
        margin-right: 10px;
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
