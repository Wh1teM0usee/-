document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const usernameCounter = document.getElementById('username-counter');
    const passwordCounter = document.getElementById('password-counter');
    
    // Лимиты символов
    const limits = {
        username: 8,
        password: 20
    };
    
    // Функция для обновления счетчиков символов
    function updateCounters() {
        usernameCounter.textContent = `${usernameInput.value.length}/${limits.username}`;
        passwordCounter.textContent = `${passwordInput.value.length}/${limits.password}`;
        
        // Подсветка при превышении лимита
        usernameCounter.classList.toggle('limit-exceeded', usernameInput.value.length > limits.username);
        passwordCounter.classList.toggle('limit-exceeded', passwordInput.value.length > limits.password);
    }
    
    // Обработчики изменения полей
    usernameInput.addEventListener('input', function() {
        this.value = this.value.substring(0, limits.username);
        updateCounters();
    });
    
    passwordInput.addEventListener('input', function() {
        this.value = this.value.substring(0, limits.password);
        updateCounters();
    });
    
    // Инициализация счетчиков
    updateCounters();
    
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Проверка длины полей перед отправкой
            if (usernameInput.value.length > limits.username) {
                alert(`Имя пользователя не должно превышать ${limits.username} символов`);
                return;
            }
            
            if (passwordInput.value.length > limits.password) {
                alert(`Пароль не должен превышать ${limits.password} символов`);
                return;
            }
            
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            submitBtn.textContent = 'Вход...';
            submitBtn.disabled = true;
            
            const formData = new FormData(loginForm);
            const data = {
                username: formData.get('username'),
                password: formData.get('password')
            };
            
            try {
                console.log('Отправка запроса на вход:', data);
                const response = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });
                
                const result = await response.json();
                console.log('Ответ сервера (вход):', result);
                
                if (result.success) {
                    // Сохраняем информацию о входе в localStorage
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('username', result.data.username);
                    localStorage.setItem('userId', result.data.user_id);
                    
                    window.location.href = 'index.html';
                } else {
                    alert('Ошибка: ' + result.message);
                }
            } catch (error) {
                console.error('Ошибка входа:', error);
                alert('Ошибка соединения: ' + error.message);
            } finally {
                submitBtn.textContent = originalBtnText;
                submitBtn.disabled = false;
            }
        });
    }
});