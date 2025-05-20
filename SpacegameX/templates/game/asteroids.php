<?php 
$pageTitle = "Asteroiden-Management";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<h2>Asteroiden-Management: <?php echo htmlspecialchars($planetName ?? ''); ?> (<?php echo htmlspecialchars($coords ?? ''); ?>)</h2>

<!-- Planet Selector -->
<div class="planet-selector">
    <form method="GET" action="/asteroids">
        <label for="planet_id">Planet wechseln:</label>
        <select name="planet_id" id="planet_id" onchange="this.form.submit()">
            <?php foreach ($allPlayerPlanets as $p): ?>
                <option value="<?php echo $p->id; ?>" <?php echo ($p->id == $currentPlanetId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p->name); ?> (<?php echo $p->galaxy; ?>:<?php echo $p->system; ?>:<?php echo $p->position; ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Display Resources -->
<div class="resource-display">
    <h3>Ressourcen auf <?php echo htmlspecialchars($planetName ?? 'Planet'); ?>:</h3>
    <div class="resources">
        <div class="resource">
            <span class="resource-label">Eisen:</span> 
            <span class="resource-value"><?php echo number_format($eisen ?? 0, 0, ',', '.'); ?></span>
        </div>
        <div class="resource">
            <span class="resource-label">Silber:</span> 
            <span class="resource-value"><?php echo number_format($silber ?? 0, 0, ',', '.'); ?></span>
        </div>
        <div class="resource">
            <span class="resource-label">Uderon:</span> 
            <span class="resource-value"><?php echo number_format($uderon ?? 0, 0, ',', '.'); ?></span>
        </div>
        <div class="resource">
            <span class="resource-label">Wasserstoff:</span> 
            <span class="resource-value"><?php echo number_format($wasserstoff ?? 0, 0, ',', '.'); ?></span>
        </div>
    </div>
</div>

<!-- Asteroid Status -->
<div class="asteroid-status">
    <h3>Asteroiden-Status</h3>
    
    <div class="current-asteroids">
        <table class="asteroid-table">
            <thead>
                <tr>
                    <th>Asteroid</th>
                    <th>Typ</th>
                    <th>Größe</th>
                    <th>Bonus</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($currentAsteroids)): ?>
                <tr>
                    <td colspan="5" class="text-center">Keine Asteroiden in der Umlaufbahn.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($currentAsteroids as $asteroid): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($asteroid->id); ?></td>
                        <td>
                            <?php 
                            switch ($asteroid->type) {
                                case 'metal':
                                    echo 'Eisen';
                                    break;
                                case 'crystal':
                                    echo 'Silber';
                                    break;
                                case 'uderon':
                                    echo 'Uderon';
                                    break;
                                case 'mixed':
                                    echo 'Gemischt';
                                    break;
                            }
                            ?>
                        </td>
                        <td><?php echo $asteroid->size; ?></td>
                        <td>
                            <?php if ($asteroid->type == 'mixed'): ?>
                                +<?php echo $asteroid->bonus; ?>% auf alle Ressourcen
                            <?php else: ?>
                                +<?php echo $asteroid->bonus; ?>% auf <?php echo $asteroid->type == 'metal' ? 'Eisen' : ($asteroid->type == 'crystal' ? 'Silber' : 'Uderon'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="/asteroids/discard">
                                <input type="hidden" name="planet_id" value="<?php echo $currentPlanetId; ?>">
                                <input type="hidden" name="asteroid_id" value="<?php echo $asteroid->id; ?>">
                                <button type="submit" class="btn-small danger">Entfernen</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (count($currentAsteroids) < $maxAsteroids): ?>
    <div class="asteroid-mining">
        <h4>Neuen Asteroiden einfangen</h4>
        
        <?php if ($canMineNewAsteroid): ?>
        <form method="POST" action="/asteroids/mine">
            <input type="hidden" name="planet_id" value="<?php echo $currentPlanetId; ?>">
            
            <div class="form-group">
                <label for="asteroid_type">Asteroid-Typ:</label>
                <select name="type" id="asteroid_type" required>
                    <option value="metal">Eisen-Asteroid</option>
                    <option value="crystal">Silber-Asteroid</option>
                    <option value="uderon">Uderon-Asteroid</option>
                    <?php if ($advancedMiningResearch >= 5): ?>
                    <option value="mixed">Gemischter Asteroid</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="asteroid-costs">
                <p>Kosten für Einfangmanöver:</p>
                <ul>
                    <li>Eisen: <?php echo number_format($miningCosts['metal'], 0, ',', '.'); ?></li>
                    <li>Silber: <?php echo number_format($miningCosts['crystal'], 0, ',', '.'); ?></li>
                    <li>Wasserstoff: <?php echo number_format($miningCosts['h2'], 0, ',', '.'); ?></li>
                </ul>
            </div>
            
            <button type="submit" class="btn-asteroid" <?php echo ($notEnoughResources ? 'disabled' : ''); ?>>
                Asteroid einfangen
            </button>
            
            <?php if ($notEnoughResources): ?>
            <p class="resources-warning">Nicht genügend Ressourcen vorhanden!</p>
            <?php endif; ?>
        </form>
        <?php else: ?>
        <p class="cooldown-warning">Bergungsausrüstung im Cooldown. Verfügbar in: <?php echo $cooldownRemaining; ?></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <p class="max-warning">Maximale Anzahl an Asteroiden erreicht (<?php echo $maxAsteroids; ?>).</p>
    <?php endif; ?>
</div>

<!-- Mining Research -->
<div class="mining-research">
    <h3>Asteroiden-Forschung</h3>
    <div class="research-info">
        <p>Asteroiden-Bergbau-Level: <?php echo $miningResearchLevel; ?></p>
        <p>Fortgeschrittener Bergbau-Level: <?php echo $advancedMiningResearch; ?></p>
        
        <div class="research-benefits">
            <p>Aktuelle Bonusse:</p>
            <ul>
                <li>Maximale Asteroiden: <?php echo $maxAsteroids; ?></li>
                <li>Asteroid-Bonus-Multiplikator: <?php echo $bonusMultiplier; ?>x</li>
                <?php if ($advancedMiningResearch >= 5): ?>
                <li>Zugang zu gemischten Asteroiden</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <p><a href="/research" class="btn-research">Zur Forschung</a></p>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
