<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Router;
use Models\Alliance;
use Models\AllianceMember;
use Models\AllianceMessage;
use Models\AllianceMessageRecipient;
use Models\PlayerNotification;
use Services\NotificationService;

class AllianceMessageController extends BaseController {

    public function __construct(Request $request)
    {
        parent::__construct($request);
        // Ensure user is logged in and part of an alliance for most actions
        if (!isset($_SESSION['player_id'])) {
            Router::redirect('/login');
        }
    }

    private function ensurePlayerInAlliance(int $playerId): ?int
    {
        $allianceMembership = AllianceMember::findByPlayerId($playerId);
        if (!$allianceMembership || !$allianceMembership->alliance_id) {
            $_SESSION['error_message'] = "You must be a member of an alliance to access this page.";
            Router::redirect('/alliance'); // Redirect to general alliance page or dashboard
            return null;
        }
        return $allianceMembership->alliance_id;
    }

    /**
     * List received alliance messages for the current player.
     */
    public function index(): void
    {
        $playerId = $_SESSION['player_id'];
        $allianceId = $this->ensurePlayerInAlliance($playerId);
        if ($allianceId === null) return;

        $page = (int)($this->request->getQuery('page', 1));
        $perPage = 15; // Messages per page
        $offset = ($page - 1) * $perPage;

        $messages = AllianceMessageRecipient::getPlayerAllianceMessages($playerId, $perPage, $offset);
        $totalMessages = AllianceMessageRecipient::countPlayerAllianceMessages($playerId);
        $totalPages = ceil($totalMessages / $perPage);
        
        $alliance = Alliance::findById($allianceId);

        $this->render('alliance/messages_list', [
            'messages' => $messages,
            'allianceName' => $alliance ? $alliance->name : 'Your Alliance',
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'baseUrl' => '/alliance/messages'
        ]);
    }

    /**
     * View a specific alliance message.
     */
    public function view(array $params): void
    {
        $playerId = $_SESSION['player_id'];
        $allianceId = $this->ensurePlayerInAlliance($playerId);
        if ($allianceId === null) return;

        $messageId = (int)$params['id'];
        if (!AllianceMessageRecipient::isRecipient($messageId, $playerId)) {
            $_SESSION['error_message'] = "Message not found or you do not have permission to view it.";
            Router::redirect('/alliance/messages');
            return;
        }

        $message = AllianceMessage::findById($messageId);
        if (!$message || $message->alliance_id !== $allianceId) {
            $_SESSION['error_message'] = "Message not found or does not belong to your alliance.";
            Router::redirect('/alliance/messages');
            return;
        }

        // Mark as read
        AllianceMessageRecipient::markAsRead($messageId, $playerId);
        // Also mark the corresponding notification as read, if any
        PlayerNotification::markReadByLink("/alliance/messages/view/{$messageId}", $playerId);

        $this->render('alliance/message_view', [
            'message' => $message,
            'allianceName' => Alliance::findById($allianceId)->name ?? 'Your Alliance'
        ]);
    }

    /**
     * Display form to compose a new alliance message.
     */
    public function compose(): void
    {
        $playerId = $_SESSION['player_id'];
        $allianceId = $this->ensurePlayerInAlliance($playerId);
        if ($allianceId === null) return;
        
        $alliance = Alliance::findById($allianceId);

        $this->render('alliance/message_compose', [
            'allianceName' => $alliance ? $alliance->name : 'Your Alliance'
        ]);
    }

    /**
     * Send a new alliance message.
     */
    public function send(): void
    {
        $playerId = $_SESSION['player_id'];
        $allianceId = $this->ensurePlayerInAlliance($playerId);
        if ($allianceId === null) return;

        if ($this->request->isPost()) {
            $subject = trim($this->request->getPost('subject'));
            $body = trim($this->request->getPost('body'));

            if (empty($subject) || empty($body)) {
                $_SESSION['error_message'] = "Subject and body cannot be empty.";
                Router::redirect('/alliance/messages/compose');
                return;
            }

            $messageId = AllianceMessage::create($allianceId, $playerId, $subject, $body);

            if ($messageId) {
                $allianceMembers = AllianceMember::getMembers($allianceId);
                $recipientIds = array_map(fn($member) => $member->player_id, $allianceMembers);
                // Exclude sender from recipients list for their own inbox, they don't need a copy there.
                $recipientIds = array_filter($recipientIds, fn($id) => $id !== $playerId);

                AllianceMessageRecipient::addRecipients($messageId, $recipientIds);

                // Notify recipients
                $sender = $_SESSION['player_username'] ?? 'An alliance member'; // Fallback if username not in session
                $notificationMessage = "New alliance message from {$sender}: \"" . substr($subject, 0, 50) . "...\"";
                $link = "/alliance/messages/view/{$messageId}";
                
                foreach ($recipientIds as $recipientId) {
                    NotificationService::createNotification(
                        $recipientId, 
                        PlayerNotification::TYPE_ALLIANCE_MESSAGE,
                        $notificationMessage,
                        $link
                    );
                }

                $_SESSION['success_message'] = "Alliance message sent successfully.";
                Router::redirect('/alliance/messages');
            } else {
                $_SESSION['error_message'] = "Failed to send alliance message.";
                Router::redirect('/alliance/messages/compose');
            }
        }
    }

    /**
     * Delete an alliance message (soft delete for the recipient).
     */
    public function delete(array $params): void
    {
        $playerId = $_SESSION['player_id'];
        $allianceId = $this->ensurePlayerInAlliance($playerId);
        if ($allianceId === null) return;

        $messageId = (int)$params['id'];

        if (AllianceMessageRecipient::isRecipient($messageId, $playerId)) {
            AllianceMessageRecipient::markAsDeleted($messageId, $playerId);
            $_SESSION['success_message'] = "Message deleted.";
        } else {
            $_SESSION['error_message'] = "Message not found or you cannot delete it.";
        }
        Router::redirect('/alliance/messages');
    }
}
?>
