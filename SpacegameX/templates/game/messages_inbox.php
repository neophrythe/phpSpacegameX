<?php 
// File: f:\sdi\wog\SpacegameX\templates\game\messages_inbox.php
$this->layout('layout.game_layout', [
    'pageTitle' => $pageTitle ?? 'Inbox',
    'gameTitle' => 'WOG - Messages',
    'stylesheets' => ['css/style.css', 'css/notifications.css'], // Add specific message CSS if needed
    'activeTab' => $activeTab ?? 'inbox' // For highlighting in a shared message navigation
]); 
?>

<div class="container mt-4 messages-container">
    <h2><?= htmlspecialchars($pageTitle ?? 'Inbox') ?></h2>

    <?php include __DIR__ . '/_messages_navigation.php'; ?>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <span>Total messages: <?= $totalMessages ?? 0 ?> (Unread: <?= $unreadCount ?? 0 ?>)</span>
        <a href="<?= $this->e('/messages/compose') ?>" class="btn btn-primary">Compose New Message</a>
    </div>

    <?php if (empty($messages)): ?>
        <p>Your inbox is empty.</p>
    <?php else: ?>
        <form id="bulkActionForm" method="POST" action="<?= $this->e('/messages/markReadBulk') ?>"> <!-- Or delete bulk -->
            <table class="table table-hover message-table">
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" id="selectAllMessages"></th>
                        <th style="width: 150px;">From</th>
                        <th>Subject</th>
                        <th style="width: 180px;">Received</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr class="<?= !$message->is_read ? 'font-weight-bold table-info' : '' ?>">
                            <td><input type="checkbox" name="message_ids[]" value="<?= $message->id ?>" class="message-checkbox"></td>
                            <td><?= htmlspecialchars($message->sender_username ?? 'System') ?></td>
                            <td>
                                <a href="<?= $this->e('/messages/view/' . $message->id) ?>">
                                    <?= htmlspecialchars($message->subject) ?>
                                </a>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($message->sent_at)) ?></td>
                            <td>
                                <a href="<?= $this->e('/messages/view/' . $message->id) ?>" class="btn btn-sm btn-info">View</a>
                                <!-- Delete form for individual message -->
                                <form action="<?= $this->e('/messages/delete') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                    <input type="hidden" name="message_id" value="<?= $message->id ?>">
                                    <input type="hidden" name="from_tab" value="inbox">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="mt-2">
                 <button type="button" id="markSelectedReadBtn" class="btn btn-secondary btn-sm">Mark Selected as Read</button>
                <!-- Add other bulk actions like delete if needed -->
            </div>
        </form>

        <?php if (($totalPages ?? 1) > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $this->e('/messages/inbox/page/' . $i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php $this->push('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAllMessages');
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
    const markSelectedReadBtn = document.getElementById('markSelectedReadBtn');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            messageCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }

    if (markSelectedReadBtn) {
        markSelectedReadBtn.addEventListener('click', function() {
            const selectedIds = Array.from(messageCheckboxes)
                                    .filter(cb => cb.checked)
                                    .map(cb => cb.value);
            if (selectedIds.length === 0) {
                alert('Please select messages to mark as read.');
                return;
            }

            fetch('<?= $this->e("/messages/markReadBulk") ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ 'message_ids[]': selectedIds })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Selected messages marked as read.');
                    window.location.reload(); // Reload to see changes
                } else {
                    alert(data.message || 'Failed to mark messages as read.');
                }
            })
            .catch(error => {
                console.error('Error marking messages as read:', error);
                alert('An error occurred. Please try again.');
            });
        });
    }
});
</script>
<?php $this->end(); ?>
