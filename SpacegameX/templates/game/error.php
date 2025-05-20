<?php
// templates/game/error.php

// Include header
include __DIR__ . '/../layout/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h1>Ein Fehler ist aufgetreten</h1>
                </div>
                <div class="card-body">
                    <p>Entschuldigung, bei der Verarbeitung Ihrer Anfrage ist ein unerwarteter Fehler aufgetreten.</p>
                    <p>Bitte versuchen Sie es später erneut oder kontaktieren Sie den Support, wenn das Problem weiterhin besteht.</p>
                    <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>Details:</strong> <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <a href="/game" class="btn btn-primary">Zurück zum Spiel</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include __DIR__ . '/../layout/footer.php';
?>
