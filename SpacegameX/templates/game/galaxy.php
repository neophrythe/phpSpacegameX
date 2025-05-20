<?php 
$pageTitle = "Galaxieansicht";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<div class="container">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    <p>Logged in as: <?php echo htmlspecialchars($playerName); ?></p>

    <div class="galaxy-navigation my-3">
        <form method="GET" action="/game/galaxy" class="form-inline">
            <div class="form-group mx-sm-3 mb-2">
                <label for="galaxy_input" class="sr-only">Galaxy</label>
                <input type="number" class="form-control" id="galaxy_input" name="galaxy" min="1" max="<?php echo $maxGalaxies; ?>" value="<?php echo $currentGalaxy; ?>">
            </div>
            <div class="form-group mx-sm-3 mb-2">
                <label for="system_input" class="sr-only">System</label>
                <input type="number" class="form-control" id="system_input" name="system" min="1" max="<?php echo $maxSystems; ?>" value="<?php echo $currentSystem; ?>">
            </div>
            <button type="submit" class="btn btn-primary mb-2">Go</button>
        </form>
    </div>
    
    <div class="galaxy-navigation-links my-3">
        <?php if ($currentSystem > 1): ?>
            <a href="/game/galaxy?galaxy=<?php echo $currentGalaxy; ?>&system=<?php echo $currentSystem - 1; ?>" class="btn btn-secondary">&lt; Prev System</a>
        <?php endif; ?>
        <?php if ($currentSystem < $maxSystems): ?>
            <a href="/game/galaxy?galaxy=<?php echo $currentGalaxy; ?>&system=<?php echo $currentSystem + 1; ?>" class="btn btn-secondary">Next System &gt;</a>
        <?php endif; ?>
    </div>


    <h2>Planets in System <?php echo $currentGalaxy; ?>:<?php echo $currentSystem; ?></h2>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Position</th>
                <th>Planet Name</th>
                <th>Owner</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($systemPlanets)): ?>
                <?php foreach ($systemPlanets as $planet): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($planet['position']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($planet['name']); ?>
                            <?php if (isset($planet['is_capital']) && $planet['is_capital']): ?>
                                (HP)
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($planet['player_name']) && $planet['player_name']): ?>
                                <?php echo htmlspecialchars($planet['player_name']); ?>
                            <?php else: ?>
                                <em>Uninhabited</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($planet['player_id']) && $planet['player_id'] == $_SESSION['user_id']): ?>
                                <a href="/game/overview?planet_id=<?php echo $planet['id']; ?>" class="btn btn-sm btn-info">View</a>
                            <?php elseif (isset($planet['is_empty_slot']) && $planet['is_empty_slot']): ?>
                                <!-- Check if player has colonization tech & colony ship -->
                                <a href="/game/colonize?galaxy=<?php echo $planet['galaxy']; ?>&system=<?php echo $planet['system']; ?>&position=<?php echo $planet['position']; ?>" class="btn btn-sm btn-success">Colonize</a>
                            <?php elseif (isset($planet['player_id']) && $planet['player_id'] != null): // Occupied by another player ?>
                                <a href="/game/espionage?target_planet_id=<?php echo $planet['id']; ?>" class="btn btn-sm btn-warning">Spy</a>
                                <a href="/game/fleet?action=attack&target_planet_id=<?php echo $planet['id']; ?>" class="btn btn-sm btn-danger">Attack</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No planets found in this system or system data is unavailable.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
