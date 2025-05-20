<?php 
$pageTitle = "Forschung";
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<h2>Forschungszentrum: <?php echo htmlspecialchars($playerName ?? ''); ?></h2>
<p>Forschungslabor-Level: <?php echo $labLevel; ?></p>

<?php if (isset($success_message)): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="resources">    
    <div class="resource">
        <span class="resource-label">Eisen:</span> 
        <span class="resource-value"><?php echo number_format($eisen, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Silber:</span> 
        <span class="resource-value"><?php echo number_format($silber, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Uderon:</span> 
        <span class="resource-value"><?php echo number_format($uderon, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Wasserstoff:</span> 
        <span class="resource-value"><?php echo number_format($wasserstoff, 0, ',', '.'); ?></span>
    </div>
    <div class="resource">
        <span class="resource-label">Energie:</span> 
        <span class="resource-value"><?php echo number_format($energie, 0, ',', '.'); ?></span>
    </div>
</div>

<?php if (!empty($researchQueue)): ?>
<div class="research-queue">
    <h3>Forschungswarteschlange</h3>
    <table class="queue-table">
        <tr>
            <th>Forschung</th>
            <th>Stufe</th>
            <th>Fertig in</th>
            <th>Aktionen</th>
        </tr>
        <?php foreach ($researchQueue as $item): ?>
        <tr>
            <td><?php echo htmlspecialchars($item->name_de); ?></td>
            <td><?php echo htmlspecialchars($item->target_level_or_quantity); ?></td>
            <td class="countdown" data-finish="<?php echo strtotime($item->end_time); ?>">
                <?php 
                    $timeLeft = strtotime($item->end_time) - time();
                    echo gmdate("H:i:s", max(0, $timeLeft)); 
                ?>
            </td>
            <td>
                <form method="post" action="/game/research/cancel">
                    <input type="hidden" name="queue_id" value="<?php echo $item->id; ?>">
                    <button type="submit" class="btn-cancel">Abbrechen</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="research-list">
    <h3>Verfügbare Forschungen</h3>
    <?php foreach ($playerResearch as $tech): ?>
    <div class="research-item <?php echo $tech->is_under_research ? 'researching' : ''; ?>">
        <h4><?php echo htmlspecialchars($tech->name_de); ?> (Stufe <?php echo $tech->level; ?>)</h4>
        <p><?php echo htmlspecialchars($tech->description_de ?? ''); ?></p>
        
        <?php if (!$tech->is_under_research): ?>
        <?php
            // Find the matching static research type for accurate cost calculation
            $staticResearchType = null;
            foreach ($allResearchTypes as $rt) {
                if ($rt->id == $tech->research_type_id) {
                    $staticResearchType = $rt;
                    break;
                }
            }
            
            if ($staticResearchType) {
                $nextLevel = $tech->level + 1;
                $cost = $staticResearchType->getCostAtLevel($tech->level);
                $researchTime = $staticResearchType->getResearchTimeAtLevel($tech->level, $labLevel);
                $researchTimeFormatted = gmdate("H:i:s", $researchTime);
            } else {
                $cost = ['eisen' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0, 'energie' => 0];
                $researchTimeFormatted = "00:00:00";
            }
        ?>
          <div class="research-costs">
            <span>Kosten für Stufe <?php echo $nextLevel; ?>:</span>
            <ul>
                <li>Eisen: <?php echo number_format($cost['eisen'], 0, ',', '.'); ?></li>
                <li>Silber: <?php echo number_format($cost['silber'], 0, ',', '.'); ?></li>
                <li>Uderon: <?php echo number_format($cost['uderon'], 0, ',', '.'); ?></li>
                <li>Wasserstoff: <?php echo number_format($cost['wasserstoff'], 0, ',', '.'); ?></li>
                <?php if ($cost['energie'] > 0): ?>
                <li>Energie: <?php echo number_format($cost['energie'], 0, ',', '.'); ?></li>
                <?php endif; ?>
            </ul>
            <p>Forschungszeit: <?php echo $researchTimeFormatted; ?></p>
        </div>
        
        <form method="post" action="/game/research/start">
            <input type="hidden" name="research_type_id" value="<?php echo $tech->research_type_id; ?>">
            <button type="submit" class="btn-research" <?php echo (
                $eisen < $cost['eisen'] || 
                $silber < $cost['silber'] || 
                $uderon < $cost['uderon'] || 
                $wasserstoff < $cost['wasserstoff'] ||
                $energie < $cost['energie']
            ) ? 'disabled' : ''; ?>>
                Forschen
            </button>
        </form>
        <?php else: ?>
        <div class="under-research">
            <p>Forschung auf Stufe <?php echo $tech->level + 1; ?> läuft...</p>
            <p>Fertig: <?php echo date('d.m.Y H:i:s', strtotime($tech->research_finish_time)); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
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
            // Refresh page to show completed research
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

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
