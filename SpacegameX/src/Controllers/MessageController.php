<?php
namespace Controllers;

use Core\Controller;
use Models\PlayerMessage;
use Models\Player; // To get player names for display
use Models\PlayerNotification; // For notifications

class MessageController extends Controller {

    public function __construct() {
        // Ensure user is logged in for all actions in this controller
        if (!isset($_SESSION['user_id'])) {
            if ($this->isAjaxRequest()) {
                http_response_code(401); // Unauthorized
                echo json_encode(['error' => 'Authentication required.']);
            } else {
                $this->redirect('/login');
            }
            exit;
        }
    }

    private function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    public function index($params = []) { // Inbox
        $playerId = $_SESSION['user_id'];
        $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $limit = 15; // Messages per page
        $offset = ($page - 1) * $limit;

        $messages = PlayerMessage::getInbox($playerId, false, $limit, $offset);
        $totalMessages = PlayerMessage::countMessages($playerId, false, false);
        $totalPages = ceil($totalMessages / $limit);
        
        $unreadCount = PlayerMessage::countMessages($playerId, false, true);

        $this->view('game.messages_inbox', [
            'pageTitle' => 'Inbox',
            'messages' => $messages,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalMessages' => $totalMessages,
            'unreadCount' => $unreadCount,
            'activeTab' => 'inbox'
        ]);
    }

    public function sent($params = []) {
        $playerId = $_SESSION['user_id'];
        $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $messages = PlayerMessage::getSentItems($playerId, $limit, $offset);
        $totalMessages = PlayerMessage::countMessages($playerId, true, false);
        $totalPages = ceil($totalMessages / $limit);
        
        $unreadCount = PlayerMessage::countMessages($playerId, false, true); // For the inbox badge

        $this->view('game.messages_sent', [
            'pageTitle' => 'Sent Messages',
            'messages' => $messages,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalMessages' => $totalMessages,
            'unreadCount' => $unreadCount, // For the inbox badge
            'activeTab' => 'sent'
        ]);
    }

    public function view($viewName, $data = []) { // Changed signature to match Core\Controller
        $messageId = isset($data['id']) ? intval($data['id']) : 0;
        if ($messageId <= 0) {
            $_SESSION['error_message'] = 'Invalid message ID.';
            $this->redirect('/messages');
            return;
        }

        $playerId = $_SESSION['user_id'];
        $message = PlayerMessage::getMessageById($messageId, $playerId);

        if (!$message) {
            $_SESSION['error_message'] = 'Message not found or you do not have permission to view it.';
            $this->redirect('/messages');
            return;
        }
        
        // If the message was for this player and was unread, it's now marked as read by getMessageById.
        // Now, also mark the corresponding notification as read.
        if ($message->player_id == $playerId) { // Only if the current player is the recipient
            PlayerNotification::markReadByLink(BASE_URL . '/messages/view/' . $messageId, $playerId);
        }

        $this->view('game.messages_view', [
            'pageTitle' => 'View Message: ' . htmlspecialchars($message->subject),
            'message' => $message,
            'activeTab' => ($message->player_id == $playerId) ? 'inbox' : 'sent' // Determine if it was an inbox or sent item for tab highlighting
        ]);
    }

