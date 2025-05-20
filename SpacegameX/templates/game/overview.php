<?php 
$pageTitle = $pageTitle ?? "Spielübersicht"; 
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<div class="overview-container" style="color: #EBEBEB;">
    <h2 class="head" style="padding:10px; margin-bottom:15px;"><?php echo htmlspecialchars($pageTitle); ?></h2>
    <p style="margin-bottom: 20px;">Willkommen bei SpacegameX, Kommandant <?php echo htmlspecialchars($playerName ?? ''); ?>!</p>

    <div class="planet-selector my-3" style="margin-bottom: 20px; padding: 15px; background-color: #103050;">
        <form method="GET" action="<?php echo BASE_URL; ?>/game/overview" class="form-inline" style="display: flex; align-items: center;">
            <label for="planet_select" style="margin-right: 10px; font-weight: bold;">Planet wechseln:</label>
            <select name="planet_id" id="planet_select" class="form-control" style="margin-right: 10px; padding: 8px; background-color: #0A547C; color: #EBEBEB; border: 1px solid #00FFF6;" onchange="this.form.submit()">
                <?php foreach ($allPlayerPlanets as $p): ?>
                    <option value="<?php echo $p->id; ?>" <?php echo ($p->id == $currentPlanetId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p->name); ?> (<?php echo $p->galaxy; ?>:<?php echo $p->system; ?>:<?php echo $p->position; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="galbutton">Go</button></noscript>
        </form>
    </div>

    <div id="planet-overview" class="rahmen" style="padding: 15px; margin-bottom: 20px; background-color: #0A547C;">
        <h3 class="head" style="padding:10px; margin-bottom:10px;">Aktueller Planet: <?php echo htmlspecialchars($planetName ?? ''); ?> (<?php echo htmlspecialchars($coords ?? ''); ?>)</h3>
        <ul style="list-style: none; padding: 0; display: flex; gap: 15px; flex-wrap: wrap; margin-bottom:15px;">
            <li class="resanzeige" style="padding: 8px 12px; border-radius: 4px;">Eisen: <span id="resource-eisen"><?php echo number_format($eisen ?? 0, 0, ',', '.'); ?></span></li>
            <li class="resanzeige" style="padding: 8px 12px; border-radius: 4px;">Silber: <span id="resource-silber"><?php echo number_format($silber ?? 0, 0, ',', '.'); ?></span></li>
            <li class="resanzeige" style="padding: 8px 12px; border-radius: 4px;">Uderon: <span id="resource-uderon"><?php echo number_format($uderon ?? 0, 0, ',', '.'); ?></span></li>
            <li class="resanzeige" style="padding: 8px 12px; border-radius: 4px;">Wasserstoff: <span id="resource-h2"><?php echo number_format($wasserstoff ?? 0, 0, ',', '.'); ?></span></li>
            <li class="resanzeige" style="padding: 8px 12px; border-radius: 4px;">Energie: <span id="resource-nrg"><?php echo number_format($energie ?? 0, 0, ',', '.'); ?></span>
                <?php if (isset($energyDetails)): ?>
                    <span class="energy-details">(<?php echo number_format($energyDetails['production'] ?? 0); ?> / <?php echo number_format($energyDetails['consumption'] ?? 0); ?>)</span>
                    <?php if ($energyDetails['balance'] < 0): ?>
                        <span class="energy-warning" title="Energiemangel! Produktionseffizienz: <?php echo round($energyDetails['factor'] * 100); ?>%">⚠️</span>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        </ul>
        
        <div class="quick-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo BASE_URL; ?>/buildings" class="galbutton">Gebäude</a>
            <a href="<?php echo BASE_URL; ?>/research" class="galbutton">Forschung</a>
            <a href="<?php echo BASE_URL; ?>/shipyard" class="galbutton">Werft</a>
            <a href="<?php echo BASE_URL; ?>/fleet" class="galbutton">Flotte</a>
            <a href="<?php echo BASE_URL; ?>/galaxy" class="galbutton">Galaxie</a>
        </div>
    </div>

    <div id="fleet-movements" class="rahmen" style="padding: 15px; margin-bottom: 20px; background-color: #0A547C;">
        <h3 class="head" style="padding:10px; margin-bottom:10px;">Flottenbewegungen</h3>
        <?php if (isset($activeFleets) && !empty($activeFleets)): ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th class="head">Herkunft</th>
                        <th class="head">Ziel</th>
                        <th class="head">Mission</th>
                        <th class="head">Ankunft</th>
                        <th class="head">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeFleets as $fleet): ?>
                    <tr>
                        <td> <!-- TD style from wog-2.0.css will apply -->
                            <?php echo htmlspecialchars($fleet->start_planet_name); ?> 
                            [<?php echo $fleet->start_galaxy; ?>:<?php echo $fleet->start_system; ?>:<?php echo $fleet->start_position; ?>]
                        </td>
                        <td>
                            <?php echo htmlspecialchars($fleet->target_planet_name); ?> 
                            [<?php echo $fleet->target_galaxy; ?>:<?php echo $fleet->target_system; ?>:<?php echo $fleet->target_position; ?>]
                        </td>
                        <td>
                            <?php 
                            switch ($fleet->mission_type) {
                                case 'attack': echo 'Angriff'; break;
                                case 'transport': echo 'Transport'; break;
                                case 'colonize': echo 'Kolonisation'; break;
                                case 'station': echo 'Stationierung'; break;
                                case 'espionage': echo 'Spionage'; break;
                                default: echo htmlspecialchars($fleet->mission_type); break;
                            }
                            ?>
                        </td>
                        <td class="countdown" data-finish="<?php echo strtotime($fleet->arrival_time); ?>">
                            <?php 
                                $timeLeft = strtotime($fleet->arrival_time) - time();
                                echo gmdate("H:i:s", max(0, $timeLeft)); 
                            ?>
                        </td>
                        <td>
                            <?php echo $fleet->is_returning ? 'Rückkehr' : 'Hinflug'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Keine aktiven Flottenbewegungen.</p>
        <?php endif; ?>
    </div>

    <div id="building-queue" class="rahmen" style="padding: 15px; margin-bottom: 20px; background-color: #0A547C;">
        <h3 class="head" style="padding:10px; margin-bottom:10px;">Bauwarteschlange</h3>
        <?php if (isset($buildQueue) && !empty($buildQueue)): ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th class="head">Typ</th>
                        <th class="head">Name</th>
                        <th class="head">Level/Anzahl</th>
                        <th class="head">Planet</th>
                        <th class="head">Fertig in</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buildQueue as $item): ?>
                    <tr>
                        <td><?php echo ucfirst(htmlspecialchars($item->item_type)); ?></td>
                        <td><?php echo htmlspecialchars($item->name_de); ?></td>
                        <td><?php echo htmlspecialchars($item->target_level_or_quantity); ?></td>
                        <td>
                            <?php 
                            if (!empty($item->queue_planet_name)) {
                                echo htmlspecialchars($item->queue_planet_name);
                            } elseif ($item->item_type === 'research') {
                                echo 'Reichsweit';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td class="countdown" data-finish="<?php echo strtotime($item->end_time); ?>">
                            <?php 
                                $timeLeft = strtotime($item->end_time) - time();
                                echo gmdate("H:i:s", max(0, $timeLeft)); 
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Keine Gebäude oder Forschungen in Bau.</p>
        <?php endif; ?>
    </div>
</div> <!-- .overview-container -->

<!-- Inline styles removed as they should be covered by wog-2.0.css or specific classes -->
<!-- The script for countdowns is kept -->
<script>
// Simple countdown timer
function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown');
    
    countdowns.forEach(countdown => {
        const finishTime = parseInt(countdown.getAttribute('data-finish'), 10);
        const now = Math.floor(Date.now() / 1000);
        let timeLeft = finishTime - now;
        
        if (timeLeft <= 0) {
            countdown.innerHTML = 'Jetzt!';
            // Refresh page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            let hours = Math.floor(timeLeft / 3600);
            let minutes = Math.floor((timeLeft % 3600) / 60);
            let seconds = timeLeft % 60;
            
            countdown.innerHTML = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    });
}

// Update countdowns every second
setInterval(updateCountdowns, 1000);
updateCountdowns(); // Initial call
</script>

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
