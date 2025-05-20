\
<?php
// File: f:\\sdi\\wog\\SpacegameX\\src\\views\\game\\transmitter.php
// Inherit main layout
$this->layout('layouts/main', ['pageTitle' => $pageTitle ?? 'Transmitter']);
?>

<h1>Transmitter</h1>

<?php if (!isset($_SESSION['player_id'])): ?>
    <p>You need to be logged in to use the transmitter.</p>
    <p><a href="/auth/login">Login</a></p>
    <?php return; ?>
<?php endif; ?>

<?php if (isset($shipyardLevel) && $shipyardLevel < 5): ?>
    <div class="alert alert-warning">
        Your Shipyard (Werft) on the current planet (ID: <?= htmlspecialchars($currentPlanetId ?? 'N/A') ?>) must be at least Level 5 to use the Transmitter.
        Current Level: <?= htmlspecialchars($shipyardLevel) ?>
    </div>
<?php else: ?>
    <p>Instantly transfer resources between your planets. This requires a Shipyard Level 5 on the source planet and consumes very high energy.</p>
    <p>Current planet (Source - ID: <?= htmlspecialchars($currentPlanetId ?? 'N/A') ?>) Shipyard Level: <?= htmlspecialchars($shipyardLevel ?? 'N/A') ?></p>
    <p>Available Energy on source planet: <?= htmlspecialchars(number_format($currentPlanetEnergy ?? 0)) ?></p>

    <form id="transmitterForm" method="POST" action="/transmitter/transmit">
        <div class="form-group">
            <label for="source_planet_id">Source Planet:</label>
            <select name="source_planet_id" id="source_planet_id" class="form-control" required>
                <?php foreach ($planets as $planet): ?>
                    <option value="<?= htmlspecialchars($planet['id']) ?>" <?= (isset($currentPlanetId) && $planet['id'] == $currentPlanetId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($planet['name']) ?> (<?= htmlspecialchars($planet['galaxy']) ?>:<?= htmlspecialchars($planet['system']) ?>:<?= htmlspecialchars($planet['position']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="target_planet_id">Target Planet:</label>
            <select name="target_planet_id" id="target_planet_id" class="form-control" required>
                <option value="">-- Select Target --</option>
                <?php foreach ($planets as $planet): ?>
                    <?php if (!isset($currentPlanetId) || $planet['id'] != $currentPlanetId): // Cannot be same as source ?>
                        <option value="<?= htmlspecialchars($planet['id']) ?>">
                            <?= htmlspecialchars($planet['name']) ?> (<?= htmlspecialchars($planet['galaxy']) ?>:<?= htmlspecialchars($planet['system']) ?>:<?= htmlspecialchars($planet['position']) ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="resource_type">Resource Type:</label>
            <select name="resource_type" id="resource_type" class="form-control" required>
                <option value="eisen">Eisen</option>
                <option value="silber">Silber</option>
                <option value="gold">Gold</option>
                <option value="uderon">Uderon</option>
                <option value="wasserstoff">Wasserstoff</option>
                <option value="antimaterie">Antimaterie</option>
                <!-- Add other transmittable resources as needed -->
            </select>
        </div>

        <div class="form-group">
            <label for="amount">Amount:</label>
            <input type="number" name="amount" id="amount" class="form-control" min="1" required placeholder="e.g., 100000">
        </div>
        
        <p class="text-muted small">Estimated energy cost will be calculated upon submission. Minimum 5,000 Energie. 1 Energie per 10 units of resource.</p>

        <button type="submit" class="btn btn-primary">Transmit Resources</button>
    </form>

    <div id="transmitterResult" class="mt-3"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('transmitterForm');
            const resultDiv = document.getElementById('transmitterResult');
            const sourcePlanetSelect = document.getElementById('source_planet_id');
            const targetPlanetSelect = document.getElementById('target_planet_id');

            // Update target planet options when source changes to prevent same source/target
            sourcePlanetSelect.addEventListener('change', function() {
                const selectedSourceId = this.value;
                // Store current target selection
                const currentTargetId = targetPlanetSelect.value;
                
                // Re-populate target options
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

                fetch('/transmitter/transmit', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="alert alert-success">' + escapeHtml(data.message) + '</div>';
                        // Optionally, update resource displays on the page here
                        // e.g., fetch new planet data or update displayed values
                        // Consider updating available energy on source planet display
                        if (document.getElementById('currentPlanetEnergy')) {
                            // This would require fetching the new energy value
                        }
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
