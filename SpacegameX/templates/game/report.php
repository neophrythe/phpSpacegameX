<?php 
$pageTitle = "Kampfbericht";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<div class="report-container">
    <div class="report-header">
        <h2>Kampfbericht</h2>
        <p class="battle-time"><?php echo date('d.m.Y H:i:s', strtotime($report->battle_time)); ?></p>
        <p class="battle-location">
            Planet: <?php echo htmlspecialchars($report->planet_name); ?> 
            [<?php echo htmlspecialchars($report->target_coordinates); ?>]
        </p>
        
        <div class="participants">
            <div class="attacker">
                <h3>Angreifer</h3>
                <p class="player-name"><?php echo htmlspecialchars($report->attacker_name); ?></p>
            </div>
            <div class="versus">VS</div>
            <div class="defender">
                <h3>Verteidiger</h3>
                <p class="player-name"><?php echo htmlspecialchars($report->defender_name); ?></p>
            </div>
        </div>
    </div>
    
    <div class="initial-fleet">
        <h3>Beteiligte Flotten</h3>
        
        <div class="fleets-container">
            <div class="attacker-fleet">
                <h4>Angreifer</h4>
                <?php if (empty($report->report_data['initial_attacker_ships'])): ?>
                    <p>Keine Schiffe</p>
                <?php else: ?>
                    <table class="fleet-table">
                        <tr>
                            <th>Schiff</th>
                            <th>Anzahl</th>
                        </tr>
                        <?php foreach ($report->report_data['initial_attacker_ships'] as $ship): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ship['name']); ?></td>
                                <td><?php echo number_format($ship['quantity'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="defender-fleet">
                <h4>Verteidiger</h4>
                <?php if (empty($report->report_data['initial_defender_ships'])): ?>
                    <p>Keine Schiffe</p>
                <?php else: ?>
                    <table class="fleet-table">
                        <tr>
                            <th>Schiff</th>
                            <th>Anzahl</th>
                        </tr>
                        <?php foreach ($report->report_data['initial_defender_ships'] as $ship): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ship['name']); ?></td>
                                <td><?php echo number_format($ship['quantity'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="battle-rounds">
        <h3>Kampfverlauf</h3>
        
        <?php if (empty($report->report_data['rounds'])): ?>
            <p>Der Kampf wurde nicht ausgeführt.</p>
        <?php else: ?>
            <?php foreach ($report->report_data['rounds'] as $round): ?>
                <div class="battle-round">
                    <h4>Runde <?php echo $round['round']; ?></h4>
                    
                    <div class="round-stats">
                        <div class="attacker-stats">
                            <p>Angreifer verursacht <strong><?php echo number_format($round['attacker_damage_dealt'], 0, ',', '.'); ?></strong> Schaden</p>
                            <?php if (!empty($round['defender_ships_destroyed'])): ?>
                                <div class="ships-destroyed">
                                    <p>Zerstörte Schiffe:</p>
                                    <ul>
                                        <?php foreach ($round['defender_ships_destroyed'] as $ship): ?>
                                            <li><?php echo htmlspecialchars($ship['name']); ?>: <?php echo number_format($ship['quantity'], 0, ',', '.'); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="defender-stats">
                            <p>Verteidiger verursacht <strong><?php echo number_format($round['defender_damage_dealt'], 0, ',', '.'); ?></strong> Schaden</p>
                            <?php if (!empty($round['attacker_ships_destroyed'])): ?>
                                <div class="ships-destroyed">
                                    <p>Zerstörte Schiffe:</p>
                                    <ul>
                                        <?php foreach ($round['attacker_ships_destroyed'] as $ship): ?>
                                            <li><?php echo htmlspecialchars($ship['name']); ?>: <?php echo number_format($ship['quantity'], 0, ',', '.'); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="battle-result">
        <h3>Kampfergebnis</h3>
        
        <?php if ($report->report_data['result']['is_draw']): ?>
            <div class="result-draw">
                <p class="result-headline">Unentschieden</p>
                <p>Der Kampf endete nach der maximalen Anzahl an Runden ohne klaren Sieger.</p>
            </div>
        <?php elseif ($report->report_data['result']['is_attacker_winner']): ?>
            <div class="result-victory <?php echo $report->attacker_id == $_SESSION['user_id'] ? 'player-victory' : 'player-defeat'; ?>">
                <p class="result-headline"><?php echo $report->attacker_id == $_SESSION['user_id'] ? 'Sieg!' : 'Niederlage!'; ?></p>
                <p>Der Angreifer hat den Kampf gewonnen!</p>
            </div>
        <?php else: ?>
            <div class="result-victory <?php echo $report->defender_id == $_SESSION['user_id'] ? 'player-victory' : 'player-defeat'; ?>">
                <p class="result-headline"><?php echo $report->defender_id == $_SESSION['user_id'] ? 'Sieg!' : 'Niederlage!'; ?></p>
                <p>Der Verteidiger hat den Kampf gewonnen!</p>
            </div>
        <?php endif; ?>
        
        <div class="remaining-fleets">
            <div class="fleets-container">
                <div class="attacker-fleet">
                    <h4>Verbleibende Angreiferflotte</h4>
                    <?php if (empty($report->report_data['result']['attacker_ships_remaining']) || array_sum(array_column($report->report_data['result']['attacker_ships_remaining'], 'quantity')) == 0): ?>
                        <p>Alle Schiffe wurden zerstört</p>
                    <?php else: ?>
                        <table class="fleet-table">
                            <tr>
                                <th>Schiff</th>
                                <th>Anzahl</th>
                            </tr>
                            <?php foreach ($report->report_data['result']['attacker_ships_remaining'] as $ship): ?>
                                <?php if ($ship['quantity'] > 0): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ship['name']); ?></td>
                                    <td><?php echo number_format($ship['quantity'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="defender-fleet">
                    <h4>Verbleibende Verteidigerflotte</h4>
                    <?php if (empty($report->report_data['result']['defender_ships_remaining']) || array_sum(array_column($report->report_data['result']['defender_ships_remaining'], 'quantity')) == 0): ?>
                        <p>Alle Schiffe wurden zerstört</p>
                    <?php else: ?>
                        <table class="fleet-table">
                            <tr>
                                <th>Schiff</th>
                                <th>Anzahl</th>
                            </tr>
                            <?php foreach ($report->report_data['result']['defender_ships_remaining'] as $ship): ?>
                                <?php if ($ship['quantity'] > 0): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ship['name']); ?></td>
                                    <td><?php echo number_format($ship['quantity'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($report->report_data['resources_captured']) && $report->report_data['result']['is_attacker_winner']): ?>
            <div class="resources-captured">
                <h4>Erbeutete Ressourcen</h4>
                <div class="resources">
                    <div class="resource">
                        <span class="resource-icon metal">🔩</span>
                        <span class="resource-value"><?php echo number_format($report->report_data['resources_captured']['metal'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="resource">
                        <span class="resource-icon crystal">💎</span>
                        <span class="resource-value"><?php echo number_format($report->report_data['resources_captured']['crystal'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="resource">
                        <span class="resource-icon deuterium">💧</span>
                        <span class="resource-value"><?php echo number_format($report->report_data['resources_captured']['deuterium'], 0, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
      <div class="report-actions">
        <a href="/combat/reports" class="btn-back">Zurück zur Übersicht</a>
    </div>
</div>

<style>
    .report-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
        background-color: #1a1f2e;
        border-radius: 5px;
    }
    
    .report-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .battle-time, .battle-location {
        color: #aaa;
        margin: 5px 0;
    }
    
    .participants {
        display: flex;
        justify-content: space-around;
        align-items: center;
        margin-top: 20px;
    }
    
    .attacker, .defender {
        flex: 1;
        text-align: center;
    }
    
    .versus {
        font-size: 24px;
        font-weight: bold;
        padding: 0 20px;
        color: #ff5722;
    }
    
    .fleets-container {
        display: flex;
        gap: 20px;
        margin-top: 10px;
    }
    
    .attacker-fleet, .defender-fleet {
        flex: 1;
        background-color: #232738;
        padding: 15px;
        border-radius: 5px;
    }
    
    .fleet-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .fleet-table th, .fleet-table td {
        padding: 5px;
        text-align: left;
        border-bottom: 1px solid #333;
    }
    
    .battle-round {
        background-color: #232738;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 5px;
    }
    
    .round-stats {
        display: flex;
        gap: 20px;
    }
    
    .attacker-stats, .defender-stats {
        flex: 1;
        padding: 10px;
        background-color: rgba(0, 0, 0, 0.2);
        border-radius: 3px;
    }
    
    .ships-destroyed {
        margin-top: 10px;
    }
    
    .ships-destroyed ul {
        margin: 5px 0;
        padding-left: 20px;
    }
    
    .result-headline {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 10px;
    }
    
    .result-victory {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .result-draw {
        background-color: #3a3a1f;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .player-victory {
        background-color: #1f3a1f;
    }
    
    .player-defeat {
        background-color: #3a1f1f;
    }
    
    .resources-captured {
        margin-top: 20px;
        background-color: #232738;
        padding: 15px;
        border-radius: 5px;
    }
    
    .resources {
        display: flex;
        gap: 30px;
        margin-top: 10px;
    }
    
    .resource {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .resource-icon {
        font-size: 20px;
    }
    
    .resource-value {
        font-weight: bold;
    }
    
    .report-actions {
        margin-top: 30px;
        text-align: center;
    }
    
    .btn-back {
        padding: 10px 20px;
        background-color: #2a3b56;
        color: #fff;
        text-decoration: none;
        border-radius: 3px;
    }
</style>

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
