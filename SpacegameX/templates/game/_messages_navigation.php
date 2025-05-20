<div class="message-nav">
    <ul>
        <li class="<?php echo ($activeTab === 'inbox') ? 'active' : ''; ?>">
            <a href="/messages">Inbox <?php if (isset($unreadCount) && $unreadCount > 0): ?>(<?php echo $unreadCount; ?>)<?php endif; ?></a>
        </li>
        <li class="<?php echo ($activeTab === 'sent') ? 'active' : ''; ?>">
            <a href="/messages/sent">Sent</a>
        </li>
        <li class="<?php echo ($activeTab === 'compose') ? 'active' : ''; ?>">
            <a href="/messages/compose">Compose</a>
        </li>
        <?php if (isset($systemMessagesCount) && $systemMessagesCount > 0): // Optional: For system messages tab if implemented later ?>
        <li class="<?php echo ($activeTab === 'system') ? 'active' : ''; ?>">
            <a href="/messages/system">System <?php if (isset($unreadSystemCount) && $unreadSystemCount > 0): ?>(<?php echo $unreadSystemCount; ?>)<?php endif; ?></a>
        </li>
        <?php endif; ?>
    </ul>
</div>
<div style="clear: both;"></div>
<br>
