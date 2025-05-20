<?php $this->layout('layout.game_layout', ['title' => $pageTitle ?? 'Sent Messages']); ?>

<div class="container messages-container">
    <h1><?php echo htmlspecialchars($pageTitle ?? 'Sent Messages'); ?></h1>

    <?php $this->insert('game._messages_navigation', ['activeTab' => $activeTab, 'unreadCount' => $unreadCount ?? 0]); ?>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="messages-list-container">
        <?php if (empty($messages)): ?>
            <p>You have no sent messages.</p>
        <?php else: ?>
            <form id="sentMessagesForm" method="post" action="/messages/delete">
                <input type="hidden" name="from_tab" value="sent">
                <table class="messages-table table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>To</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $message): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($message->recipient_username ?? 'N/A'); ?></td>
                                <td>
                                    <a href="/messages/view/<?php echo $message->id; ?>">
                                        <?php echo htmlspecialchars($message->subject); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($message->sent_at))); ?></td>
                                <td>
                                    <button type="submit" name="message_id" value="<?php echo $message->id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to remove this message from your sent items? The recipient will still be able to see it.');">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                <a class="page-link" href="/messages/sent?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <a href="/messages/compose" class="btn btn-primary">Compose New Message</a>
</div>

<style>
    .messages-container {
        /* Add any specific styling for the messages container if needed */
    }
    .messages-table th, .messages-table td {
        vertical-align: middle;
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
