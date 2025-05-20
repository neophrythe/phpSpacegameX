<?php require_once __DIR__ . '/../layout/header.php'; ?>

<h1><?php echo htmlspecialchars($pageTitle ?? 'Gebäude'); ?></h1>
<p>Spieler: <?php echo htmlspecialchars($playerName ?? 'N/A'); ?></p>

<!-- Planet Selector -->
<div class="planet-selector">
    <form method="GET" action="/game/buildings">
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

<p>Aktueller Planet: <?php echo htmlspecialchars($planetName ?? 'N/A'); ?> (<?php echo htmlspecialchars($coords ?? 'N/A'); ?>)</p>

<!-- Display Resources -->
<div class="resource-display">
    <h3>Ressourcen auf <?php echo htmlspecialchars($planetName ?? 'Planet'); ?>:</h3>
    <p>Eisen: <?php echo htmlspecialchars(round($eisen ?? 0)); ?></p>
    <p>Silber: <?php echo htmlspecialchars(round($silber ?? 0)); ?></p>
    <p>Uderon: <?php echo htmlspecialchars(round($uderon ?? 0)); ?></p>
    <p>Wasserstoff: <?php echo htmlspecialchars(round($wasserstoff ?? 0)); ?></p>
    <p>Energie: <?php echo htmlspecialchars(round($energie ?? 0)); ?>
    <?php if (isset($energyDetails)): ?>
        <span>(<?php echo number_format($energyDetails['production'] ?? 0); ?> Produktion / <?php echo number_format($energyDetails['consumption'] ?? 0); ?> Verbrauch)</span>
        <?php if ($energyDetails['balance'] < 0): ?>
            <span class="energy-warning">⚠️ Energiemangel: Produktionseffizienz nur <?php echo round($energyDetails['factor'] * 100); ?>%</span>
        <?php else: ?>
            <span class="energy-positive">✓ Überschuss: <?php echo number_format($energyDetails['balance'] ?? 0); ?></span>
        <?php endif; ?>
    <?php endif; ?>
    </p>
</div>

<?php if (isset($energyDetails) && !empty($energyDetails['building_contributions'])): ?>
<div class="energy-details">
    <h3>Energieverteilung</h3>
    <table border="1" class="energy-table">
        <thead>
            <tr>
                <th>Gebäude</th>
                <th>Produktion</th>
                <th>Verbrauch</th>
                <th>Bilanz</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($energyDetails['building_contributions'] as $building): ?>
                <tr>
                    <td><?php echo htmlspecialchars($building['name']); ?></td>
                    <td><?php echo number_format($building['production']); ?></td>
                    <td><?php echo number_format($building['consumption']); ?></td>
                    <td><?php echo number_format($building['production'] - $building['consumption']); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="energy-total">
                <td><strong>Gesamt</strong></td>
                <td><strong><?php echo number_format($energyDetails['production']); ?></strong></td>
                <td><strong><?php echo number_format($energyDetails['consumption']); ?></strong></td>
                <td class="<?php echo $energyDetails['balance'] >= 0 ? 'positive-balance' : 'negative-balance'; ?>">
                    <strong><?php echo number_format($energyDetails['balance']); ?></strong>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Success/Error Messages -->
