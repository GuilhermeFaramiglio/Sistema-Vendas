<?php
header('Content-Type: application/json');

// Verifica se o cookie existe
if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
    
    // Valida o tema
    if ($theme === 'dark' || $theme === 'light') {
        echo json_encode(['theme' => $theme]);
    } else {
        echo json_encode(['theme' => 'light']);
    }
} else {
    // Tema padrão
    echo json_encode(['theme' => 'light']);
}
?>

