<?php
/**
 * @var \Models\AllianceMessage $message
 * @var string $allianceName
 */

$this->layout('../layout/default', ['title' => 'View Alliance Message - ' . htmlspecialchars($allianceName)]);
?>

<div class="container">
    <?php include __DIR__ . '/_alliance_navigation.php'; ?>

    <h2>View Alliance Message: <?= htmlspecialchars($message->subject) ?></h2>
    <p><a href="/alliance/messages" class="btn btn-secondary btn-sm">&laquo; Back to Alliance Messages</a></p>

    <div class="card">
        <div class="card-header">
            <strong>From:</strong> <?= htmlspecialchars($message->sender_username ?? 'System') ?><br>
            <strong>Date:</strong> <?= date('Y-m-d H:i:s', strtotime($message->created_at)) ?><br>
            <strong>Subject:</strong> <?= htmlspecialchars($message->subject) ?>
        </div>
        <div class="card-body">
            <p><?= nl2br(htmlspecialchars($message->body)) ?></p>
        </div>
        <div class="card-footer">
            <a href="/alliance/messages/delete/<?= (int)$message->id ?>" 
               class="btn btn-danger btn-sm" 
               onclick="return confirm('Are you sure you want to delete this message?');">Delete Message</a>
        </div>
    </div>
</div>
