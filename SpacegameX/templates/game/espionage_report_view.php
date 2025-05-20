<?php
// File: templates/game/espionage_report_view.php
$pageTitle = $pageTitle ?? 'Spionagebericht Details';
$player = $player ?? null;
$report = $report ?? null;
$reportData = $reportData ?? [];
$espionageLevel = $espionageLevel ?? 0;
$navigation = $navigation ?? []; // Contains unread counts

require_once __DIR__ . '/../layout/header.php';

function displayResources($resources, $title = 'Ressourcen') {
    if (empty($resources)) return '';
    $html = '<h5>' . htmlspecialchars($title) . '</h5><ul class="list-unstyled">';
    foreach ($resources as $key => $value) {
        $html .= '<li>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) . ': ' . htmlspecialchars(number_format($value)) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function displayFleet($fleet, $title = 'Flotte') {
    if (empty($fleet)) return '';
    $html = '<h5>' . htmlspecialchars($title) . '</h5><ul class="list-unstyled">';
    foreach ($fleet as $shipName => $quantity) {
        $html .= '<li>' . htmlspecialchars($shipName) . ': ' . htmlspecialchars(number_format($quantity)) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function displayDefense($defense, $title = 'Verteidigung') {
    if (empty($defense)) return '';
    $html = '<h5>' . htmlspecialchars($title) . '</h5><ul class="list-unstyled">';
    foreach ($defense as $unitName => $quantity) {
        $html .= '<li>' . htmlspecialchars($unitName) . ': ' . htmlspecialchars(number_format($quantity)) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function displayBuildings($buildings, $title = 'Gebäude') {
    if (empty($buildings)) return '';
    $html = '<h5>' . htmlspecialchars($title) . '</h5><ul class="list-unstyled">';
    foreach ($buildings as $buildingName => $level) {
        $html .= '<li>' . htmlspecialchars($buildingName) . ': Level ' . htmlspecialchars($level) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function displayResearch($research, $title = 'Forschungen') {
    if (empty($research)) return '';
    $html = '<h5>' . htmlspecialchars($title) . '</h5><ul class="list-unstyled">';
    foreach ($research as $researchName => $level) {
        $html .= '<li>' . htmlspecialchars($researchName) . ': Level ' . htmlspecialchars($level) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

?>

<div class="container mt-3">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

    <?php require_once __DIR__ . '/_messages_navigation.php'; // Re-use messages navigation for consistency ?>

    <?php if ($report): ?>
        <div class="card mt-3">
            <div class="card-header">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-0">Bericht #<?php echo htmlspecialchars($report->id); ?></h5>
                    <small>Datum: <?php echo htmlspecialchars(date("d.m.Y H:i", strtotime($report->created_at))); ?></small>
                </div>
            </div>
            <div class="card-body">
                <p>
                    <strong>Von:</strong> Planet <?php echo htmlspecialchars($report->start_planet_name ?? 'Unbekannt'); ?>
                    (<?php echo htmlspecialchars($report->attacker_player_name ?? 'Unbekannt'); ?>)
                </p>
                <p>
                    <strong>Ziel:</strong> Planet <?php echo htmlspecialchars($report->target_planet_name ?? 'Unbekannt'); ?>
                    (<?php echo htmlspecialchars($report->target_galaxy . ':' . $report->target_system . ':' . $report->target_position); ?>)
                    <?php if (isset($report->target_player_name) && $report->target_player_name !== 'N/A'): ?>
                        Spieler: <?php echo htmlspecialchars($report->target_player_name); ?>
                    <?php endif; ?>
                </p>
                <p>
                    <strong>Missionstyp:</strong> <?php echo htmlspecialchars(ucfirst($report->mission_type)); ?><br>
                    <strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($report->status)); ?><br>
                    <strong>Eingesetzte Einheiten:</strong> <?php echo htmlspecialchars($report->quantity); ?> x <?php echo htmlspecialchars($unitName ?? $report->unit_type_id); ?>
                </p>

                <hr>

                <h4>Spionageergebnis:</h4>
                <?php if (isset($reportData['error'])): ?>
                    <div class="alert alert-danger">Fehler: <?php echo htmlspecialchars($reportData['error']); ?></div>
                <?php elseif (isset($reportData['success']) && $reportData['success'] === false && isset($reportData['message'])) : ?>
                     <div class="alert alert-warning">Mission fehlgeschlagen: <?php echo htmlspecialchars($reportData['message']); ?></div>
                     <?php if(isset($reportData['agents_lost']) && $reportData['agents_lost'] > 0): ?>
                        <p>Agenten verloren: <?php echo htmlspecialchars($reportData['agents_lost']); ?></p>
                     <?php endif; ?>
                <?php elseif (empty($reportData) || (isset($reportData['success']) && !$reportData['success'])) : ?>
                    <p class="text-muted">Keine detaillierten Informationen verfügbar oder Spionage fehlgeschlagen.</p>
                <?php else:
                    // Structure based on common espionage report details
                    // Adjust keys based on what Combat::processAgentEspionage actually returns
                    ?>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo displayResources($reportData['resources'] ?? [], 'Ressourcen auf dem Planeten'); ?>
                            <?php echo displayBuildings($reportData['buildings'] ?? [], 'Gebäudelevel'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo displayFleet($reportData['fleet'] ?? [], 'Stationierte Flotte'); ?>
                            <?php echo displayDefense($reportData['defense'] ?? [], 'Stationierte Verteidigung'); ?>
                            <?php echo displayResearch($reportData['research'] ?? [], 'Forschungslevel des Spielers'); ?>
                        </div>
                    </div>

                    <?php if (isset($reportData['messages'])): ?>
                        <h5>Nachrichten (<?php echo htmlspecialchars(count($reportData['messages'])); ?>)</h5>
                        <ul class="list-group">
                            <?php foreach($reportData['messages'] as $msg): ?>
                                <li class="list-group-item">
                                    <?php echo htmlspecialchars($msg['subject'] ?? 'Kein Betreff'); ?> - <?php echo htmlspecialchars($msg['from'] ?? 'Unbekannt'); ?> (<?php echo htmlspecialchars($msg['timestamp'] ?? 'N/A'); ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (isset($reportData['shipyard_orders'])) : ?>
                        <h5>Schiffswerft Aufträge:</h5>
                        <pre><?php echo htmlspecialchars(json_encode($reportData['shipyard_orders'], JSON_PRETTY_PRINT)); ?></pre>
                    <?php endif; ?>

                    <?php if (isset($reportData['additional_info'])): // For any other generic info ?>
                        <h5>Zusätzliche Informationen:</h5>
                        <pre><?php echo htmlspecialchars(json_encode($reportData['additional_info'], JSON_PRETTY_PRINT)); ?></pre>
                    <?php endif; ?>

                <?php endif; ?>

                <hr>
                <a href="<?php echo BASE_URL; ?>/espionage/reports" class="btn btn-secondary">Zurück zur Übersicht</a>
                <a href="<?php echo BASE_URL; ?>/espionage" class="btn btn-primary">Neuer Spionageauftrag</a>

            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Spionagebericht nicht gefunden.</div>
        <a href="<?php echo BASE_URL; ?>/espionage/reports" class="btn btn-secondary">Zurück zur Übersicht</a>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
