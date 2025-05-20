<?php 
$pageTitle = "Flotte";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<h2>Flotte: <?php echo htmlspecialchars($playerName ?? ''); ?></h2>
<p>Planet: <?php echo htmlspecialchars($planet->name); ?> [<?php echo $planet->galaxy; ?>:<?php echo $planet->system; ?>:<?php echo $planet->position; ?>]</p>

<div class="resources">    <div class="resource">
        <span class="resource-label">Eisen:</span> 
        <span class="resource-value"><?php echo number_format($planet->metal, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Silber:</span> 
        <span class="resource-value"><?php echo number_format($planet->crystal, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Wasserstoff:</span> 
        <span class="resource-value"><?php echo number_format($planet->h2, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Energie:</span> 
        <span class="resource-value"><?php echo number_format($planet->nrg, 0, ',', '.'); ?></span>
    </div>
</div>

<div class="planet-selector">
    <form method="get" action="/fleet">
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
    <a href="/shipyard?planet=<?php echo $planet->id; ?>" class="btn-nav">Schiffbau</a>
    <a href="/fleet?planet=<?php echo $planet->id; ?>" class="btn-nav active">Flottenübersicht</a>
</div>

<?php if (isset($_SESSION['fleet_error'])): ?>
<div class="error-message">
    <?php echo $_SESSION['fleet_error']; unset($_SESSION['fleet_error']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['fleet_success'])): ?>
<div class="success-message">
    <?php echo $_SESSION['fleet_success']; unset($_SESSION['fleet_success']); ?>
</div>
<?php endif; ?>

<?php if (!empty($activeFleets)): ?>
<div class="active-fleets">
    <h3>Aktive Flotten</h3>
    <table class="fleets-table">
        <tr>
            <th>Herkunft</th>
            <th>Ziel</th>
            <th>Mission</th>
            <th>Ankunft</th>
            <th>Rückkehr</th>
            <th>Status</th>
        </tr>
        <?php foreach ($activeFleets as $fleet): ?>
        <tr>
            <td>
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
                    default: echo $fleet->mission_type; break;
                }
                ?>
            </td>
            <td class="countdown" data-finish="<?php echo strtotime($fleet->arrival_time); ?>">
                <?php 
                    $timeLeft = strtotime($fleet->arrival_time) - time();
                    echo gmdate("H:i:s", max(0, $timeLeft)); 
                ?>
            </td>
            <td class="countdown" data-finish="<?php echo strtotime($fleet->return_time); ?>">
                <?php 
                    $timeLeft = strtotime($fleet->return_time) - time();
                    echo gmdate("H:i:s", max(0, $timeLeft)); 
                ?>
            </td>
            <td>
                <?php echo $fleet->is_returning ? 'Rückkehr' : 'Hinflug'; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<h3>Flotte entsenden</h3>

<form method="post" action="/shipyard/send" id="send-fleet-form">
    <input type="hidden" name="planet_id" value="<?php echo $planet->id; ?>">
    
    <div class="form-section">
        <h4>1. Schiffe auswählen</h4>
        <?php if (empty($shipsOnPlanet)): ?>
            <p>Keine Schiffe verfügbar auf diesem Planeten.</p>
        <?php else: ?>
            <table class="ships-select-table">
                <tr>
                    <th>Schiffstyp</th>
                    <th>Verfügbar</th>
                    <th>Auswählen</th>
                </tr>
                <?php foreach ($shipsOnPlanet as $ship): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ship->name_de); ?></td>
                    <td><?php echo $ship->quantity; ?></td>
                    <td>
                        <input type="number" 
                               name="ship_<?php echo $ship->ship_type_id; ?>" 
                               value="0" 
                               min="0" 
                               max="<?php echo $ship->quantity; ?>"
                               class="ship-quantity">
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <div class="ship-buttons">
                <button type="button" onclick="maxShips()">Alle Schiffe</button>
                <button type="button" onclick="noShips()">Keine Schiffe</button>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="form-section">
        <h4>2. Ziel auswählen</h4>
        <div class="target-coords">
            <label>Galaxie:</label>
            <input type="number" name="target_galaxy" value="<?php echo $targetGalaxy; ?>" min="1" max="9">
            
            <label>System:</label>
            <input type="number" name="target_system" value="<?php echo $targetSystem; ?>" min="1" max="499">
            
            <label>Position:</label>
            <input type="number" name="target_position" value="<?php echo $targetPosition; ?>" min="1" max="15">
        </div>
        <div class="target-shortcuts">
            <h5>Eigene Planeten:</h5>
            <div class="planet-buttons">
                <?php foreach ($planets as $p): ?>
                    <button type="button" class="planet-shortcut" 
                            data-galaxy="<?php echo $p->galaxy; ?>" 
                            data-system="<?php echo $p->system; ?>" 
                            data-position="<?php echo $p->position; ?>">
                        <?php echo htmlspecialchars($p->name); ?> [<?php echo $p->galaxy; ?>:<?php echo $p->system; ?>:<?php echo $p->position; ?>]
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="form-section">
        <h4>3. Mission wählen</h4>
        <div class="mission-select">
            <label>
                <input type="radio" name="mission" value="attack" <?php echo ($missionType == 'attack') ? 'checked' : ''; ?>> Angriff
            </label>
            <label>
                <input type="radio" name="mission" value="transport" <?php echo ($missionType == 'transport') ? 'checked' : ''; ?>> Transport
            </label>
            <label>
                <input type="radio" name="mission" value="colonize" <?php echo ($missionType == 'colonize') ? 'checked' : ''; ?>> Kolonisieren
            </label>
            <label>
                <input type="radio" name="mission" value="station" <?php echo ($missionType == 'station') ? 'checked' : ''; ?>> Stationieren
            </label>
            <label>
                <input type="radio" name="mission" value="espionage" <?php echo ($missionType == 'espionage') ? 'checked' : ''; ?>> Spionage
            </label>
        </div>
    </div>
    
    <div class="form-section" id="resources-section">
        <h4>4. Ressourcen auswählen (nur für Transport)</h4>
        <div class="resources-select">
            <label>Eisen:</label>
            <input type="number" name="metal" value="0" min="0" max="<?php echo $planet->metal; ?>">
            
            <label>Silber:</label>
            <input type="number" name="crystal" value="0" min="0" max="<?php echo $planet->crystal; ?>">
            
            <label>Uderon:</label>
            <input type="number" name="deuterium" value="0" min="0" max="<?php echo $planet->deuterium; ?>">
            
            <div class="resource-buttons">
                <button type="button" onclick="maxResources()">Alle Ressourcen</button>
                <button type="button" onclick="noResources()">Keine Ressourcen</button>
            </div>
        </div>
    </div>
    
    <div class="form-submit">
        <button type="submit" class="btn-send-fleet">Flotte entsenden</button>
    </div>
