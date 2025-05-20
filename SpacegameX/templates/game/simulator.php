<?php 
$pageTitle = "Kampfsimulator";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<div class="simulator-container">
    <h2>Kampfsimulator</h2>
    <p>Teste Kampfszenarien ohne echte Flotten zu riskieren</p>
    
    <form action="/combat/simulate" method="post" class="simulator-form">
        <div class="simulator-fleets">
            <div class="simulator-fleet attacker">
                <h3>Angreifer</h3>
                
                <div class="fleet-settings">
                    <h4>Forschungen</h4>
                    <div class="research-settings">
                        <div class="research-item">
                            <label for="attacker_weapon">Waffen:</label>
                            <select name="attacker_weapon" id="attacker_weapon">
                                <?php for ($i = 0; $i <= 20; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="research-item">
                            <label for="attacker_shield">Schilde:</label>
                            <select name="attacker_shield" id="attacker_shield">
                                <?php for ($i = 0; $i <= 20; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="research-item">
                            <label for="attacker_armor">Panzerung:</label>
                            <select name="attacker_armor" id="attacker_armor">
                                <?php for ($i = 0; $i <= 20; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <h4>Schiffe</h4>
                    <div class="ship-selection">
                        <table class="ship-table">
                            <tr>
                                <th>Schiff</th>
                                <th>Anzahl</th>
                            </tr>
                            <?php foreach ($shipTypes as $ship): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ship->name_de); ?></td>
                                    <td>
                                        <input type="number" 
                                               name="attacker_ship[<?php echo $ship->id; ?>]" 
                                               value="0" 
                                               min="0" 
                                               class="ship-amount">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        
                        <div class="ship-buttons">
                            <button type="button" onclick="clearShips('attacker')">Keine Schiffe</button>
                            <button type="button" onclick="setShipsPreset('attacker', 'small')">Kleine Flotte</button>
                            <button type="button" onclick="setShipsPreset('attacker', 'medium')">Mittlere Flotte</button>
                            <button type="button" onclick="setShipsPreset('attacker', 'large')">Große Flotte</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="simulator-fleet defender">
                <h3>Verteidiger</h3>
                
                <div class="fleet-settings">
                    <h4>Forschungen</h4>
                    <div class="research-settings">
                        <div class="research-item">
                            <label for="defender_weapon">Waffen:</label>
                            <select name="defender_weapon" id="defender_weapon">
                                <?php for ($i = 0; $i <= 20; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="research-item">
                            <label for="defender_shield">Schilde:</label>
                            <select name="defender_shield" id="defender_shield">
                                <?php for ($i = 0; $i <= 20; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="research-item">
                            <label for="defender_armor">Panzerung:</label>
                            <select name="defender_armor" id="defender_armor">
                                <?php for ($i = 0; $i <= 20; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <h4>Schiffe</h4>
                    <div class="ship-selection">
                        <table class="ship-table">
                            <tr>
                                <th>Schiff</th>
                                <th>Anzahl</th>
                            </tr>
                            <?php foreach ($shipTypes as $ship): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ship->name_de); ?></td>
                                    <td>
                                        <input type="number" 
                                               name="defender_ship[<?php echo $ship->id; ?>]" 
                                               value="0" 
                                               min="0" 
                                               class="ship-amount">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        
                        <div class="ship-buttons">
                            <button type="button" onclick="clearShips('defender')">Keine Schiffe</button>
                            <button type="button" onclick="setShipsPreset('defender', 'small')">Kleine Flotte</button>
                            <button type="button" onclick="setShipsPreset('defender', 'medium')">Mittlere Flotte</button>
                            <button type="button" onclick="setShipsPreset('defender', 'large')">Große Flotte</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="simulator-actions">
            <button type="submit" name="simulate" class="btn-simulate">Kampf simulieren</button>
        </div>
    </form>
</div>

<script>
    // Function to clear all ships for a side
    function clearShips(side) {
        document.querySelectorAll(`input[name^="${side}_ship"]`).forEach(input => {
            input.value = 0;
        });
    }
    
    // Function to set ships based on preset
    function setShipsPreset(side, preset) {
        // Clear current ships
        clearShips(side);
        
        // Get all ship inputs
        const shipInputs = document.querySelectorAll(`input[name^="${side}_ship"]`);
        
        // Set values based on preset
        if (preset === 'small') {
            // Small fleet - mostly light ships
            if (shipInputs.length > 0) shipInputs[0].value = 1000; // Moskito
            if (shipInputs.length > 1) shipInputs[1].value = 500; // Spacejet
            if (shipInputs.length > 2) shipInputs[2].value = 300; // Korvette
            if (shipInputs.length > 3) shipInputs[3].value = 50; // Leichter Kreuzer
        } else if (preset === 'medium') {
            // Medium fleet - mixed ships
            if (shipInputs.length > 0) shipInputs[0].value = 2000; // Moskito
            if (shipInputs.length > 1) shipInputs[1].value = 1000; // Spacejet
            if (shipInputs.length > 2) shipInputs[2].value = 800; // Korvette
            if (shipInputs.length > 3) shipInputs[3].value = 300; // Leichter Kreuzer
            if (shipInputs.length > 4) shipInputs[4].value = 150; // Schwerer Kreuzer
            if (shipInputs.length > 5) shipInputs[5].value = 50; // Schlachtschiff
        } else if (preset === 'large') {
            // Large fleet - heavy ships
            if (shipInputs.length > 0) shipInputs[0].value = 5000; // Moskito
            if (shipInputs.length > 1) shipInputs[1].value = 2500; // Spacejet
            if (shipInputs.length > 2) shipInputs[2].value = 1500; // Korvette
            if (shipInputs.length > 3) shipInputs[3].value = 800; // Leichter Kreuzer
            if (shipInputs.length > 4) shipInputs[4].value = 400; // Schwerer Kreuzer
            if (shipInputs.length > 5) shipInputs[5].value = 200; // Schlachtschiff
            if (shipInputs.length > 6) shipInputs[6].value = 50; // Imp-Klasse
            if (shipInputs.length > 7) shipInputs[7].value = 10; // Galaxy-Klasse
        }
    }
</script>

<style>
    .simulator-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .simulator-fleets {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 20px;
    }
    
    .simulator-fleet {
        flex: 1;
        min-width: 450px;
        background-color: #1a1f2e;
        padding: 20px;
        border-radius: 5px;
    }
    
    .simulator-fleet h3 {
        text-align: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #333;
    }
    
    .attacker h3 {
        color: #4caf50;
    }
    
    .defender h3 {
        color: #f44336;
    }
    
    .research-settings {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .research-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .research-item select {
        padding: 5px;
        background-color: #232738;
        color: #fff;
        border: 1px solid #444;
        border-radius: 3px;
    }
    
    .ship-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }
    
    .ship-table th, .ship-table td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #333;
    }
    
    .ship-amount {
        width: 80px;
        padding: 5px;
        background-color: #232738;
        color: #fff;
        border: 1px solid #444;
        border-radius: 3px;
    }
    
    .ship-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .ship-buttons button {
        padding: 5px 10px;
        background-color: #2a3b56;
        color: #fff;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    
    .simulator-actions {
        margin-top: 30px;
        text-align: center;
    }
    
    .btn-simulate {
        padding: 10px 20px;
        background-color: #4caf50;
        color: #fff;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 16px;
    }
</style>

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
