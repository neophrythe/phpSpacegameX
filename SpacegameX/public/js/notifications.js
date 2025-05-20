// SpacegameX Notification System
document.addEventListener('DOMContentLoaded', function() {
    // Initialize notification system
    const notificationCenter = document.getElementById('notificationCenter');
    const notificationIcon = document.getElementById('notificationIcon');
    const notificationList = document.getElementById('notificationList');
    const notificationCount = document.getElementById('notificationCount');
    const closeNotificationsBtn = document.getElementById('closeNotifications');
    
    if (!notificationIcon || !notificationCenter) {
        console.error('Notification UI elements not found');
        return;
    }
      // Check if we already have the notification count from PHP
    if (typeof unreadNotificationCount !== 'undefined') {
        // Use the count provided by PHP
        updateNotificationCount(unreadNotificationCount);
    } else {
        // Fetch it via AJAX if not provided
        fetchNotificationCount();
    }
    
    // Toggle notification center when icon is clicked
    notificationIcon.addEventListener('click', function() {
        toggleNotificationCenter();
    });
    
    // Close notification center when close button is clicked
    if (closeNotificationsBtn) {
        closeNotificationsBtn.addEventListener('click', function() {
            notificationCenter.classList.remove('open');
        });
    }
    
    // Close notification center when clicking outside of it
    document.addEventListener('click', function(event) {
        if (!notificationCenter.contains(event.target) && 
            !notificationIcon.contains(event.target) &&
            notificationCenter.classList.contains('open')) {
            notificationCenter.classList.remove('open');
        }
    });
    
    function toggleNotificationCenter() {
        if (!notificationCenter.classList.contains('open')) {
            // Load notifications before opening the panel
            fetchNotifications();
        }
        
        notificationCenter.classList.toggle('open');
    }
    
    function fetchNotificationCount() {
        fetch(BASE_URL + '/notifications/count', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error fetching notification count:', data.error);
                return;
            }
            
            updateNotificationCount(data.count);
        })
        .catch(error => {
            console.error('Error fetching notification count:', error);
        });
    }
    
    function updateNotificationCount(count) {
        notificationCount.textContent = count;
        
        if (count > 0) {
            notificationIcon.classList.add('has-notifications');
        } else {
            notificationIcon.classList.remove('has-notifications');
        }
    }
    
    function fetchNotifications() {
        fetch(BASE_URL + '/notifications', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(notifications => {
            if (notifications.error) {
                console.error('Error fetching notifications:', notifications.error);
                return;
            }
            
            displayNotifications(notifications);
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
    }
    
    function displayNotifications(notifications) {
        // Clear the notification list
        notificationList.innerHTML = '';
        
        if (notifications.length === 0) {
            const emptyMessage = document.createElement('div');
            emptyMessage.className = 'notification-empty';
            emptyMessage.textContent = 'Keine Benachrichtigungen vorhanden.';
            notificationList.appendChild(emptyMessage);
            return;
        }
        
        // Add each notification to the list
        notifications.forEach(notification => {
            const notificationItem = createNotificationElement(notification);
            notificationList.appendChild(notificationItem);
        });
        
        // Add "Mark all as read" button if there are unread notifications
        const hasUnread = notifications.some(notification => !notification.is_read);
        if (hasUnread) {
            const markAllBtn = document.createElement('button');
            markAllBtn.className = 'mark-all-read-btn';
            markAllBtn.textContent = 'Alle als gelesen markieren';
            markAllBtn.addEventListener('click', markAllAsRead);
            notificationList.appendChild(markAllBtn);
        }
    }
    
    function createNotificationElement(notification) {
        const notificationItem = document.createElement('div');
        notificationItem.className = 'notification-item';
        notificationItem.dataset.id = notification.id;
        
        if (!notification.is_read) {
            notificationItem.classList.add('unread');
        }
        
        // Add icon based on notification type
        const icon = document.createElement('div');
        icon.className = 'notification-icon';
        
        switch (notification.type) {
            case 'building':
                icon.textContent = '🏗️';
                break;
            case 'research':
                icon.textContent = '🔬';
                break;
            case 'fleet':
                icon.textContent = '🚀';
                break;
            case 'attack':
                icon.textContent = '⚔️';
                break;
            case 'defense':
                icon.textContent = '🛡️';
                break;
            case 'resource':
                icon.textContent = '💰';
                break;
            case 'asteroid':
                icon.textContent = '☄️';
                break;
            case 'capital':
                icon.textContent = '👑';
                break;
            case 'alliance':
                icon.textContent = '🤝';
                break;
            case 'system':
                icon.textContent = '⚙️';
                break;
            default:
                icon.textContent = '📩';
        }
        
        notificationItem.appendChild(icon);
        
        // Message content
        const content = document.createElement('div');
        content.className = 'notification-content';
        content.innerHTML = notification.message;
        
        // Time
        const time = document.createElement('div');
        time.className = 'notification-time';
        const date = new Date(notification.created_at);
        time.textContent = formatDate(date);
        
        content.appendChild(time);
        notificationItem.appendChild(content);
        
        // Action buttons container
        const actions = document.createElement('div');
        actions.className = 'notification-actions';
        
        // Mark as read button for unread notifications
        if (!notification.is_read) {
            const markReadBtn = document.createElement('button');
            markReadBtn.className = 'mark-read-btn';
            markReadBtn.innerHTML = '✓';
            markReadBtn.title = 'Als gelesen markieren';
            markReadBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                markAsRead(notification.id);
            });
            actions.appendChild(markReadBtn);
        }
        
        // Delete button for all notifications
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'delete-notification-btn';
        deleteBtn.innerHTML = '🗑️';
        deleteBtn.title = 'Löschen';
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            deleteNotification(notification.id);
        });
        actions.appendChild(deleteBtn);
        
        notificationItem.appendChild(actions);
        
        // Make the notification clickable
        notificationItem.addEventListener('click', function() {
            if (!notification.is_read) {
                markAsRead(notification.id);
            }
            
            // Add additional navigation based on notification type if needed
            // Example: redirect to fleets for fleet notifications
            // if (notification.type === 'fleet') {
            //     window.location.href = BASE_URL + '/fleet';
            // }
        });
        
        return notificationItem;
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
                // Update UI to show as read
                const notificationElement = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notificationElement) {
                    notificationElement.classList.remove('unread');
                    const markReadBtn = notificationElement.querySelector('.mark-read-btn');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                }
                
                // Update notification count
                fetchNotificationCount();
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
                // Update UI to show all as read
                const unreadElements = document.querySelectorAll('.notification-item.unread');
                unreadElements.forEach(element => {
                    element.classList.remove('unread');
                    const markReadBtn = element.querySelector('.mark-read-btn');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                });
                
                // Remove the "Mark all as read" button
                const markAllBtn = document.querySelector('.mark-all-read-btn');
                if (markAllBtn) {
                    markAllBtn.remove();
                }
                
                // Update notification count
                updateNotificationCount(0);
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
                // Remove notification element from UI
                const notificationElement = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notificationElement) {
                    notificationElement.remove();
                }
                
                // Check if we need to show "no notifications" message
                if (document.querySelectorAll('.notification-item').length === 0) {
                    const emptyMessage = document.createElement('div');
                    emptyMessage.className = 'notification-empty';
                    emptyMessage.textContent = 'Keine Benachrichtigungen vorhanden.';
                    notificationList.appendChild(emptyMessage);
                }
                
                // If this was an unread notification, update the count
                if (notificationElement && notificationElement.classList.contains('unread')) {
                    fetchNotificationCount();
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
                // Remove notification elements from UI
                notificationIds.forEach(id => {
                    const notificationElement = document.querySelector(`.notification-item[data-id="${id}"]`);
                    if (notificationElement) {
                        notificationElement.remove();
                    }
                });
                
                // Check if we need to show "no notifications" message
                if (document.querySelectorAll('.notification-item').length === 0) {
                    const emptyMessage = document.createElement('div');
                    emptyMessage.className = 'notification-empty';
                    emptyMessage.textContent = 'Keine Benachrichtigungen vorhanden.';
                    notificationList.appendChild(emptyMessage);
                }
                
                // Update unread count
                fetchNotificationCount();
            }
        })
        .catch(error => {
            console.error('Error deleting multiple notifications:', error);
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
                // Refresh notification list to show changes
                fetchNotifications();
                
                // Show confirmation message
                alert(`Alte Benachrichtigungen wurden erfolgreich gelöscht.`);
            }
        })
        .catch(error => {
            console.error('Error cleaning up notifications:', error);
        });
    }
    
    function formatDate(date) {
        const now = new Date();
        const diff = Math.floor((now - date) / 1000); // difference in seconds
        
        if (diff < 60) {
            return 'gerade eben';
        } else if (diff < 3600) {
            const mins = Math.floor(diff / 60);
            return `vor ${mins} Minute${mins !== 1 ? 'n' : ''}`;
        } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return `vor ${hours} Stunde${hours !== 1 ? 'n' : ''}`;
        } else {
            const days = Math.floor(diff / 86400);
            if (days < 7) {
                return `vor ${days} Tag${days !== 1 ? 'en' : ''}`;
            } else {
                return date.toLocaleDateString('de-DE', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }
        }
    }
    
    // Poll for updates every 30 seconds
    setInterval(fetchNotificationCount, 30000);
});
