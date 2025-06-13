// menu.js
document.addEventListener('DOMContentLoaded', function() {
    // Элементы меню
    const menuToggle = document.getElementById('menu-toggle');
    const dropdownMenu = document.getElementById('dropdown-menu');
    
    // Функция для переключения меню
    function toggleMenu() {
        dropdownMenu.classList.toggle('show');
    }
    
    // Закрытие меню при клике вне его
    function closeMenuOnClickOutside(e) {
        if (!menuToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
        }
    }
    
    // Инициализация событий
    function initMenu() {
        if (menuToggle && dropdownMenu) {
            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleMenu();
            });
            
            document.addEventListener('click', closeMenuOnClickOutside);
            
            // Предотвращаем закрытие при клике внутри меню
            dropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    }
    
    initMenu();
});