<?php if (isset($success_message) && $success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if (isset($error_message) && $error_message): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<h2>Gebäudeliste</h2>
<table border="1">
    <thead>
        <tr>
            <th>Gebäude</th>
            <th>Beschreibung</th>
            <th>Level</th>
            <th>Nächstes Level Kosten</th>
            <th>Bauzeit</th>
            <th>Aktion</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($allBuildingTypes as $building): ?>
            <?php
                $currentLevel = $playerBuildingLevels[$building->id] ?? 0;
                $nextLevel = $currentLevel + 1;
                $isMaxLevel = ($building->max_level > 0 && $currentLevel >= $building->max_level);
                
                $isUpgrading = false;
                $upgradeEndTime = null;
                if (isset($buildingQueueOnPlanet) && is_array($buildingQueueOnPlanet)) {
                    foreach ($buildingQueueOnPlanet as $item) {
                        if ($item->item_id == $building->id) { // item_type check is already in controller query for buildingQueueOnPlanet
                            $isUpgrading = true;
                            $upgradeEndTime = $item->end_time;
                            break;
                        }
                    }
                }

                // Cost and time calculation
                // Base costs are for level 1. For level 0 to 1, currentLevel is 0.
                $costFactor = $building->cost_factor ?? 1.6; 
                $timeFactor = $building->time_factor ?? 1.5;

                $costEisen = $building->cost_eisen_base * pow($costFactor, $currentLevel);
                $costSilber = $building->cost_silber_base * pow($costFactor, $currentLevel);
                $costUderon = $building->cost_uderon_base * pow($costFactor, $currentLevel);
                $costWasserstoff = $building->cost_wasserstoff_base * pow($costFactor, $currentLevel);
                // $costEnergie = $building->cost_energie_base * pow($costFactor, $currentLevel); // Assuming energy is a requirement not a stored resource for build cost
                $buildTime = $building->build_time_base * pow($timeFactor, $currentLevel); // in seconds
            ?>
            <tr>
                <td><?php echo htmlspecialchars($building->name_de); ?></td>
                <td><?php echo htmlspecialchars($building->description_de); ?></td>
                <td><?php echo htmlspecialchars($currentLevel); ?> / <?php echo htmlspecialchars($building->max_level == 0 ? '∞' : $building->max_level); ?></td>
                <td>
                    <?php if (!$isMaxLevel && !$isUpgrading): ?>
                        Eisen: <?php echo htmlspecialchars(round($costEisen)); ?><br>
                        Silber: <?php echo htmlspecialchars(round($costSilber)); ?><br>
                        Uderon: <?php echo htmlspecialchars(round($costUderon)); ?><br>
                        Wasserstoff: <?php echo htmlspecialchars(round($costWasserstoff)); ?>
                        <?php // Energiebedarf: echo htmlspecialchars(round($costEnergie)); ?>
                    <?php elseif ($isMaxLevel): ?>
                        -
                    <?php elseif ($isUpgrading): ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$isMaxLevel && !$isUpgrading): ?>
                        <?php echo htmlspecialchars(gmdate("H:i:s", (int)$buildTime)); ?>
                    <?php elseif ($isMaxLevel): ?>
                        -
                    <?php elseif ($isUpgrading): ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isUpgrading): ?>
                        In Bau bis: <?php echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($upgradeEndTime))); ?> (Level <?php 
                        // Find the target level from the queue
                        $targetLevelInQueue = $nextLevel; // Default if not found
                        foreach ($buildingQueueOnPlanet as $item) {
                            if ($item->item_id == $building->id) {
                                $targetLevelInQueue = $item->level;
                                break;
                            }
                        }
                        echo htmlspecialchars($targetLevelInQueue);
                        ?>)
                    <?php elseif ($isMaxLevel && $building->max_level > 0): ?>
                        Max Level erreicht
                    <?php else: ?>
                        <form method="POST" action="/game/buildings/upgrade">
                            <input type="hidden" name="building_type_id" value="<?php echo $building->id; ?>">
                            <input type="hidden" name="planet_id" value="<?php echo $currentPlanetId; ?>">
                            <button type="submit" 
                                <?php if (($eisen < $costEisen) || ($silber < $costSilber) || ($uderon < $costUderon) || ($wasserstoff < $costWasserstoff) /* || ($energie_available < $costEnergie) */ ): ?>
                                    disabled title="Nicht genügend Ressourcen"
                                <?php endif; ?>
                            >
                                Ausbauen auf Level <?php echo $nextLevel; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>Aktuelle Bauaufträge auf diesem Planeten:</h3>
<?php if (isset($buildingQueueOnPlanet) && !empty($buildingQueueOnPlanet)): ?>
    <table border="1">
        <thead>
            <tr>
                <th>Gebäude</th>
                <th>Level (Ziel)</th>
                <th>Fertigstellung</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($buildingQueueOnPlanet as $item): ?>
                 <?php
                    $itemName = 'Unbekanntes Gebäude'; // Fallback
                    foreach ($allBuildingTypes as $bType) { // Check against allBuildingTypes passed to view
                        if ($bType->id == $item->item_id) {
                            $itemName = $bType->name_de;
                            break;
                        }
                    }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($itemName); ?></td>
                    <td><?php echo htmlspecialchars($item->level); ?></td>
                    <td><?php echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($item->end_time))); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Keine Bauaufträge auf diesem Planeten aktiv.</p>
<?php endif; ?>


<?php require_once __DIR__ . '/../layout/footer.php'; ?>
