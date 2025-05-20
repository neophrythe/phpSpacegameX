<?php 
$pageTitle = "Allianz-Forschung";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<h2>Allianz-Forschungszentrum: <?php echo htmlspecialchars($allianceName ?? 'Keine Allianz'); ?></h2>

<?php if (empty($alliance)): ?>
<div class="no-alliance">
    <p>Du bist aktuell in keiner Allianz. Tritt einer Allianz bei oder gründe eine eigene, um auf die Allianz-Forschungen zugreifen zu können.</p>
    <a href="/alliance" class="btn-primary">Zu den Allianzen</a>
</div>
<?php else: ?>

<?php if (isset($successMessage)): ?>
<div class="alert success">
    <?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
<div class="alert error">
    <?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<!-- Alliance Info -->
<div class="alliance-info">
    <p>Mitglieder: <?php echo $memberCount; ?>/<?php echo $maxMembers; ?></p>
    <p>Deine Rolle: <?php echo htmlspecialchars($userRole); ?></p>
    <p>Allianzpunkte: <?php echo number_format($alliancePoints, 0, ',', '.'); ?></p>
    
    <div class="alliance-resources">
        <h3>Allianz-Ressourcen:</h3>
        <div class="resources">
            <div class="resource">
                <span class="resource-label">Eisen:</span> 
                <span class="resource-value"><?php echo number_format($allianceMetal ?? 0, 0, ',', '.'); ?></span>
            </div>
            <div class="resource">
                <span class="resource-label">Silber:</span> 
                <span class="resource-value"><?php echo number_format($allianceCrystal ?? 0, 0, ',', '.'); ?></span>
            </div>
            <div class="resource">
                <span class="resource-label">Uderon:</span> 
                <span class="resource-value"><?php echo number_format($allianceUderon ?? 0, 0, ',', '.'); ?></span>
            </div>
        </div>
        
        <?php if ($canManageResources): ?>
        <div class="resource-management">
            <a href="/alliance/resources" class="btn-primary">Ressourcen verwalten</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($researchQueue)): ?>
<div class="research-queue">
    <h3>Aktuelle Forschungen</h3>
    <table class="queue-table">
        <thead>
            <tr>
                <th>Forschung</th>
                <th>Stufe</th>
                <th>Fortschritt</th>
                <th>Fertig in</th>
                <?php if ($canCancelResearch): ?>
                <th>Aktionen</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($researchQueue as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item->name); ?></td>
                <td><?php echo $item->level; ?></td>
                <td>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo $item->progress; ?>%"></div>
                    </div>
                    <?php echo $item->progress; ?>%
                </td>
                <td><?php echo $item->time_remaining; ?></td>
                <?php if ($canCancelResearch): ?>
                <td>
                    <form method="POST" action="/alliance/research/cancel">
                        <input type="hidden" name="research_id" value="<?php echo $item->id; ?>">
                        <button type="submit" class="btn-small danger">Abbrechen</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Alliance Research -->
