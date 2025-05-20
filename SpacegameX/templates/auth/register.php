<?php 
$pageTitle = "Registrierung";
// The header already includes wog-2.0.css and wog30.css
require_once BASE_PATH . '/templates/layout/header.php'; 
?>

<div style="display: flex; justify-content: center; align-items: center; min-height: 80vh;">
    <div class="rahmen" style="width: 450px; padding: 20px; background-color: #0A547C;">
        <h2 class="head" style="margin-bottom: 20px; padding: 10px;"><?php echo $pageTitle; ?></h2>

        <?php if (!empty($error)): ?>
            <div style="color: red; background-color: #330000; border: 1px solid red; padding: 10px; margin-bottom: 15px; text-align: center;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo BASE_URL; ?>/register">
            <div style="margin-bottom: 15px;">
                <label for="username" style="display: block; margin-bottom: 5px; color: #EBEBEB;">Benutzername:</label>
                <input type="text" name="username" id="username" required style="width: 100%; padding: 8px; box-sizing: border-box; background-color: #103050; border: 1px solid #00FFF6; color: #EBEBEB;">
            </div>

            <div style="margin-bottom: 15px;">
                <label for="email" style="display: block; margin-bottom: 5px; color: #EBEBEB;">E-Mail:</label>
                <input type="email" name="email" id="email" required style="width: 100%; padding: 8px; box-sizing: border-box; background-color: #103050; border: 1px solid #00FFF6; color: #EBEBEB;">
            </div>

            <div style="margin-bottom: 15px;">
                <label for="password" style="display: block; margin-bottom: 5px; color: #EBEBEB;">Passwort:</label>
                <input type="password" name="password" id="password" required style="width: 100%; padding: 8px; box-sizing: border-box; background-color: #103050; border: 1px solid #00FFF6; color: #EBEBEB;">
            </div>

            <div style="margin-bottom: 20px;">
                <label for="password2" style="display: block; margin-bottom: 5px; color: #EBEBEB;">Passwort wiederholen:</label>
                <input type="password" name="password2" id="password2" required style="width: 100%; padding: 8px; box-sizing: border-box; background-color: #103050; border: 1px solid #00FFF6; color: #EBEBEB;">
            </div>

            <button type="submit" class="galbutton" style="width: 100%; padding: 10px; font-size: 16px;">Registrieren</button>
        </form>

        <p style="text-align: center; margin-top: 20px;">
            Schon einen Account? <a href="<?php echo BASE_URL; ?>/login" style="color: #00DEFF;">Login</a>
        </p>
    </div>
</div>

<?php require_once BASE_PATH . '/templates/layout/footer.php'; ?>
