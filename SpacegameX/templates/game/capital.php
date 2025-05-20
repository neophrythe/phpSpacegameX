<?php 
$pageTitle = "Hauptplanet-Management";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<h2>Hauptplanet-Management</h2>

<!-- Current Capital Planet Info -->
<div class="capital-planet-info">
    <h3>Dein aktueller Hauptplanet</h3>
    
    <?php if ($hasCapitalPlanet): ?>
    <div class="planet-card capital">
        <div class="planet-header">
            <h4><?php echo htmlspecialchars($capitalPlanet->name); ?> [<?php echo $capitalPlanet->galaxy; ?>:<?php echo $capitalPlanet->system; ?>:<?php echo $capitalPlanet->position; ?>]</h4>
            <span class="capital-badge">Hauptplanet</span>
        </div>
        
        <div class="planet-details">
            <p><strong>Statuseffekte:</strong></p>
            <ul class="bonus-list">
                <li>Produktionsbonus: <span class="bonus">+<?php echo $capitalBonuses['production']; ?>%</span></li>
                <li>Forschungsbonus: <span class="bonus">+<?php echo $capitalBonuses['research']; ?>%</span></li>
                <li>Schiffbaubonus: <span class="bonus">+<?php echo $capitalBonuses['shipyard']; ?>%</span></li>
                <li>Verteidigungsbonus: <span class="bonus">+<?php echo $capitalBonuses['defense']; ?>%</span></li>
            </ul>
            
            <?php if ($lastCapitalChangeDate): ?>
            <p><strong>Letzter Hauptplanet-Wechsel:</strong> <?php echo $lastCapitalChangeDate; ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="no-capital-notice">
        <p>Du hast noch keinen Planeten als Hauptplanet festgelegt. Wähle unten einen Planeten aus!</p>
    </div>
    <?php endif; ?>
</div>

<!-- Capital Planet Change Form -->
<div class="capital-planet-change">
    <h3>Hauptplanet ändern</h3>
    
    <?php if (!$canChangeCapital): ?>
    <div class="cooldown-notice">
        <p class="warning">Der Hauptplanet kann nur alle <?php echo $capitalChangeCooldownDays; ?> Tage gewechselt werden.</p>
        <p>Nächster möglicher Wechsel: <?php echo $nextPossibleChangeDate; ?></p>
    </div>
    <?php else: ?>
    <form method="POST" action="/capital/change">
        <div class="form-group">
            <label for="new_capital_planet">Wähle neuen Hauptplaneten:</label>
            <select name="planet_id" id="new_capital_planet" required>
                <option value="">-- Bitte wählen --</option>
                <?php foreach ($playerPlanets as $planet): ?>
                <?php if ($hasCapitalPlanet && $planet->id == $capitalPlanet->id) continue; ?>
                <option value="<?php echo $planet->id; ?>">
                    <?php echo htmlspecialchars($planet->name); ?> [<?php echo $planet->galaxy; ?>:<?php echo $planet->system; ?>:<?php echo $planet->position; ?>]
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="change-warning">
            <p class="warning">Achtung: Ein Hauptplanet-Wechsel hat folgende Konsequenzen:</p>
            <ul>
                <li>Der vorherige Hauptplanet verliert alle Hauptplanet-Boni</li>
                <li>Eine Sperrzeit von <?php echo $capitalChangeCooldownDays; ?> Tagen wird aktiviert</li>
                <li>Laufende Bauprozesse können verzögert werden</li>
            </ul>
        </div>
        
        <div class="form-group">
            <label for="confirmation">Bestätigung:</label>
            <div class="checkbox-container">
                <input type="checkbox" name="confirmation" id="confirmation" required>
                <label for="confirmation">Ich bestätige, dass ich den Hauptplanet wechseln möchte und verstehe die Konsequenzen.</label>
            </div>
        </div>
        
        <button type="submit" class="btn-capital-change">Hauptplanet ändern</button>
    </form>
    <?php endif; ?>
</div>

<!-- Player Planets Overview -->
<div class="player-planets-overview">
    <h3>Deine Planeten</h3>
    
    <div class="planets-grid">
        <?php foreach ($playerPlanets as $planet): ?>
        <div class="planet-card <?php echo ($hasCapitalPlanet && $planet->id == $capitalPlanet->id) ? 'capital' : ''; ?>">
            <div class="planet-header">
                <h4><?php echo htmlspecialchars($planet->name); ?> [<?php echo $planet->galaxy; ?>:<?php echo $planet->system; ?>:<?php echo $planet->position; ?>]</h4>
                <?php if ($hasCapitalPlanet && $planet->id == $capitalPlanet->id): ?>
                <span class="capital-badge">Hauptplanet</span>
                <?php endif; ?>
            </div>
            
            <div class="planet-quick-info">
                <p><strong>Felder:</strong> <?php echo $planet->used_fields; ?>/<?php echo $planet->max_fields; ?></p>
                <p><strong>Temperatur:</strong> <?php echo $planet->temp_min; ?>°C bis <?php echo $planet->temp_max; ?>°C</p>
                <p><strong>Ressourcen:</strong></p>
                <ul class="resource-list">
                    <li>Eisen: <?php echo number_format($planet->metal, 0, ',', '.'); ?></li>
                    <li>Silber: <?php echo number_format($planet->crystal, 0, ',', '.'); ?></li>
                    <li>Uderon: <?php echo number_format($planet->uderon, 0, ',', '.'); ?></li>
                    <li>Wasserstoff: <?php echo number_format($planet->h2, 0, ',', '.'); ?></li>
                </ul>
            </div>
            
            <div class="planet-actions">
                <a href="/buildings?planet_id=<?php echo $planet->id; ?>" class="btn-small">Gebäude</a>
                <a href="/shipyard?planet=<?php echo $planet->id; ?>" class="btn-small">Werft</a>
                <a href="/fleet?planet=<?php echo $planet->id; ?>" class="btn-small">Flotte</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Capital Planet Benefits Information -->
<div class="capital-benefits-info">
    <h3>Vorteile des Hauptplaneten</h3>
    
    <div class="benefits-explanation">
        <p>Der Hauptplanet bietet folgende Vorteile:</p>
        <ul>
            <li>Erhöhte Ressourcenproduktion (+20%)</li>
            <li>Schnellere Forschung (+15%)</li>
            <li>Beschleunigter Schiffbau (+10%)</li>
            <li>Verstärkte planetare Verteidigung (+25%)</li>
            <li>Höhere Priorität bei Flottenbenachrichtigungen</li>
            <li>Bessere Sichtbarkeit und Präsenz in der Galaxie</li>
        </ul>
        <p>Hinweis: Wird der Hauptplanet erobert, wird automatisch ein anderer Planet zum Hauptplaneten, falls verfügbar.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
