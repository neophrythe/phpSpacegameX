<?php
// filepath: f:\sdi\wog\SpacegameX\templates\notifications\index.php
$pageTitle = "Benachrichtigungen"; 
include_once BASE_PATH . '/templates/layout/header.php';
?>

<div class="content-box">
    <h2>Benachrichtigungen</h2>

    <?php if (empty($notifications)): ?>
        <p class="no-data">Du hast keine Benachrichtigungen.</p>
    <?php else: ?>
        <div class="notification-grid">
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo !$notification->is_read ? 'unread' : ''; ?>">
                    <div class="notification-icon">
                        <?php
                        switch ($notification->type) {
                            case 'building':
                                echo '🏗️';
                                break;
                            case 'research':
                                echo '🔬';
                                break;
                            case 'fleet':
                                echo '🚀';
                                break;
                            case 'attack':
                                echo '⚔️';
                                break;
                            case 'defense':
                                echo '🛡️';
                                break;
                            case 'resource':
                                echo '💰';
                                break;
                            case 'asteroid':
                                echo '☄️';
                                break;
                            case 'capital':
                                echo '👑';
                                break;
                            case 'alliance':
                                echo '🤝';
                                break;
                            default:
                                echo '📩';
                        }
                        ?>
                    </div>
                    <div class="notification-content">
                        <?php echo $notification->message; ?>
                        
                        <div class="notification-time">
                            <?php
                            $date = new DateTime($notification->created_at);
                            $now = new DateTime();
                            $interval = $now->diff($date);
                            
                            if ($interval->days == 0) {
                                if ($interval->h == 0) {
                                    if ($interval->i == 0) {
                                        echo 'gerade eben';
                                    } else {
                                        echo 'vor ' . $interval->i . ' Minute' . ($interval->i !== 1 ? 'n' : '');
                                    }
                                } else {
                                    echo 'vor ' . $interval->h . ' Stunde' . ($interval->h !== 1 ? 'n' : '');
                                }
                            } else if ($interval->days < 7) {
                                echo 'vor ' . $interval->days . ' Tag' . ($interval->days !== 1 ? 'en' : '');
                            } else {
                                echo $date->format('d.m.Y H:i');
                            }
                            ?>
                        </div>
                    </div>
                      <div class="notification-actions">
                        <?php if (!$notification->is_read): ?>
                            <button class="mark-read-btn" data-id="<?php echo $notification->id; ?>" title="Als gelesen markieren">✓</button>
                        <?php endif; ?>
                        <button class="delete-notification-btn" data-id="<?php echo $notification->id; ?>" title="Löschen">🗑️</button>
                        <input type="checkbox" class="notification-select" data-id="<?php echo $notification->id; ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="notification-actions">
            <?php if (array_filter($notifications, function($n) { return !$n->is_read; })): ?>
                <button id="markAllAsReadBtn" class="mark-all-read-btn">Alle als gelesen markieren</button>
            <?php endif; ?>
            <button id="deleteSelectedBtn" class="delete-selected-btn" disabled>Ausgewählte löschen</button>
            <button id="cleanupBtn" class="cleanup-btn">Alte Benachrichtigungen löschen</button>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle "Mark as Read" buttons
    const markReadBtns = document.querySelectorAll('.mark-read-btn');
    markReadBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.getAttribute('data-id');
            markAsRead(notificationId);
        });
    });
      // Handle "Mark All as Read" button
    const markAllBtn = document.getElementById('markAllAsReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', markAllAsRead);
    }
    
    // Handle "Delete" buttons
    const deleteBtns = document.querySelectorAll('.delete-notification-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.getAttribute('data-id');
            deleteNotification(notificationId);
        });
    });
    
    // Handle "Cleanup" button
    const cleanupBtn = document.getElementById('cleanupBtn');
    if (cleanupBtn) {
        cleanupBtn.addEventListener('click', function() {
            cleanupOldNotifications(30); // Default to 30 days
        });
    }
    
    // Handle notification checkboxes
    const checkboxes = document.querySelectorAll('.notification-select');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedBoxes = document.querySelectorAll('.notification-select:checked');
            deleteSelectedBtn.disabled = checkedBoxes.length === 0;
        });
    });
    
    // Handle "Delete Selected" button
    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.notification-select:checked');
            if (checkedBoxes.length === 0) return;
            
            const notificationIds = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-id'));
            deleteMultipleNotifications(notificationIds);
        });
    }
    
    function markAsRead(notificationId) {
        const formData = new FormData();
        formData.append('notification_id', notificationId);
        
        fetch(BASE_URL + '/notifications/mark-read', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationElement = document.querySelector(`.notification-item button[data-id="${notificationId}"]`).closest('.notification-item');
                notificationElement.classList.remove('unread');
                const markReadBtn = notificationElement.querySelector('.mark-read-btn');
                if (markReadBtn) {
                    markReadBtn.remove();
                }
                
                // Update count in header
                const countElement = document.getElementById('notificationCount');
                if (countElement) {
                    const currentCount = parseInt(countElement.textContent);
                    if (!isNaN(currentCount) && currentCount > 0) {
                        countElement.textContent = currentCount - 1;
                        if (currentCount - 1 === 0) {
                            document.getElementById('notificationIcon').classList.remove('has-notifications');
                        }
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    function markAllAsRead() {
        fetch(BASE_URL + '/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const unreadElements = document.querySelectorAll('.notification-item.unread');
                unreadElements.forEach(element => {
                    element.classList.remove('unread');
                    const markReadBtn = element.querySelector('.mark-read-btn');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                });
                
                const markAllBtn = document.getElementById('markAllAsReadBtn');
                if (markAllBtn) {
                    markAllBtn.style.display = 'none';
                }
                
                // Update count in header
                const countElement = document.getElementById('notificationCount');
                if (countElement) {
                    countElement.textContent = '0';
                    document.getElementById('notificationIcon').classList.remove('has-notifications');
                }
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
    }
    
    function deleteNotification(notificationId) {
        if (!confirm('Möchtest du diese Benachrichtigung wirklich löschen?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('notification_id', notificationId);
        
        fetch(BASE_URL + '/notifications/delete', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationElement = document.querySelector(`.notification-item .delete-notification-btn[data-id="${notificationId}"]`).closest('.notification-item');
                notificationElement.remove();
                
                // If this was an unread notification, update the count
                if (notificationElement.classList.contains('unread')) {
                    const countElement = document.getElementById('notificationCount');
                    if (countElement) {
                        const currentCount = parseInt(countElement.textContent);
                        if (!isNaN(currentCount) && currentCount > 0) {
                            countElement.textContent = currentCount - 1;
                            if (currentCount - 1 === 0) {
                                document.getElementById('notificationIcon').classList.remove('has-notifications');
                            }
                        }
                    }
                }
                
                // Check if no more notifications
                if (document.querySelectorAll('.notification-item').length === 0) {
                    document.querySelector('.notification-grid').innerHTML = '<p class="no-data">Du hast keine Benachrichtigungen.</p>';
                    document.querySelector('.notification-actions').style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error deleting notification:', error);
        });
    }
    
    function deleteMultipleNotifications(notificationIds) {
        if (!confirm('Möchtest du die ausgewählten Benachrichtigungen wirklich löschen?')) {
            return;
        }
        
        fetch(BASE_URL + '/notifications/delete-multiple', {
            method: 'POST',
            body: JSON.stringify({ notification_ids: notificationIds }),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let unreadDeleted = 0;
                
                // Remove notification elements
                notificationIds.forEach(id => {
                    const notificationElement = document.querySelector(`.notification-item .notification-select[data-id="${id}"]`).closest('.notification-item');
                    if (notificationElement.classList.contains('unread')) {
                        unreadDeleted++;
                    }
                    notificationElement.remove();
                });
                
                // Update unread count if needed
                if (unreadDeleted > 0) {
                    const countElement = document.getElementById('notificationCount');
                    if (countElement) {
                        const currentCount = parseInt(countElement.textContent);
                        if (!isNaN(currentCount)) {
                            const newCount = Math.max(0, currentCount - unreadDeleted);
                            countElement.textContent = newCount;
                            if (newCount === 0) {
                                document.getElementById('notificationIcon').classList.remove('has-notifications');
                            }
                        }
                    }
                }
                
                // Disable delete selected button
                deleteSelectedBtn.disabled = true;
                
                // Check if no more notifications
                if (document.querySelectorAll('.notification-item').length === 0) {
                    document.querySelector('.notification-grid').innerHTML = '<p class="no-data">Du hast keine Benachrichtigungen.</p>';
                    document.querySelector('.notification-actions').style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error deleting notifications:', error);
        });
    }
    
    function cleanupOldNotifications(days = 30) {
        if (!confirm(`Möchtest du alle gelesenen Benachrichtigungen löschen, die älter als ${days} Tage sind?`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('days', days);
        
        fetch(BASE_URL + '/notifications/cleanup', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Simply reload the page to show updated notifications
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error cleaning up notifications:', error);
        });
    }
});
</script>

<?php include_once BASE_PATH . '/templates/layout/footer.php'; ?>
