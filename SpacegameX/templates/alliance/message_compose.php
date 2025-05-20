<?php
/**
 * @var string $allianceName
 */

$this->layout('../layout/default', ['title' => 'Compose Alliance Message - ' . htmlspecialchars($allianceName)]);
?>

<div class="container">
    <?php include __DIR__ . '/_alliance_navigation.php'; ?>

    <h2>Compose Alliance Message for <?= htmlspecialchars($allianceName) ?></h2>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <form method="POST" action="/alliance/messages/send">
        <div class="mb-3">
            <label for="subject" class="form-label">Subject</label>
            <input type="text" class="form-control" id="subject" name="subject" required>
        </div>
        <div class="mb-3">
            <label for="body" class="form-label">Body</label>
            <textarea class="form-control" id="body" name="body" rows="10" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Send Message</button>
        <a href="/alliance/messages" class="btn btn-secondary">Cancel</a>
    </form>
</div>