    public function compose($params = []) {
        $playerId = $_SESSION['user_id'];
        $recipientName = isset($_GET['recipient_name']) ? trim($_GET['recipient_name']) : '';
        $replyToMessageId = isset($_GET['reply_to']) ? intval($_GET['reply_to']) : null;
        
        $recipient = null;
        $originalMessage = null;
        $subject = '';
        $content = '';

        if ($recipientName) {
            $recipient = Player::findByUsername($recipientName); // Assuming Player model has findByUsername
            if (!$recipient) {
                $_SESSION['error_message'] = 'Recipient not found.';
                // Don't redirect, let them correct the name or clear it
            }
        }

        if ($replyToMessageId) {
            $originalMessage = PlayerMessage::getMessageById($replyToMessageId, $playerId);
            if ($originalMessage && ($originalMessage->player_id == $playerId || $originalMessage->sender_id == $playerId)) {
                // If current player is recipient of original, new recipient is original sender
                if ($originalMessage->player_id == $playerId && $originalMessage->sender_id) {
                     $recipient = Player::getPlayerDataById($originalMessage->sender_id);
                     $recipientName = $recipient ? $recipient->username : '';
                }
                // If current player is sender of original (e.g. replying to own sent message, less common)
                // then new recipient is original recipient.
                else if ($originalMessage->sender_id == $playerId) {
                    $recipient = Player::getPlayerDataById($originalMessage->player_id);
                    $recipientName = $recipient ? $recipient->username : '';
                }


                $subject = (strpos($originalMessage->subject, 'Re: ') === 0 ? '' : 'Re: ') . $originalMessage->subject;
                $content = "\n\n--- Original Message ---\nFrom: " . ($originalMessage->sender_username ?? 'System') . "\nSent: " . $originalMessage->sent_at . "\nSubject: " . $originalMessage->subject . "\n\n" . $originalMessage->content;
            } else {
                 $_SESSION['error_message'] = 'Original message for reply not found or not accessible.';
            }
        }
        
        $unreadCount = PlayerMessage::countMessages($playerId, false, true); // For the inbox badge

        $this->view('game.messages_compose', [
            'pageTitle' => 'Compose Message',
            'recipientName' => $recipientName,
            'recipientExists' => (bool)$recipient,
            'subject' => $subject,
            'content' => $content,
            'unreadCount' => $unreadCount, // For the inbox badge
            'activeTab' => 'compose'
        ]);
    }

