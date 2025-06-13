document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logout-btn');
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            fetch('api.php?action=logout', {
                method: 'POST',
                credentials: 'include' // Важно для работы с сессиями
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Обновляем статус авторизации
                    updateAuthStatus(false);
                    // Перенаправляем на главную страницу
                    window.location.href = 'index.html';
                } else {
                    alert('Ошибка при выходе: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при выходе');
            });
        });
    }
});

function updateAuthStatus(isAuthenticated) {
    const authMessage = document.getElementById('auth-message');
    const logoutBtn = document.getElementById('logout-btn');
    const loginBtn = document.getElementById('login-btn');
    
    if (isAuthenticated) {
        authMessage.textContent = 'Вы авторизованы';
        logoutBtn.style.display = 'inline-block';
        loginBtn.style.display = 'none';
    } else {
        authMessage.textContent = 'Вы не авторизованы';
        logoutBtn.style.display = 'none';
        loginBtn.style.display = 'inline-block';
    }
}