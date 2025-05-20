<?php 
$pageTitle = "Werft";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<h2>Werft: <?php echo htmlspecialchars($playerName ?? ''); ?></h2>
<p>Planet: <?php echo htmlspecialchars($planet->name); ?> [<?php echo $planet->galaxy; ?>:<?php echo $planet->system; ?>:<?php echo $planet->position; ?>]</p>
<p>Werft-Level: <?php echo $shipyardLevel; ?></p>

<div class="resources">
    <div class="resource">
        <span class="resource-label">Eisen:</span> 
        <span class="resource-value"><?php echo number_format($planet->metal, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Silber:</span> 
        <span class="resource-value"><?php echo number_format($planet->crystal, 0, ',', '.'); ?></span>
    </div>    <div class="resource">
        <span class="resource-label">Wasserstoff:</span> 
        <span class="resource-value"><?php echo number_format($planet->h2, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Energie:</span> 
        <span class="resource-value"><?php echo number_format($planet->nrg, 0, ',', '.'); ?></span>
    </div>
</div>

<div class="planet-selector">
    <form method="get" action="/shipyard">
        <label for="planet-select">Planet wählen:</label>
        <select name="planet" id="planet-select" onchange="this.form.submit()">
            <?php foreach ($planets as $p): ?>
                <option value="<?php echo $p->id; ?>" <?php echo ($p->id == $planet->id) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p->name); ?> [<?php echo $p->galaxy; ?>:<?php echo $p->system; ?>:<?php echo $p->position; ?>]
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="navigation-buttons">
    <a href="/shipyard?planet=<?php echo $planet->id; ?>" class="btn-nav active">Schiffbau</a>
    <a href="/fleet?planet=<?php echo $planet->id; ?>" class="btn-nav">Flottenübersicht</a>
</div>

<?php if (!empty($buildQueue)): ?>
<div class="build-queue">
    <h3>Bauwarteschlange</h3>
    <table class="queue-table">
        <tr>
            <th>Schiffstyp</th>
            <th>Anzahl</th>
            <th>Fertig in</th>
        </tr>
        <?php foreach ($buildQueue as $item): ?>
        <tr>
            <td><?php echo htmlspecialchars($item->name_de); ?></td>
            <td><?php echo htmlspecialchars($item->target_level_or_quantity); ?></td>
            <td class="countdown" data-finish="<?php echo strtotime($item->end_time); ?>">
                <?php 
                    $timeLeft = strtotime($item->end_time) - time();
                    echo gmdate("H:i:s", max(0, $timeLeft)); 
                ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="ships-container">
    <h3>Verfügbare Schiffe</h3>
    <table class="ships-table">
        <tr>
            <th>Schiffstyp</th>
            <th>Bestand</th>
            <th>Kosten</th>
            <th>Kapazität</th>
            <th>Geschwindigkeit</th>
            <th>Kampfwerte</th>
            <th>Bauen</th>
        </tr>
        <?php foreach ($shipTypes as $ship): ?>
        <tr>
            <td><?php echo htmlspecialchars($ship->name_de); ?></td>
            <td>
                <?php 
                $currentQuantity = 0;
                foreach ($currentShips as $cShip) {
                    if ($cShip->ship_type_id == $ship->id) {
                        $currentQuantity = $cShip->quantity;
                        break;
                    }
                }
                echo $currentQuantity; 
                ?>
            </td>
            <td>
                Eisen: <?php echo number_format($ship->base_cost_metal, 0, ',', '.'); ?><br>
                Silber: <?php echo number_format($ship->base_cost_crystal, 0, ',', '.'); ?><br>
                Uderon: <?php echo number_format($ship->base_cost_deuterium, 0, ',', '.'); ?>
            </td>
            <td><?php echo number_format($ship->cargo_capacity, 0, ',', '.'); ?></td>
            <td><?php echo number_format($ship->speed, 0, ',', '.'); ?></td>
            <td>
                <span title="Waffenstärke">W: <?php echo $ship->weapon_power; ?></span><br>
                <span title="Schildstärke">S: <?php echo $ship->shield_power; ?></span><br>
                <span title="Hüllenstärke">H: <?php echo $ship->hull_strength; ?></span>
            </td>
            <td>
                <form method="post" action="/shipyard/build">
                    <input type="hidden" name="planet_id" value="<?php echo $planet->id; ?>">
                    <input type="hidden" name="ship_id" value="<?php echo $ship->id; ?>">
                    <input type="number" name="quantity" value="1" min="1" max="9999" style="width: 60px;">
                    <button type="submit" class="btn-build"
                        <?php echo ($planet->metal < $ship->base_cost_metal || 
                                    $planet->crystal < $ship->base_cost_crystal || 
                                    $planet->deuterium < $ship->base_cost_deuterium) ? 'disabled' : ''; ?>>
                        Bauen
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<script>
// Simple countdown timer
function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown');
    
    countdowns.forEach(countdown => {
        const finishTime = parseInt(countdown.getAttribute('data-finish'), 10);
        const now = Math.floor(Date.now() / 1000);
        let timeLeft = finishTime - now;
        
        if (timeLeft <= 0) {
            countdown.innerHTML = 'Fertig!';
            // Refresh page to show completed ships
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
updateCountdowns();
</script>

<style>
    .ships-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .ships-table th, .ships-table td {
        padding: 8px;
        text-align: center;
        border: 1px solid #444;
    }
    
    .navigation-buttons {
        margin: 20px 0;
        display: flex;
        gap: 10px;
    }
    
    .btn-nav {
        padding: 8px 15px;
        background: #222;
        color: #fff;
        text-decoration: none;
        border-radius: 4px;
    }
    
    .btn-nav.active {
        background: #2a5d8c;
    }
    
    .planet-selector {
        margin-bottom: 20px;
    }
    
    .planet-selector select {
        padding: 5px;
        background: #222;
        color: #fff;
        border: 1px solid #444;
    }
</style>

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
