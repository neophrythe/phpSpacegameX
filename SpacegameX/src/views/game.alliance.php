<?php
// templates/game/alliance.php (assuming this is the view loaded by AllianceController::index)

// Include the alliance navigation
require_once __DIR__ . '/../../templates/alliance/_alliance_navigation.php';

?>

<div class="container">
    <h2>Alliance Overview</h2>

    <?php if ($alliance): ?>
        <p>Welcome to the alliance page for <strong><?= htmlspecialchars($alliance->name) ?> [<?= htmlspecialchars($alliance->tag) ?>]</strong>.</p>
        
        <!-- Display general alliance information here -->
        <p>Description: <?= htmlspecialchars($alliance->description ?? 'No description available.') ?></p>
        <p>Leader: <?= htmlspecialchars($alliance->getLeaderName() ?? 'N/A') ?></p>
        <p>Members: <?= count($allianceMembers) ?></p>

        <h4>Treasury:</h4>
        <ul>
            <li>Eisen: <?= number_format($allianceTreasury['eisen']) ?></li>
            <li>Silber: <?= number_format($allianceTreasury['silber']) ?></li>
            <li>Uderon: <?= number_format($allianceTreasury['uderon']) ?></li>
            <li>Wasserstoff: <?= number_format($allianceTreasury['wasserstoff']) ?></li>
            <li>Energie: <?= number_format($allianceTreasury['energie']) ?></li>
        </ul>

        <!-- Further sections for buildings, research, members list can be linked or partially displayed here -->

    <?php else: ?>
        <p>You are not currently in an alliance.</p>
        <h3>Join an Existing Alliance</h3>
        <?php if (!empty($alliancesToJoin)): ?>
            <ul>
                <?php foreach ($alliancesToJoin as $ally): ?>
                    <li>
                        <?= htmlspecialchars($ally->name) ?> [<?= htmlspecialchars($ally->tag) ?>] - <?= htmlspecialchars($ally->description ?? 'No description') ?>
                        <form action="/alliance/join" method="post" style="display: inline;">
                            <input type="hidden" name="alliance_id" value="<?= $ally->id ?>">
                            <button type="submit">Join</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>There are no alliances to join at the moment.</p>
        <?php endif; ?>

        <h3>Create a New Alliance</h3>
        <form action="/alliance/create" method="post">
            <div>
                <label for="name">Alliance Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div>
                <label for="tag">Alliance Tag:</label>
                <input type="text" id="tag" name="tag" required maxlength="5">
            </div>
            <div>
                <label for="description">Description (optional):</label>
                <textarea id="description" name="description"></textarea>
            </div>
            <button type="submit">Create Alliance</button>
        </form>
    <?php endif; ?>
</div>