    public function send() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/messages/compose');
            return;
        }

        $playerId = $_SESSION['user_id'];
        $recipientName = trim($_POST['recipient_name'] ?? '');
        $subject = trim($_POST['subject'] ?? 'No Subject');
        $content = trim($_POST['content'] ?? '');

        if (empty($recipientName)) {
            $_SESSION['error_message'] = 'Recipient name cannot be empty.';
            $this->redirect('/messages/compose?subject=' . urlencode($subject) . '&content=' . urlencode($content));
            return;
        }
        if (empty($content)) {
            $_SESSION['error_message'] = 'Message content cannot be empty.';
            $this->redirect('/messages/compose?recipient_name=' . urlencode($recipientName) . '&subject=' . urlencode($subject));
            return;
        }
        
        // Prevent sending messages to oneself
        $sender = Player::getPlayerDataById($playerId);
        if (strtolower($sender->username) == strtolower($recipientName)) {
            $_SESSION['error_message'] = 'You cannot send a message to yourself.';
            $this->redirect('/messages/compose?recipient_name=' . urlencode($recipientName) . '&subject=' . urlencode($subject) . '&content=' . urlencode($content));
            return;
        }

        $recipient = Player::findByUsername($recipientName); // Assuming Player model has findByUsername
        if (!$recipient) {
            $_SESSION['error_message'] = 'Recipient "' . htmlspecialchars($recipientName) . '" not found.';
            $this->redirect('/messages/compose?recipient_name=' . urlencode($recipientName) . '&subject=' . urlencode($subject) . '&content=' . urlencode($content));
            return;
        }

        $messageId = PlayerMessage::createMessage($playerId, $recipient->id, $subject, $content, 'player');

        if ($messageId) {
            $_SESSION['success_message'] = 'Message sent successfully to ' . htmlspecialchars($recipientName) . '.';
            // Create a notification for the recipient
            PlayerNotification::createNotification(
                $recipient->id, 
                PlayerNotification::TYPE_PLAYER_MESSAGE_RECEIVED, // Use the constant
                'Neue Nachricht von ' . htmlspecialchars($sender->username) . ': \\"' . htmlspecialchars($subject) . '\\"', // Standardized message
                BASE_URL . '/messages/view/' . $messageId // Add link to view the message
            );
            $this->redirect('/messages/sent');
        } else {
            $_SESSION['error_message'] = 'Failed to send message. Please try again.';
            $this->redirect('/messages/compose?recipient_name=' . urlencode($recipientName) . '&subject=' . urlencode($subject) . '&content=' . urlencode($content));
        }
    }

    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->isAjaxRequest()) {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Invalid request method.']);
            } else {
                $this->redirect('/messages');
            }
            return;
        }

        $playerId = $_SESSION['user_id'];
        $messageId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        $fromTab = $_POST['from_tab'] ?? 'inbox'; // To redirect back correctly

        if ($messageId <= 0) {
            if ($this->isAjaxRequest()) {
                echo json_encode(['success' => false, 'message' => 'Invalid message ID.']);
            } else {
                $_SESSION['error_message'] = 'Invalid message ID.';
                $this->redirect($fromTab === 'sent' ? '/messages/sent' : '/messages');
            }
            return;
        }

        // Ensure the message belongs to the player (either as sender or receiver to allow deletion from their view)
        // PlayerMessage::deleteMessage is currently implemented to only allow recipient to delete.
        // This might need adjustment if senders should also be able to "delete" from their sent view (e.g., soft delete or flag).
        // For now, we rely on PlayerMessage::deleteMessage's current logic.
        
        $message = PlayerMessage::getMessageById($messageId, $playerId); // This also marks as read if it was an inbox message
        
        if (!$message) {
             if ($this->isAjaxRequest()) {
                echo json_encode(['success' => false, 'message' => 'Message not found or action not permitted.']);
            } else {
                $_SESSION['error_message'] = 'Message not found or action not permitted.';
                $this->redirect($fromTab === 'sent' ? '/messages/sent' : '/messages');
            }
            return;
        }

        // If the player is the recipient, they can delete it.
        // If the player is the sender, current PlayerMessage::deleteMessage won't delete it.
        // We might need a separate method or logic for "removing from sent view" if that's desired.
        // For now, only recipients can truly delete.
        $deleted = false;
        if ($message->player_id == $playerId) { // Player is the recipient
            $deleted = PlayerMessage::deleteMessage($messageId, $playerId);
        } else if ($message->sender_id == $playerId) { // Player is the sender
            // Mark as deleted by sender
            $deleted = PlayerMessage::markDeletedBySender($messageId, $playerId);
            if ($deleted) {
                if ($this->isAjaxRequest()) {
                    echo json_encode(['success' => true, 'message' => 'Message removed from your sent items.']);
                } else {
                    $_SESSION['success_message'] = 'Message removed from your sent items.';
                    $this->redirect('/messages/sent');
                }
                return; // Early return as we don't want the generic success/failure message below for this case
            }
        }


        if ($deleted) {
            if ($this->isAjaxRequest()) {
                echo json_encode(['success' => true, 'message' => 'Message deleted successfully.']);
            } else {
                $_SESSION['success_message'] = 'Message deleted successfully.';
                $this->redirect($fromTab === 'sent' ? '/messages/sent' : '/messages');
            }
        } else {
             if ($this->isAjaxRequest()) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete message or action not permitted.']);
            } else {
                $_SESSION['error_message'] = 'Failed to delete message or action not permitted.';
                $this->redirect($fromTab === 'sent' ? '/messages/sent' : '/messages');
            }
        }
    }
    
    public function markReadBulk() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->isAjaxRequest()) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request.']);
            return;
        }

        $playerId = $_SESSION['user_id'];
        $messageIds = isset($_POST['message_ids']) && is_array($_POST['message_ids']) ? $_POST['message_ids'] : [];
        
        if (empty($messageIds)) {
            echo json_encode(['success' => false, 'message' => 'No message IDs provided.']);
            return;
        }

        $markedCount = 0;
        foreach ($messageIds as $messageId) {
            if (PlayerMessage::markAsRead(intval($messageId), $playerId)) {
                $markedCount++;
            }
        }

        if ($markedCount > 0) {
            echo json_encode(['success' => true, 'message' => $markedCount . ' message(s) marked as read.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No messages were marked as read. They might have already been read or do not belong to you.']);
        }
    }
}
?>
