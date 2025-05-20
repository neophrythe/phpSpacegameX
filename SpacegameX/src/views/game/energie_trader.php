<?php
// File: f:\\\\sdi\\\\wog\\\\SpacegameX\\\\src\\\\views\\\\game\\\\energie_trader.php
$this->layout('layouts/main', ['pageTitle' => $pageTitle ?? 'Energie-Trader']);
?>

<h1>Energie-Trader</h1>

<?php if (!isset($_SESSION['player_id'])): ?>
    <p>You need to be logged in to use the Energie-Trader.</p>
    <p><a href="/auth/login">Login</a></p>
    <?php return; ?>
<?php endif; ?>

<?php if (isset($shipyardLevel) && $shipyardLevel < 5): ?>
    <div class="alert alert-warning">
        Your Shipyard (Werft) on the current planet (ID: <?= htmlspecialchars($currentPlanetId ?? 'N/A') ?>) must be at least Level 5 to use the Energie-Trader.
        Current Level: <?= htmlspecialchars($shipyardLevel) ?>
    </div>
<?php else: ?>
    <p>Instantly transfer Energie between your planets. Requires Shipyard Level 5 on the source planet. Transfer costs apply.</p>
    <p>Current planet (Source - ID: <?= htmlspecialchars($currentPlanetId ?? 'N/A') ?>) Shipyard Level: <?= htmlspecialchars($shipyardLevel ?? 'N/A') ?></p>
    <p>Available Energy on source planet: <?= htmlspecialchars(number_format($currentPlanetEnergy ?? 0)) ?></p>

    <form id="energieTraderForm" method="POST" action="/energie-trader/transfer">
        <div class="form-group">
            <label for="source_planet_id">Source Planet:</label>
            <select name="source_planet_id" id="source_planet_id" class="form-control" required>
                <?php foreach ($planets as $planet): ?>
                    <option value="<?= htmlspecialchars($planet['id']) ?>" <?= (isset($currentPlanetId) && $planet['id'] == $currentPlanetId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($planet['name']) ?> (<?= htmlspecialchars($planet['galaxy']) ?>:<?= htmlspecialchars($planet['system']) ?>:<?= htmlspecialchars($planet['position']) ?>) - Energie: <?= htmlspecialchars(number_format($planet['energie'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="target_planet_id">Target Planet:</label>
            <select name="target_planet_id" id="target_planet_id" class="form-control" required>
                <option value="">-- Select Target --</option>
                <?php foreach ($planets as $planet): ?>
                     <?php if (!isset($currentPlanetId) || $planet['id'] != $currentPlanetId): ?>
                        <option value="<?= htmlspecialchars($planet['id']) ?>">
                            <?= htmlspecialchars($planet['name']) ?> (<?= htmlspecialchars($planet['galaxy']) ?>:<?= htmlspecialchars($planet['system']) ?>:<?= htmlspecialchars($planet['position']) ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="amount">Amount of Energy to Transfer:</label>
            <input type="number" name="amount" id="amount" class="form-control" min="1" required placeholder="e.g., 50000">
        </div>
        
        <p class="text-muted small">Transfer Cost: 5% of transferred amount (minimum 2,500 Energie). This cost will be deducted from the source planet in addition to the transferred amount.</p>

        <button type="submit" class="btn btn-primary">Transfer Energie</button>
    </form>

    <div id="energieTraderResult" class="mt-3"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('energieTraderForm');
            const resultDiv = document.getElementById('energieTraderResult');
            const sourcePlanetSelect = document.getElementById('source_planet_id');
            const targetPlanetSelect = document.getElementById('target_planet_id');
            
            // Function to update available energy display for the selected source planet
            function updateSourceEnergyDisplay() {
                const selectedSourceId = sourcePlanetSelect.value;
                const planetsData = <?= json_encode($planets) ?>; // Pass planets data from PHP
                let selectedPlanetEnergy = 0;

                for (let i = 0; i < planetsData.length; i++) {
                    if (planetsData[i].id == selectedSourceId) {
                        selectedPlanetEnergy = planetsData[i].energie;
                        break;
                    }
                }
                // Assuming you have an element to display this, e.g., near the source planet dropdown or form
                // For now, let's update the paragraph above the form if it exists or add one.
                let energyDisplayP = document.getElementById('sourcePlanetEnergyDisplay');
                if (!energyDisplayP) {
                    energyDisplayP = document.createElement('p');
                    energyDisplayP.id = 'sourcePlanetEnergyDisplay';
                    // Insert it after the source planet select, for example
                    sourcePlanetSelect.closest('.form-group').after(energyDisplayP);
                }
                energyDisplayP.innerHTML = 'Available Energy on selected source: ' + new Intl.NumberFormat().format(selectedPlanetEnergy);
            }
            
            // Initial call and on change
            if(sourcePlanetSelect.value) updateSourceEnergyDisplay();
            sourcePlanetSelect.addEventListener('change', updateSourceEnergyDisplay);


            // Update target planet options when source changes
            sourcePlanetSelect.addEventListener('change', function() {
                const selectedSourceId = this.value;
                const currentTargetId = targetPlanetSelect.value;
                
                let newTargetOptions = '<option value="">-- Select Target --</option>';
                <?php foreach ($planets as $planet): ?>
                    if ('<?= htmlspecialchars($planet['id']) ?>' !== selectedSourceId) {
                        newTargetOptions += `<option value="<?= htmlspecialchars($planet['id']) ?>" \${('<?= htmlspecialchars($planet['id']) ?>' === currentTargetId && '<?= htmlspecialchars($planet['id']) ?>' !== selectedSourceId) ? 'selected' : ''}>
                                                <?= htmlspecialchars($planet['name']) ?> (<?= htmlspecialchars($planet['galaxy']) ?>:<?= htmlspecialchars($planet['system']) ?>:<?= htmlspecialchars($planet['position']) ?>)
                                           </option>`;
                    }
                <?php endforeach; ?>
                targetPlanetSelect.innerHTML = newTargetOptions;
            });


            form.addEventListener('submit', function (event) {
                event.preventDefault();
                resultDiv.innerHTML = '<div class="alert alert-info">Processing...</div>';

                const formData = new FormData(form);

                fetch('/energie-trader/transfer', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="alert alert-success">' + escapeHtml(data.message) + '</div>';
                        // Update energy display for source planet
                        // This requires fetching new planet data or making an assumption
                        // For simplicity, we'll just re-trigger the display update which uses initial data
                        // A better way would be to get updated energy from server response
                        updateSourceEnergyDisplay(); 
                        // Potentially refresh all planet data or specific planet's energy value
                    } else {
                        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + escapeHtml(data.error || 'Unknown error') + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultDiv.innerHTML = '<div class="alert alert-danger">An unexpected error occurred. Please check console.</div>';
                });
            });

            function escapeHtml(unsafe) {
                if (unsafe === null || typeof unsafe === 'undefined') return '';
                return unsafe
                    .toString()
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        });
    </script>
<?php endif; ?>

<p><a href="/game/overview">Back to Overview</a></p>
