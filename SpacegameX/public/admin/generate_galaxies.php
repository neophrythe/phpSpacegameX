<?php
// Script to generate solar systems in batches
namespace Admin;

require_once __DIR__ . '/../../config/config.php';
// Ensure init.php is included. It should handle session_start() and class autoloading.
// If not already done in config.php, require it explicitly.
if (file_exists(__DIR__ . '/../../config/init.php')) {
    require_once __DIR__ . '/../../config/init.php';
} else {
    // Fallback or error if init.php is critical and not found
    // For autoloading, ensure your autoloader is registered.
    // Example basic autoloader if not using a framework's:
    spl_autoload_register(function ($class) {
        $prefix = 'Lib\';
        $base_dir = __DIR__ . '/../../src/Lib/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            $prefixCore = 'Core\';
            $base_dir_core = __DIR__ . '/../../src/Core/';
            $lenCore = strlen($prefixCore);
            if (strncmp($prefixCore, $class, $lenCore) !== 0) {
                 return;
            }
            $relative_class_core = substr($class, $lenCore);
            $file_core = $base_dir_core . str_replace('\\', '/', $relative_class_core) . '.php';
            if (file_exists($file_core)) {
                require $file_core;
            }
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}


// Check if user is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /admin/login.php'); // Adjust if your admin login path is different
    exit;
}

// Initialize parameters for GET requests
$galaxyStart = isset($_GET['galaxyStart']) ? intval($_GET['galaxyStart']) : 1;
$galaxyEnd = isset($_GET['galaxyEnd']) ? intval($_GET['galaxyEnd']) : min(10, \Lib\GalaxyGenerator::GALAXY_COUNT);
$galaxyEnd = min($galaxyEnd, \Lib\GalaxyGenerator::GALAXY_COUNT);


$message = '';
if (isset($_POST['generate'])) {
    $galaxyStart = max(1, min(\Lib\GalaxyGenerator::GALAXY_COUNT, intval($_POST['galaxyStart'])));
    $galaxyEnd = max(1, min(\Lib\GalaxyGenerator::GALAXY_COUNT, intval($_POST['galaxyEnd'])));
    
    if ($galaxyStart > $galaxyEnd) {
        $message = '<div class="alert alert-danger">Die Start-Galaxie muss kleiner oder gleich der End-Galaxie sein.</div>';
    } else {
        $db = \Core\Model::getDB();
        try {
            $db->beginTransaction();
            for ($g = $galaxyStart; $g <= $galaxyEnd; $g++) {
                $stmt = $db->prepare("SELECT id FROM galaxies WHERE galaxy_number = :gn");
                $stmt->execute([':gn' => $g]);
                if (!$stmt->fetch()) {
                    \Lib\GalaxyGenerator::createGalaxy($g);
                }
                
                $sampleSystemCount = min(10, \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY); // Generate fewer systems per batch
                if (\Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY > 0 && $sampleSystemCount > 0) {
                    $systemsToCreateIndices = array_rand(range(1, \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY), $sampleSystemCount);
                    if (!is_array($systemsToCreateIndices)) { // If only one random key is returned
                        $systemsToCreateIndices = [$systemsToCreateIndices];
                    }

                    foreach ($systemsToCreateIndices as $s_index) {
                        $s = $s_index +1; // array_rand returns keys, if range starts at 1, index is value-1
                         if (is_array(range(1, \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY)) && array_key_exists($s_index, range(1, \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY))) {
                            $s = range(1, \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY)[$s_index];
                        }


                        $stmt = $db->prepare("SELECT id FROM solar_systems WHERE galaxy = :g AND system = :s");
                        $stmt->execute([':g' => $g, ':s' => $s]);
                        if (!$stmt->fetch()) {
                            $systemType = \Lib\GalaxyGenerator::getRandomSystemType();
                            $planetCount = \Lib\GalaxyGenerator::calculatePlanetCount($systemType);
                            \Lib\GalaxyGenerator::createSolarSystem($g, $s, $planetCount, $systemType);
                        }
                    }
                }
            }
            $db->commit();
            $message = '<div class="alert alert-success">Galaxien ' . $galaxyStart . ' bis ' . $galaxyEnd . ' wurden generiert mit Beispiel-Sonnensystemen.</div>';
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $message = '<div class="alert alert-danger">Fehler bei der Generierung: ' . $e->getMessage() . '</div>';
        }
    }
}

$db = \Core\Model::getDB();
$stmt = $db->query('SELECT COUNT(*) FROM galaxies');
$currentGalaxyCount = $stmt->fetchColumn();
$stmt = $db->query('SELECT COUNT(*) FROM solar_systems');
$currentSystemCount = $stmt->fetchColumn();
$totalPossibleSystems = \Lib\GalaxyGenerator::GALAXY_COUNT * \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY;
$progress = $totalPossibleSystems > 0 ? ($currentSystemCount / $totalPossibleSystems * 100) : 0;

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin - Galaxy Generator</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1 { color: #343a40; }
        .progress-bar { background-color: #28a745; }
        .card-header { background-color: #007bff; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4">Galaxy Generator</h1>
    <p>Dieses Tool dient zur Generierung und Initialisierung von Galaxien und Sonnensystemen im Spiel.</p>
    
    <?php if ($message): ?>
        <?php echo $message; ?>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">Status der Generierung</div>
        <div class="card-body">
            <p>Generierte Galaxien: <strong><?php echo $currentGalaxyCount; ?> / <?php echo \Lib\GalaxyGenerator::GALAXY_COUNT; ?></strong></p>
            <p>Generierte Sonnensysteme: <strong><?php echo $currentSystemCount; ?> / <?php echo $totalPossibleSystems; ?></strong></p>
            <div class="progress mb-2">
                <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%" 
                     aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                    <?php echo round($progress, 1); ?>%
                </div>
            </div>
            <small class="text-muted">Die Anzahl der Sonnensysteme kann initial niedriger sein, da Systeme auch dynamisch bei Bedarf erstellt werden.</small>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">Galaxien-Batch generieren</div>
        <div class="card-body">
            <form method="post" action="">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="galaxyStart">Start-Galaxie:</label>
                        <input type="number" class="form-control" id="galaxyStart" name="galaxyStart" 
                               min="1" max="<?php echo \Lib\GalaxyGenerator::GALAXY_COUNT; ?>" 
                               value="<?php echo $galaxyStart; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="galaxyEnd">End-Galaxie:</label>
                        <input type="number" class="form-control" id="galaxyEnd" name="galaxyEnd" 
                               min="1" max="<?php echo \Lib\GalaxyGenerator::GALAXY_COUNT; ?>" 
                               value="<?php echo $galaxyEnd; ?>" required>
                    </div>
                </div>
                <p class="text-muted small">Hinweis: Generiert die Galaxie-Einträge und eine kleine Anzahl von Beispiel-Sonnensystemen pro Galaxie. Nicht alle Systeme werden sofort erstellt, um die Serverlast zu reduzieren.</p>
                <button type="submit" name="generate" class="btn btn-primary">Batch generieren</button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">Spezifisches Sonnensystem initialisieren</div>
        <div class="card-body">
            <form method="post" action="">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="init_galaxy">Galaxie:</label>
                        <input type="number" class="form-control" id="init_galaxy" name="init_galaxy" 
                               min="1" max="<?php echo \Lib\GalaxyGenerator::GALAXY_COUNT; ?>" value="1" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="init_system">System:</label>
                        <input type="number" class="form-control" id="init_system" name="init_system" 
                               min="1" max="<?php echo \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY; ?>" value="1" required>
                    </div>
                </div>
                <button type="submit" name="initialize_system" class="btn btn-info">System initialisieren</button>
                 <p class="text-muted small mt-2">Hinweis: Erstellt den Sonnensystem-Eintrag (falls nicht vorhanden) und füllt ihn mit Planeten gemäß den Regeln des GalaxyGenerators.</p>
            </form>
            
            <?php
            if (isset($_POST['initialize_system'])) {
                $initGalaxy = intval($_POST['init_galaxy']);
                $initSystem = intval($_POST['init_system']);
                echo "<h4 class='mt-3'>Initialisiere System G{$initGalaxy}:S{$initSystem}</h4>";
                try {
                    $db = \Core\Model::getDB(); // Ensure DB connection
                    $db->beginTransaction();

                    // Ensure galaxy record exists
                    $stmt = $db->prepare("SELECT id FROM galaxies WHERE galaxy_number = :gn");
                    $stmt->execute([':gn' => $initGalaxy]);
                    if (!$stmt->fetch()) {
                        \Lib\GalaxyGenerator::createGalaxy($initGalaxy);
                         echo "<p class='text-info'>Galaxie {$initGalaxy} erstellt.</p>";
                    }

                    // This will create the system if it doesn't exist and then populate planets
                    \Lib\GalaxyGenerator::initializeSolarSystem($initGalaxy, $initSystem); 

                    $db->commit();

                    // Count planets created
                    $stmt = $db->prepare('SELECT COUNT(*) FROM planets WHERE galaxy = :g AND system = :s');
                    $stmt->execute([':g' => $initGalaxy, ':s' => $initSystem]);
                    $planetCount = $stmt->fetchColumn();
                    echo "<p class='text-success'>System G{$initGalaxy}:S{$initSystem} erfolgreich initialisiert/aktualisiert mit {$planetCount} Planeten.</p>";

                } catch (\Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    echo "<p class='text-danger'>Fehler bei der Initialisierung von System G{$initGalaxy}:S{$initSystem}: " . $e->getMessage() . "</p>";
                }
            }
            ?>
        </div>
    </div>
    <footer class="mt-5 text-center text-muted small">
        SpacegameX Admin Panel
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