</form>

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
            // Refresh page to show updated fleet status
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

// Update mission dependent sections
function updateMissionForm() {
    const mission = document.querySelector('input[name="mission"]:checked').value;
    const resourcesSection = document.getElementById('resources-section');
    
    if (mission === 'transport') {
        resourcesSection.style.display = 'block';
    } else {
        resourcesSection.style.display = 'none';
    }
}

// Planet shortcuts
document.querySelectorAll('.planet-shortcut').forEach(button => {
    button.addEventListener('click', function() {
        document.querySelector('input[name="target_galaxy"]').value = this.dataset.galaxy;
        document.querySelector('input[name="target_system"]').value = this.dataset.system;
        document.querySelector('input[name="target_position"]').value = this.dataset.position;
    });
});

// Select all ships
function maxShips() {
    document.querySelectorAll('.ship-quantity').forEach(input => {
        input.value = input.max;
    });
}

// Select no ships
function noShips() {
    document.querySelectorAll('.ship-quantity').forEach(input => {
        input.value = 0;
    });
}

// Select all resources
function maxResources() {
    document.querySelector('input[name="metal"]').value = <?php echo floor($planet->metal); ?>;
    document.querySelector('input[name="crystal"]').value = <?php echo floor($planet->crystal); ?>;
    document.querySelector('input[name="deuterium"]').value = <?php echo floor($planet->deuterium) - 500; // Reserve some fuel ?>;
}

// Select no resources
function noResources() {
    document.querySelector('input[name="metal"]').value = 0;
    document.querySelector('input[name="crystal"]').value = 0;
    document.querySelector('input[name="deuterium"]').value = 0;
}

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    updateMissionForm();
    
    // Add listeners for mission changes
    document.querySelectorAll('input[name="mission"]').forEach(radio => {
        radio.addEventListener('change', updateMissionForm);
    });
    
    // Form validation
    document.getElementById('send-fleet-form').addEventListener('submit', function(e) {
        let shipsSelected = false;
        document.querySelectorAll('.ship-quantity').forEach(input => {
            if (parseInt(input.value) > 0) {
                shipsSelected = true;
            }
        });
        
        if (!shipsSelected) {
            e.preventDefault();
            alert('Bitte wähle mindestens ein Schiff aus.');
        }
    });
});

// Update countdowns every second
setInterval(updateCountdowns, 1000);
updateCountdowns();
</script>

<style>
    .fleets-table, .ships-select-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .fleets-table th, .fleets-table td,
    .ships-select-table th, .ships-select-table td {
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
    
    .form-section {
        margin: 20px 0;
        padding: 15px;
        background: #212330;
        border-radius: 5px;
    }
    
    .target-coords, .resources-select {
        display: flex;
        gap: 10px;
        align-items: center;
        margin: 10px 0;
    }
    
    .target-coords input, .resources-select input {
        width: 70px;
        padding: 5px;
        background: #181920;
        border: 1px solid #444;
        color: #fff;
    }
    
    .mission-select {
        display: flex;
        gap: 20px;
        margin: 10px 0;
    }
    
    .form-submit {
        margin: 20px 0;
    }
    
    .btn-send-fleet {
        padding: 10px 20px;
        background: #2c6e2c;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .ship-buttons, .resource-buttons {
        margin-top: 10px;
        display: flex;
        gap: 10px;
    }
    
    .planet-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }
    
    .planet-shortcut {
        padding: 5px 10px;
        background: #2a3b56;
        border: none;
        color: #fff;
        cursor: pointer;
        border-radius: 3px;
    }
    
    .error-message {
        padding: 10px;
        background: #6e2c2c;
        color: #fff;
        margin: 10px 0;
        border-radius: 4px;
    }
    
    .success-message {
        padding: 10px;
        background: #2c6e2c;
        color: #fff;
        margin: 10px 0;
        border-radius: 4px;
    }
</style>

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
