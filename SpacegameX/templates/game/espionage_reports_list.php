<?php
// File: templates/game/espionage_reports_list.php
$pageTitle = $pageTitle ?? 'Spionageberichte';
$player = $player ?? null;
$reports = $reports ?? [];
$navigation = $navigation ?? []; // Contains unread counts

require_once __DIR__ . '/../layout/header.php';
?>

<div class="container mt-3">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

    <?php require_once __DIR__ . '/_messages_navigation.php'; // Re-use messages navigation for consistency ?>

    <div class="list-group mt-3">
        <?php if (empty($reports)): ?>
            <p class="list-group-item">Keine Spionageberichte vorhanden.</p>
        <?php else: ?>
            <?php foreach ($reports as $report): ?>
                <a href="<?php echo BASE_URL; ?>/espionage/reports/view/<?php echo $report->id; ?>"
                   class="list-group-item list-group-item-action <?php echo !$report->is_read ? 'font-weight-bold' : ''; ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1">
                            Spionagebericht #<?php echo htmlspecialchars($report->id); ?>
                            <?php if (!$report->is_read): ?>
                                <span class="badge badge-danger">Neu</span>
                            <?php endif; ?>
                        </h5>
                        <small><?php echo htmlspecialchars(date("d.m.Y H:i", strtotime($report->created_at))); ?></small>
                    </div>
                    <p class="mb-1">
                        Ziel: <?php echo htmlspecialchars($report->target_galaxy . ':' . $report->target_system . ':' . $report->target_position); ?>
                        (Planet: <?php echo htmlspecialchars($report->target_planet_name ?? 'Unbekannt'); ?>)
                        <?php if (isset($report->target_player_name) && $report->target_player_name !== 'N/A' && $report->target_player_name !== 'Niemand (Unbewohnt)'): ?>
                            Spieler: <?php echo htmlspecialchars($report->target_player_name); ?>
                        <?php elseif (isset($report->target_player_name) && $report->target_player_name === 'Niemand (Unbewohnt)'): ?>
                            <span class="text-muted">(Unbewohnt)</span>
                        <?php endif; ?>
                    </p>
                    <small>Typ: <?php echo htmlspecialchars(ucfirst($report->mission_type)); ?>, Status: <?php echo htmlspecialchars(ucfirst($report->status)); ?></small>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
