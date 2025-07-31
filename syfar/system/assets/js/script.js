document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;

    // Função para aplicar o tema
    function applyTheme(theme) {
        if (theme === 'dark') {
            body.classList.add('dark-mode');
        } else {
            body.classList.remove('dark-mode');
        }
    }

    // Aplica o tema salvo no localStorage em todas as páginas
    let savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark' || savedTheme === 'light') {
        applyTheme(savedTheme);
    } else {
        // Se não houver tema salvo, busca do PHP e salva no localStorage
        fetch("../../get_theme.php")
            .then(response => response.json())
            .then(data => {
                if (data && (data.theme === 'dark' || data.theme === 'light')) {
                    localStorage.setItem('theme', data.theme);
                    applyTheme(data.theme);
                    savedTheme = data.theme;
                } else {
                    localStorage.setItem('theme', 'light');
                    applyTheme('light');
                    savedTheme = 'light';
                }
            });
            
            // Certifique-se de incluir este script em TODAS as páginas do sistema, não apenas na index.php.
            // Além disso, garanta que a classe CSS 'dark-mode' esteja definida globalmente para afetar todos os elementos necessários.
    }

    // Adiciona o evento de toggle ao botão, se existir
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            // Alterna entre dark e light
            const currentTheme = localStorage.getItem('theme') === 'dark' ? 'light' : 'dark';
            applyTheme(currentTheme);

            // Salva a escolha do tema no localStorage e no servidor via PHP
            localStorage.setItem('theme', currentTheme);
            fetch("../../set_theme.php?theme=" + currentTheme)
                .then(response => response.json())
                .then(data => {
                    if (data && (data.theme === 'dark' || data.theme === 'light')) {
                        applyTheme(data.theme);
                        localStorage.setItem('theme', data.theme);
                    } else {
                        applyTheme(currentTheme); // fallback para o tema selecionado
                    }
                })
                .catch(error => {
                    console.error('Erro ao salvar o tema:', error);
                    applyTheme(currentTheme); // fallback em caso de erro
                });
        });
    }
});

