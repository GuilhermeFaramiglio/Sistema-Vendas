<?php
if (isset($_GET['theme'])) {
    $theme = $_GET['theme'];
    
    // Valida o tema
    if ($theme === 'dark' || $theme === 'light') {
        // Define o cookie por 30 dias
        setcookie('theme', $theme, time() + (30 * 24 * 60 * 60), '/');
        echo "Tema $theme salvo com sucesso!";
    } else {
        echo "Tema inválido!";
    }
} else {
    echo "Nenhum tema especificado!";
}
?>

