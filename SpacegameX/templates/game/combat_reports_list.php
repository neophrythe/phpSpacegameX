
<?php
// filepath: f:\sdi\wog\SpacegameX\templates\game\combat_reports_list.php
$pageTitle = $pageTitle ?? 'Kampfberichte';
include __DIR__ . '/../layout/header.php';
?>

<div class="messages-container"> <?php // Reusing messages-container for similar styling ?>
    <h2><?php echo htmlspecialchars($pageTitle); ?></h2>

    <?php include __DIR__ . '/_messages_navigation.php'; // Optional: if you want similar sub-navigation ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="flash-message flash-error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="flash-message flash-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <?php if (empty($reports)): ?>
        <p class="no-messages">Keine Kampfberichte vorhanden.</p>
    <?php else: ?>
        <table class="messages-table"> <?php // Reusing messages-table for styling ?>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Betreff</th>
                    <th>Von/An</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr class="<?php echo !$report->is_read ? 'unread-message' : ''; ?>">
                        <td><?php echo htmlspecialchars(date('d.m.Y H:i:s', strtotime($report->battle_time))); ?></td>
                        <td>
                            <?php
                            $subject = "Kampf um {$report->target_coordinates}";
                            if ($report->attacker_id == $_SESSION['user_id']) {
                                $subject = "Angriff auf {$report->defender_name} bei {$report->target_coordinates}";
                            } elseif ($report->defender_id == $_SESSION['user_id']) {
                                $subject = "Verteidigung gegen {$report->attacker_name} bei {$report->target_coordinates}";
                            }
                            echo htmlspecialchars($subject);
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($report->attacker_id == $_SESSION['user_id']) {
                                echo "An: " . htmlspecialchars($report->defender_name ?? 'Unbekannt');
                            } else {
                                echo "Von: " . htmlspecialchars($report->attacker_name ?? 'Unbekannt');
                            }
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/combat/reports/view/<?php echo $report->id; ?>">Ansehen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
