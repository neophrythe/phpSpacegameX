<?php
namespace Controllers;

use Core\Controller;
use Models\Player;

class AuthController extends Controller {
    public function login() {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $player = Player::findByUsername($username);
            if ($player && password_verify($password, $player->password_hash)) {
                $_SESSION['user_id'] = $player->id;
                $this->redirect('/');
            } else {
                $error = 'Benutzername oder Passwort falsch.';
            }
        }
        $this->view('auth.login', ['error' => $error]);
    }

    public function register() {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';
            if ($password !== $password2) {
                $error = 'Passwörter stimmen nicht überein.';
            } elseif (Player::findByUsername($username)) {
                $error = 'Benutzername existiert bereits.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $player_id = Player::create($username, $hash, $email);
                if ($player_id) {
                    // Create home planet for new player
                    $planet_id = \Models\Planet::createHomePlanetForPlayer($player_id);
                    
                    // Create initial buildings for the planet
                    if ($planet_id) {
                        \Models\PlayerBuilding::createInitialBuildings($planet_id);
                    }
                    
                    // Create initial research entries for the player
                    \Models\PlayerResearch::createInitialResearch($player_id);
                    
                    $this->redirect('/login');
                } else {
                    $error = 'Registrierung fehlgeschlagen.';
                }
            }
        }
        $this->view('auth.register', ['error' => $error]);
    }

    public function logout() {
        session_destroy();
        $this->redirect('/login');
    }
}
