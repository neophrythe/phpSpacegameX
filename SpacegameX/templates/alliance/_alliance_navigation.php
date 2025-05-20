<?php
// templates/alliance/_alliance_navigation.php

$currentAllianceId = null;
if (isset($_SESSION['player_id'])) {
    $allianceMembership = \Models\AllianceMember::findByPlayerId($_SESSION['player_id']);
    if ($allianceMembership) {
        $currentAllianceId = $allianceMembership->alliance_id;
    }
}
?>
<nav class="nav nav-pills flex-column flex-sm-row mb-3">
    <a class="flex-sm-fill text-sm-center nav-link" href="/alliance">Alliance Overview</a>
    <?php if ($currentAllianceId): ?>
        <a class="flex-sm-fill text-sm-center nav-link" href="/alliance/members">Members</a>
        <a class="flex-sm-fill text-sm-center nav-link <?= (strpos($_SERVER['REQUEST_URI'], '/alliance/messages') !== false) ? 'active' : '' ?>" href="/alliance/messages">Messages</a>
        <a class="flex-sm-fill text-sm-center nav-link" href="/alliance/research">Research</a> 
        <a class="flex-sm-fill text-sm-center nav-link" href="/alliance/settings">Settings</a>
    <?php endif; ?>
</nav>
