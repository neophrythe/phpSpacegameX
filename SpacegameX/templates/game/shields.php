<?php 
$pageTitle = "Planetare Schilde";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<h2>Planetare Verteidigung: <?php echo htmlspecialchars($planetName ?? ''); ?> (<?php echo htmlspecialchars($coords ?? ''); ?>)</h2>

<!-- Planet Selector -->
<div class="planet-selector">
    <form method="GET" action="/shields">
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

<!-- Shield Status -->
<div class="shield-status">
    <h3>Schildstatus</h3>
    
    <?php if ($shieldActive): ?>
    <div class="shield-active">
        <div class="shield-indicator active">
            <span class="shield-icon">🛡️</span>
            <span class="shield-text">Schild aktiviert</span>
        </div>
        <div class="shield-details">
            <p>Verbleibende Zeit: <?php echo $shieldTimeRemaining; ?></p>
            <p>Schildstärke: <?php echo number_format($shieldStrength, 0); ?></p>
        </div>
        
        <form method="POST" action="/shields/deactivate">
            <input type="hidden" name="planet_id" value="<?php echo $currentPlanetId; ?>">
            <button type="submit" class="btn-shield deactivate">Schild deaktivieren</button>
        </form>
    </div>
    <?php else: ?>
    <div class="shield-inactive">
        <div class="shield-indicator inactive">
            <span class="shield-icon">🛡️</span>
            <span class="shield-text">Schild deaktiviert</span>
        </div>
        
        <?php if ($canActivateShield): ?>
        <div class="shield-activation">
            <form method="POST" action="/shields/activate">
                <input type="hidden" name="planet_id" value="<?php echo $currentPlanetId; ?>">
                <div class="shield-costs">
                    <p>Aktivierungskosten:</p>
                    <ul>
                        <li>Eisen: <?php echo number_format($shieldCosts['metal'], 0, ',', '.'); ?></li>
                        <li>Silber: <?php echo number_format($shieldCosts['crystal'], 0, ',', '.'); ?></li>
                        <li>Uderon: <?php echo number_format($shieldCosts['uderon'], 0, ',', '.'); ?></li>
                        <li>Energie: <?php echo number_format($shieldCosts['energy'], 0, ',', '.'); ?></li>
                    </ul>
                </div>
                <button type="submit" class="btn-shield activate" <?php echo ($notEnoughResources ? 'disabled' : ''); ?>>
                    Schild aktivieren
                </button>
                <?php if ($notEnoughResources): ?>
                <p class="resources-warning">Nicht genügend Ressourcen vorhanden!</p>
                <?php endif; ?>
            </form>
        </div>
        <?php else: ?>
        <p class="cooldown-warning">Schildgenerator im Cooldown. Verfügbar in: <?php echo $cooldownRemaining; ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Shield Research -->
<div class="shield-research">
    <h3>Schildforschung</h3>
    <div class="research-info">
        <p>Schildtechnologie-Level: <?php echo $shieldResearchLevel; ?></p>
        <div class="research-benefits">
            <p>Aktuelle Bonusse:</p>
            <ul>
                <li>Schildstärke: +<?php echo ($shieldResearchLevel * 10); ?>%</li>
                <li>Energieeffizienz: +<?php echo ($shieldResearchLevel * 5); ?>%</li>
                <?php if ($shieldResearchLevel >= 5): ?>
                <li>Schildregeneration: <?php echo ($shieldResearchLevel - 4); ?>% pro Stunde</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <?php if ($shieldResearchLevel < $maxShieldResearchLevel): ?>
    <div class="research-upgrade">
        <p>Nächste Stufe:</p>
        <ul>
            <li>Schildstärke: +<?php echo (($shieldResearchLevel + 1) * 10); ?>%</li>
            <li>Energieeffizienz: +<?php echo (($shieldResearchLevel + 1) * 5); ?>%</li>
            <?php if (($shieldResearchLevel + 1) >= 5): ?>
            <li>Schildregeneration: <?php echo (($shieldResearchLevel + 1) - 4); ?>% pro Stunde</li>
            <?php endif; ?>
        </ul>
        <p><a href="/research" class="btn-research">Zur Forschung</a></p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
