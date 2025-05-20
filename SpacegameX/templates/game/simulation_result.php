<?php 
$pageTitle = "Simulationsergebnis";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<div class="simulation-result-container">
    <div class="simulation-header">
        <h2>Kampfsimulation - Ergebnis</h2>
    </div>
    
    <div class="initial-fleet">
        <h3>Simulierte Flotten</h3>
        
        <div class="fleets-container">
            <div class="attacker-fleet">
                <h4>Angreifer</h4>
                <div class="research-info">
                    <div class="research-item">
                        <span class="research-label">Waffen:</span>
                        <span class="research-value"><?php echo $report['attacker_research']['weapon']; ?></span>
                    </div>
                    <div class="research-item">
                        <span class="research-label">Schilde:</span>
                        <span class="research-value"><?php echo $report['attacker_research']['shield']; ?></span>
                    </div>
                    <div class="research-item">
                        <span class="research-label">Panzerung:</span>
                        <span class="research-value"><?php echo $report['attacker_research']['armor']; ?></span>
                    </div>
                </div>
                
                <?php if (empty($report['initial_attacker_ships'])): ?>
                    <p>Keine Schiffe</p>
                <?php else: ?>
                    <table class="fleet-table">
                        <tr>
                            <th>Schiff</th>
                            <th>Anzahl</th>
                        </tr>
                        <?php foreach ($report['initial_attacker_ships'] as $ship): ?>
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
                <div class="research-info">
                    <div class="research-item">
                        <span class="research-label">Waffen:</span>
                        <span class="research-value"><?php echo $report['defender_research']['weapon']; ?></span>
                    </div>
                    <div class="research-item">
                        <span class="research-label">Schilde:</span>
                        <span class="research-value"><?php echo $report['defender_research']['shield']; ?></span>
                    </div>
                    <div class="research-item">
                        <span class="research-label">Panzerung:</span>
                        <span class="research-value"><?php echo $report['defender_research']['armor']; ?></span>
                    </div>
                </div>
                
                <?php if (empty($report['initial_defender_ships'])): ?>
                    <p>Keine Schiffe</p>
                <?php else: ?>
                    <table class="fleet-table">
                        <tr>
                            <th>Schiff</th>
                            <th>Anzahl</th>
                        </tr>
                        <?php foreach ($report['initial_defender_ships'] as $ship): ?>
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
        <h3>Simulierter Kampfverlauf</h3>
        
        <?php if (empty($report['rounds'])): ?>
            <p>Der Kampf wurde nicht ausgeführt.</p>
        <?php else: ?>
            <?php foreach ($report['rounds'] as $round): ?>
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
        <h3>Simulationsergebnis</h3>
        
        <?php if ($report['result']['is_draw']): ?>
            <div class="result-draw">
                <p class="result-headline">Unentschieden</p>
                <p>Die Simulation endete nach der maximalen Anzahl an Runden ohne klaren Sieger.</p>
            </div>
        <?php elseif ($report['result']['is_attacker_winner']): ?>
            <div class="result-victory attacker-victory">
                <p class="result-headline">Angreifer gewinnt!</p>
            </div>
        <?php else: ?>
            <div class="result-victory defender-victory">
                <p class="result-headline">Verteidiger gewinnt!</p>
            </div>
        <?php endif; ?>
        
        <div class="remaining-fleets">
            <div class="fleets-container">
                <div class="attacker-fleet">
                    <h4>Verbleibende Angreiferflotte</h4>
                    <?php if (empty($report['result']['attacker_ships_remaining']) || array_sum(array_column($report['result']['attacker_ships_remaining'], 'quantity')) == 0): ?>
                        <p>Alle Schiffe wurden zerstört</p>
                    <?php else: ?>
                        <table class="fleet-table">
                            <tr>
                                <th>Schiff</th>
                                <th>Anzahl</th>
                                <th>Verluste</th>
                                <th>Verluste (%)</th>
                            </tr>
                            <?php foreach ($report['result']['attacker_ships_remaining'] as $ship): ?>
                                <?php
                                $original = 0;
                                foreach ($report['initial_attacker_ships'] as $initialShip) {
                                    if ($initialShip['ship_type_id'] == $ship['ship_type_id']) {
                                        $original = $initialShip['quantity'];
                                        break;
                                    }
                                }
                                $losses = $original - $ship['quantity'];
                                $lossesPercent = $original > 0 ? round(($losses / $original) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ship['name']); ?></td>
                                    <td><?php echo number_format($ship['quantity'], 0, ',', '.'); ?></td>
                                    <td><?php echo number_format($losses, 0, ',', '.'); ?></td>
                                    <td><?php echo $lossesPercent; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="defender-fleet">
                    <h4>Verbleibende Verteidigerflotte</h4>
                    <?php if (empty($report['result']['defender_ships_remaining']) || array_sum(array_column($report['result']['defender_ships_remaining'], 'quantity')) == 0): ?>
                        <p>Alle Schiffe wurden zerstört</p>
                    <?php else: ?>
                        <table class="fleet-table">
                            <tr>
                                <th>Schiff</th>
                                <th>Anzahl</th>
                                <th>Verluste</th>
                                <th>Verluste (%)</th>
                            </tr>
                            <?php foreach ($report['result']['defender_ships_remaining'] as $ship): ?>
                                <?php
                                $original = 0;
                                foreach ($report['initial_defender_ships'] as $initialShip) {
                                    if ($initialShip['ship_type_id'] == $ship['ship_type_id']) {
                                        $original = $initialShip['quantity'];
                                        break;
                                    }
                                }
                                $losses = $original - $ship['quantity'];
                                $lossesPercent = $original > 0 ? round(($losses / $original) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ship['name']); ?></td>
                                    <td><?php echo number_format($ship['quantity'], 0, ',', '.'); ?></td>
                                    <td><?php echo number_format($losses, 0, ',', '.'); ?></td>
                                    <td><?php echo $lossesPercent; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="battle-statistics">
            <h4>Kampfstatistik</h4>
            <div class="statistics-container">
                <div class="statistic-item">
                    <span class="statistic-label">Kampfpunkte:</span>
                    <span class="statistic-value"><?php echo number_format($report['battle_points'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>
    </div>
      <div class="simulation-actions">
        <a href="/combat/simulator" class="btn-back">Neue Simulation</a>
    </div>
</div>

<style>
    .simulation-result-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
        background-color: #1a1f2e;
        border-radius: 5px;
    }
    
    .simulation-header {
        text-align: center;
        margin-bottom: 30px;
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
    
    .research-info {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        padding: 10px;
        background-color: rgba(0, 0, 0, 0.2);
        border-radius: 3px;
    }
    
    .research-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .research-label {
        font-size: 12px;
        color: #aaa;
    }
    
    .research-value {
        font-weight: bold;
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
    
    .result-victory, .result-draw {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        text-align: center;
    }
    
    .result-draw {
        background-color: #3a3a1f;
    }
    
    .attacker-victory {
        background-color: #1f3a1f;
    }
    
    .defender-victory {
        background-color: #3a1f1f;
    }
    
    .battle-statistics {
        margin-top: 20px;
        background-color: #232738;
        padding: 15px;
        border-radius: 5px;
    }
    
    .statistics-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 10px;
    }
    
    .statistic-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .statistic-label {
        font-size: 12px;
        color: #aaa;
    }
    
    .statistic-value {
        font-weight: bold;
        font-size: 18px;
    }
    
    .simulation-actions {
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
