<?php
require_once __DIR__ . '/includes/auth.php';

if (esta_autenticado()) {
    header('Location: ordenes.php');
} else {
    header('Location: login.php');
}
exit;