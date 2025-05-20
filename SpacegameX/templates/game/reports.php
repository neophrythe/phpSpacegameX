<?php 
$pageTitle = "Kampfberichte";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<h2>Kampfberichte</h2>
<p>Übersicht aller Kämpfe deiner Flotte</p>

<div class="reports-container">
    <?php if (empty($reports)): ?>
        <div class="no-reports">
            <p>Keine Kampfberichte vorhanden.</p>
        </div>
    <?php else: ?>
        <table class="reports-table">
            <tr>
                <th>Datum</th>
                <th>Angreifer</th>
                <th>Verteidiger</th>
                <th>Planet</th>
                <th>Koordinaten</th>
                <th>Ergebnis</th>
                <th>Beute</th>
                <th></th>
            </tr>
            <?php foreach ($reports as $report): ?>
                <tr class="report-row <?php echo ($report->player_role == 'attacker' && $report->is_attacker_winner) || 
                                             ($report->player_role == 'defender' && $report->is_defender_winner) ? 'victory' : 
                                             (($report->is_draw) ? 'draw' : 'defeat'); ?>">
                    <td><?php echo date('d.m.Y H:i:s', strtotime($report->battle_time)); ?></td>
                    <td><?php echo htmlspecialchars($report->attacker_name); ?></td>
                    <td><?php echo htmlspecialchars($report->defender_name); ?></td>
                    <td><?php echo htmlspecialchars($report->planet_name); ?></td>
                    <td><?php echo htmlspecialchars($report->target_coordinates); ?></td>
                    <td>
                        <?php 
                        if ($report->is_draw) {
                            echo 'Unentschieden';
                        } else if ($report->is_attacker_winner) {
                            echo $report->player_role == 'attacker' ? 'Sieg' : 'Niederlage';
                        } else {
                            echo $report->player_role == 'defender' ? 'Sieg' : 'Niederlage';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($report->player_role == 'attacker' && $report->is_attacker_winner && !empty($report->resources_captured)): ?>
                            <div class="resources-captured">
                                <span class="resource metal"><?php echo number_format($report->resources_captured['metal'], 0, ',', '.'); ?></span>
                                <span class="resource crystal"><?php echo number_format($report->resources_captured['crystal'], 0, ',', '.'); ?></span>
                                <span class="resource deuterium"><?php echo number_format($report->resources_captured['deuterium'], 0, ',', '.'); ?></span>
                            </div>
                        <?php elseif ($report->player_role == 'defender' && !$report->is_defender_winner): ?>
                            <div class="resources-lost">
                                <span class="resource metal">-<?php echo number_format($report->resources_captured['metal'], 0, ',', '.'); ?></span>
                                <span class="resource crystal">-<?php echo number_format($report->resources_captured['crystal'], 0, ',', '.'); ?></span>
                                <span class="resource deuterium">-<?php echo number_format($report->resources_captured['deuterium'], 0, ',', '.'); ?></span>
                            </div>
                        <?php else: ?>
                            <span>-</span>
                        <?php endif; ?>
                    </td>                    <td>
                        <a href="/combat/report/<?php echo $report->id; ?>" class="btn-view-report">Details</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<style>
    .reports-container {
        margin: 20px 0;
    }
    
    .reports-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .reports-table th, .reports-table td {
        border: 1px solid #444;
        padding: 8px;
        text-align: center;
    }
    
    .reports-table th {
        background-color: #1a1f3a;
    }
    
    .report-row {
        background-color: #232738;
    }
    
    .report-row:nth-child(odd) {
        background-color: #1e2230;
    }
    
    .report-row.victory {
        background-color: #1f3a1f;
    }
    
    .report-row.defeat {
        background-color: #3a1f1f;
    }
    
    .report-row.draw {
        background-color: #3a3a1f;
    }
    
    .resource {
        display: inline-block;
        margin-right: 10px;
    }
    
    .resource.metal::before {
        content: "🔩 ";
    }
    
    .resource.crystal::before {
        content: "💎 ";
    }
    
    .resource.deuterium::before {
        content: "💧 ";
    }
    
    .btn-view-report {
        padding: 5px 10px;
        background-color: #2a3b56;
        color: #fff;
        text-decoration: none;
        border-radius: 3px;
    }
</style>

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
