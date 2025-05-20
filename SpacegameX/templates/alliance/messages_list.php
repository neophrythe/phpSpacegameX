<?php
/**
 * @var array $messages
 * @var string $allianceName
 * @var int $currentPage
 * @var int $totalPages
 * @var string $baseUrl
 */

$this->layout('../layout/default', ['title' => $allianceName . ' - Alliance Messages']);
?>

<div class="container">
    <?php include __DIR__ . '/_alliance_navigation.php'; ?>

    <h2>Alliance Messages - <?= htmlspecialchars($allianceName) ?></h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="mb-3">
        <a href="/alliance/messages/compose" class="btn btn-primary">Compose New Message</a>
    </div>

    <?php if (empty($messages)): ?>
        <p>You have no alliance messages.</p>
    <?php else: ?>
        <table class="table table-hover messages-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>From</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $message): ?>
                    <tr class="<?= !(bool)$message['is_read'] ? 'message-unread' : '' ?>">
                        <td>
                            <a href="/alliance/messages/view/<?= (int)$message['id'] ?>">
                                <?= htmlspecialchars($message['subject']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($message['sender_username'] ?? 'System') ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($message['created_at'])) ?></td>
                        <td>
                            <a href="/alliance/messages/delete/<?= (int)$message['id'] ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Are you sure you want to delete this message?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($baseUrl) ?>?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    .messages-table .message-unread td {
        font-weight: bold;
    }
</style>