<div class="alliance-research">
    <h3>Verfügbare Forschungen</h3>
    
    <div class="research-categories">
        <?php foreach ($researchCategories as $category => $researches): ?>
        <div class="research-category">
            <h4>
                <?php 
                switch($category) {
                    case 'resource':
                        echo 'Ressourcenforschung';
                        break;
                    case 'military':
                        echo 'Militärforschung';
                        break;
                    case 'diplomatic':
                        echo 'Diplomatische Forschung';
                        break;
                    case 'infrastructure':
                        echo 'Infrastrukturforschung';
                        break;
                    default:
                        echo htmlspecialchars($category);
                }
                ?>
            </h4>
            
            <div class="research-items">
                <?php foreach ($researches as $research): ?>
                <div class="research-item <?php echo ($research->can_research ? '' : 'disabled'); ?>">
                    <div class="research-header">
                        <h5><?php echo htmlspecialchars($research->name); ?> (Stufe <?php echo $research->level; ?>)</h5>
                        <?php if ($research->level >= $research->max_level): ?>
                        <span class="max-level">Max Level</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="research-description">
                        <?php echo $research->description; ?>
                    </div>
                    
                    <div class="research-effects">
                        <strong>Aktueller Bonus:</strong>
                        <ul>
                            <?php foreach ($research->current_effects as $effect => $value): ?>
                            <li><?php echo htmlspecialchars($effect); ?>: <?php echo htmlspecialchars($value); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if ($research->level < $research->max_level): ?>
                        <strong>Nächste Stufe:</strong>
                        <ul>
                            <?php foreach ($research->next_level_effects as $effect => $value): ?>
                            <li><?php echo htmlspecialchars($effect); ?>: <?php echo htmlspecialchars($value); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($research->level < $research->max_level && $canStartResearch): ?>
                    <div class="research-costs">
                        <strong>Kosten:</strong>
                        <ul>
                            <li>Eisen: <?php echo number_format($research->costs['metal'], 0, ',', '.'); ?></li>
                            <li>Silber: <?php echo number_format($research->costs['crystal'], 0, ',', '.'); ?></li>
                            <li>Uderon: <?php echo number_format($research->costs['uderon'], 0, ',', '.'); ?></li>
                            <li>Forschungszeit: <?php echo $research->time; ?></li>
                        </ul>
                    </div>
                    
                    <div class="research-requirements">
                        <?php if (!empty($research->requirements)): ?>
                        <strong>Voraussetzungen:</strong>
                        <ul>
                            <?php foreach ($research->requirements as $req): ?>
                            <li class="<?php echo $req->fulfilled ? 'fulfilled' : 'not-fulfilled'; ?>">
                                <?php echo htmlspecialchars($req->name); ?> (Stufe <?php echo $req->required_level; ?>)
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div class="research-action">
                        <form method="POST" action="/alliance/research/start">
                            <input type="hidden" name="research_id" value="<?php echo $research->id; ?>">
                            <button type="submit" class="btn-research" <?php echo (!$research->can_research || !empty($researchQueue) ? 'disabled' : ''); ?>>
                                <?php echo (!empty($researchQueue) ? 'Forschung in Bearbeitung' : 'Forschung starten'); ?>
                            </button>
                            
                            <?php if (!$research->can_research && empty($researchQueue)): ?>
                            <p class="requirements-note">Voraussetzungen nicht erfüllt oder nicht genügend Ressourcen.</p>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="alliance-research-bonuses">
    <h3>Aktuelle Allianz-Boni</h3>
    
    <table class="bonuses-table">
        <thead>
            <tr>
                <th>Bonus-Kategorie</th>
                <th>Wert</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Ressourcenproduktion</td>
                <td>+<?php echo $allianceBonuses['resource_production']; ?>%</td>
            </tr>
            <tr>
                <td>Forschungsgeschwindigkeit</td>
                <td>+<?php echo $allianceBonuses['research_speed']; ?>%</td>
            </tr>
            <tr>
                <td>Schiffbaugeschwindigkeit</td>
                <td>+<?php echo $allianceBonuses['construction_speed']; ?>%</td>
            </tr>
            <tr>
                <td>Schiffsangriff</td>
                <td>+<?php echo $allianceBonuses['attack_bonus']; ?>%</td>
            </tr>
            <tr>
                <td>Schiffsverteidigung</td>
                <td>+<?php echo $allianceBonuses['defense_bonus']; ?>%</td>
            </tr>
            <tr>
                <td>Spionageabwehr</td>
                <td>+<?php echo $allianceBonuses['espionage_defense']; ?>%</td>
            </tr>
            <tr>
                <td>Fluggeschwindigkeit</td>
                <td>+<?php echo $allianceBonuses['flight_speed']; ?>%</td>
            </tr>
            <tr>
                <td>Handelsbonus</td>
                <td>+<?php echo $allianceBonuses['trade_bonus']; ?>%</td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
