<?php
// filepath: f:\sdi\wog\SpacegameX\templates\game\combat_report_view.php
$pageTitle = $pageTitle ?? 'Kampfbericht Details';
$player = $player ?? null; // Assuming $player is passed from controller, contains current player data
$report = $report ?? null;
$reportData = $reportData ?? [];

require_once __DIR__ . '/../layout/header.php';

// Helper functions (can be moved to a helper file if used elsewhere)
function displayUnits(array $units, string $title): string
{
    if (empty($units)) {
        return '<p>' . htmlspecialchars($title) . ': Keine Einheiten.</p>';
    }
    $html = '<h5>' . htmlspecialchars($title) . '</h5><ul class="list-unstyled">';
    foreach ($units as $unitName => $details) {
        $quantity = is_array($details) ? ($details['quantity'] ?? $details['count'] ?? 0) : $details;
        $html .= '<li>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $unitName))) . ': ' . htmlspecialchars(number_format((int)$quantity));
        if (is_array($details) && isset($details['lost'])) {
            $html .= ' (Verloren: ' . htmlspecialchars(number_format((int)$details['lost'])) . ')';
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function displayLoot(array $loot): string
{
    if (empty($loot)) {
        return '<p>Keine Beute gemacht.</p>';
    }
    $html = '<h5>Beute:</h5><ul class="list-unstyled">';
    foreach ($loot as $resource => $amount) {
        $html .= '<li>' . htmlspecialchars(ucfirst($resource)) . ': ' . htmlspecialchars(number_format($amount)) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function displayDebris(array $debris): string
{
    if (empty($debris)) {
        return '<p>Kein Trümmerfeld entstanden.</p>';
    }
    $html = '<h5>Trümmerfeld:</h5><ul class="list-unstyled">';
    foreach ($debris as $resource => $amount) {
        $html .= '<li>' . htmlspecialchars(ucfirst($resource)) . ': ' . htmlspecialchars(number_format($amount)) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

?>

<div class="container mt-3">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

    <?php require_once __DIR__ . '/_messages_navigation.php'; // Re-use messages navigation for consistency ?>

    <?php if ($report && !empty($reportData)): ?>
        <div class="card mt-3">
            <div class="card-header">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-0">Kampfbericht #<?php echo htmlspecialchars($report->id); ?></h5>
                    <small>Datum: <?php echo htmlspecialchars(date('d.m.Y H:i:s', strtotime($report->battle_time))); ?></small>
                </div>
            </div>
            <div class="card-body">
                <p>
                    <strong>Angreifer:</strong> <?php echo htmlspecialchars($report->attacker_name ?? 'Unbekannt'); ?>
                    (Planet: <?php echo htmlspecialchars($reportData['attacker']['planet_name'] ?? 'N/A'); ?>)
                </p>
                <p>
                    <strong>Verteidiger:</strong> <?php echo htmlspecialchars($report->defender_name ?? 'Unbekannt'); ?>
                    (Planet: <?php echo htmlspecialchars($reportData['defender']['planet_name'] ?? $report->target_coordinates); ?>)
                </p>

                <hr>

                <h4>Kampfverlauf:</h4>
                <?php if (isset($reportData['rounds']) && !empty($reportData['rounds'])):
                    foreach ($reportData['rounds'] as $roundNumber => $round):
                ?>
                    <div class="card mt-2 mb-2">
                        <div class="card-header">Runde <?php echo $roundNumber + 1; ?></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Angreifer Flotte</h6>
                                    <?php echo displayUnits($round['attacker']['units_before_combat'] ?? [], 'Einheiten vor Kampf'); ?>
                                    <?php echo displayUnits($round['attacker']['units_after_combat'] ?? [], 'Einheiten nach Kampf (Verluste)'); ?>
                                     <p>Schaden verursacht: <?php echo htmlspecialchars(number_format($round['attacker']['damage_dealt'] ?? 0)); ?></p>
                                     <p>Schilde absorbiert: <?php echo htmlspecialchars(number_format($round['attacker']['shields_absorbed'] ?? 0)); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Verteidiger Flotte/Verteidigung</h6>
                                    <?php echo displayUnits($round['defender']['units_before_combat'] ?? [], 'Einheiten vor Kampf'); ?>
                                    <?php echo displayUnits($round['defender']['units_after_combat'] ?? [], 'Einheiten nach Kampf (Verluste)'); ?>
                                    <p>Schaden verursacht: <?php echo htmlspecialchars(number_format($round['defender']['damage_dealt'] ?? 0)); ?></p>
                                    <p>Schilde absorbiert: <?php echo htmlspecialchars(number_format($round['defender']['shields_absorbed'] ?? 0)); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                    endforeach;
                else:
                ?>
                    <p>Keine detaillierten Kampfrunden aufgezeichnet.</p>
                <?php endif; ?>

                <hr>
                <h4>Ergebnis des Kampfes:</h4>
                <?php if (isset($reportData['summary'])):
                    $summary = $reportData['summary'];
                ?>
                    <p><strong>Gewinner:</strong> <?php echo htmlspecialchars(ucfirst($summary['winner'] ?? 'Unentschieden')); ?></p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Angreifer Verluste</h5>
                            <?php echo displayUnits($summary['attacker_losses']['units'] ?? [], 'Verlorene Einheiten'); ?>
                            <p>Gesamtwert Verluste (Eisen): <?php echo htmlspecialchars(number_format($summary['attacker_losses']['total_value']['eisen'] ?? 0)); ?> </p>
                             <p>Gesamtwert Verluste (Silber): <?php echo htmlspecialchars(number_format($summary['attacker_losses']['total_value']['silber'] ?? 0)); ?> </p>
                              <p>Gesamtwert Verluste (Uderon): <?php echo htmlspecialchars(number_format($summary['attacker_losses']['total_value']['uderon'] ?? 0)); ?> </p>
                        </div>
                        <div class="col-md-6">
                            <h5>Verteidiger Verluste</h5>
                            <?php echo displayUnits($summary['defender_losses']['units'] ?? [], 'Verlorene Einheiten'); ?>
                             <p>Gesamtwert Verluste (Eisen): <?php echo htmlspecialchars(number_format($summary['defender_losses']['total_value']['eisen'] ?? 0)); ?> </p>
                             <p>Gesamtwert Verluste (Silber): <?php echo htmlspecialchars(number_format($summary['defender_losses']['total_value']['silber'] ?? 0)); ?> </p>
                              <p>Gesamtwert Verluste (Uderon): <?php echo htmlspecialchars(number_format($summary['defender_losses']['total_value']['uderon'] ?? 0)); ?> </p>
                        </div>
                    </div>

                    <?php echo displayLoot($summary['loot'] ?? []); ?>
                    <?php echo displayDebris($summary['debris_field'] ?? []); ?>

                <?php else: ?>
                    <p>Keine Zusammenfassung verfügbar.</p>
                <?php endif; ?>

                <hr>
                <a href="<?php echo BASE_URL; ?>/combat/reports" class="btn btn-secondary">Zurück zur Übersicht</a>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Kampfbericht nicht gefunden oder Daten unvollständig.</div>
        <a href="<?php echo BASE_URL; ?>/combat/reports" class="btn btn-secondary">Zurück zur Übersicht</a>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
