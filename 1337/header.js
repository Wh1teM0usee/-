document.addEventListener('DOMContentLoaded', function() {
    // Проверка статуса авторизации
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const authMessage = document.getElementById('auth-message');
    const logoutBtn = document.getElementById('logout-btn');
    const loginBtn = document.getElementById('login-btn');
    
    if (isLoggedIn) {
        const username = localStorage.getItem('username') || 'Гость';
        authMessage.textContent = `Вы вошли как ${username}`;
        logoutBtn.style.display = 'inline-block';
    } else {
        authMessage.textContent = 'Вы не авторизованы';
        loginBtn.style.display = 'inline-block';
    }
    
    // Обработка выхода
    logoutBtn.addEventListener('click', function() {
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('username');
        window.location.reload();
    });
    
    // Мобильное меню (можно добавить позже)
    const mobileMenuBtn = document.createElement('button');
    mobileMenuBtn.className = 'mobile-menu-btn';
    mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    
    const headerContainer = document.querySelector('.header-container');
    headerContainer.appendChild(mobileMenuBtn);
    
    mobileMenuBtn.addEventListener('click', function() {
        const nav = document.querySelector('.header-nav');
        nav.style.display = nav.style.display === 'flex' ? 'none' : 'flex';
    });
});