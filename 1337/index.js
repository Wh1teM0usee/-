document.addEventListener('DOMContentLoaded', async function() {
    // Установка дат по умолчанию для формы бронирования
    setDefaultDates();
    
    // Проверяем статус авторизации при загрузке страницы
    await checkAuthStatus();

    // Загружаем комнаты, если есть соответствующая функция
    if (typeof loadRooms === 'function') {
        await loadRooms();
    }

    // Настраиваем кнопку выхода
    setupLogout();

    // Настраиваем мобильное меню
    setupMobileMenu();

    // Настраиваем плавную прокрутку
    setupSmoothScroll();

    // Настраиваем форму скидки
    setupDiscountForm();

    // Инициализируем модальное окно бронирования, если оно есть
    if (typeof initBookingModal === 'function') {
        initBookingModal();
    }
});

/* ========== ФУНКЦИИ ДЛЯ УСТАНОВКИ ДАТ ПО УМОЛЧАНИЮ ========== */

function setDefaultDates() {
    const today = new Date();
    const tomorrow = new Date();
    tomorrow.setDate(today.getDate() + 1);
    
    // Форматируем даты в формат YYYY-MM-DD
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    const checkInInput = document.getElementById('check-in');
    const checkOutInput = document.getElementById('check-out');
    
    if (checkInInput) {
        checkInInput.value = formatDate(today);
        // Устанавливаем минимальную дату - сегодня
        checkInInput.min = formatDate(today);
    }
    
    if (checkOutInput) {
        checkOutInput.value = formatDate(tomorrow);
        // Устанавливаем минимальную дату - завтра
        checkOutInput.min = formatDate(tomorrow);
    }
    
    // Обновляем минимальную дату выезда при изменении даты заезда
    if (checkInInput && checkOutInput) {
        checkInInput.addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            checkInDate.setDate(checkInDate.getDate() + 1);
            checkOutInput.min = formatDate(checkInDate);
            
            // Если текущая дата выезда раньше новой минимальной даты
            if (new Date(checkOutInput.value) < checkInDate) {
                checkOutInput.value = formatDate(checkInDate);
            }
        });
    }
}

/* ========== ФУНКЦИИ АВТОРИЗАЦИИ ========== */

async function checkAuthStatus() {
    const authMessage = document.getElementById('auth-message');
    if (!authMessage) return;

    try {
        const response = await fetch('api.php?action=check_auth', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const result = await response.json();
        console.log('Auth check response:', result);

        if (result.success && result.data?.authenticated) {
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('username', result.data.username || 'Гость');
            if (result.data.role) {
                localStorage.setItem('role', result.data.role);
            }
        } else {
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('username');
            localStorage.removeItem('role');
        }
    } catch (error) {
        console.error('Error checking auth status:', error);
    }

    updateAuthUI();
}

function updateAuthUI() {
    const authMessage = document.getElementById('auth-message');
    const logoutBtn = document.getElementById('logout-btn');
    const loginBtn = document.getElementById('login-btn');
    const adminPanel = document.getElementById('admin-panel');

    if (!authMessage) return;

    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const username = localStorage.getItem('username') || 'Гость';
    const role = localStorage.getItem('role');

    if (isLoggedIn) {
        authMessage.textContent = `Вы вошли как ${username}`;
        
        if (logoutBtn) logoutBtn.style.display = 'block';
        if (loginBtn) loginBtn.style.display = 'none';
        
        if (adminPanel) {
            adminPanel.style.display = role === 'admin' ? 'block' : 'none';
        }
    } else {
        authMessage.textContent = 'Вы не авторизованы';
        
        if (logoutBtn) logoutBtn.style.display = 'none';
        if (loginBtn) loginBtn.style.display = 'block';
        
        if (adminPanel) {
            adminPanel.style.display = 'none';
        }
    }
}

function setupLogout() {
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async function() {
            try {
                const response = await fetch('api.php?action=logout', {
                    method: 'GET',
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    throw new Error('Logout failed');
                }
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                localStorage.removeItem('isLoggedIn');
                localStorage.removeItem('username');
                localStorage.removeItem('role');
                window.location.reload();
            }
        });
    }
}

/* ========== ФУНКЦИИ ДЛЯ РАБОТЫ С НОМЕРАМИ ========== */

async function loadRooms() {
    try {
        const response = await fetch('rooms_api.php?action=get_room', {
            method: 'GET',
            credentials: 'include'
        });

        if (!response.ok) throw new Error('Ошибка загрузки номеров');

        const result = await response.json();
        if (result.success && result.data.length > 0) {
            renderRooms(result.data);
        } else {
            document.getElementById('rooms-container').innerHTML = '<p>Нет доступных номеров.</p>';
        }
    } catch (error) {
        console.error('Ошибка получения номеров:', error);
    }
}

function renderRooms(rooms) {
    const container = document.getElementById('rooms-container');
    if (!container) return;

    container.innerHTML = '';
    const isAdmin = localStorage.getItem('role') === 'admin';

    rooms.forEach(room => {
        const roomCard = document.createElement('div');
        roomCard.classList.add('room-card');

        roomCard.innerHTML = `
            <img src="${room.image_path || 'placeholder.jpg'}" alt="${room.title}">
            <div class="room-card-content">
                <h3>${room.title}</h3>
                <p>${room.description}</p>
                <p>Цена: ${room.price_per_night}₽/ночь</p>
                <p>Вместимость: ${room.capacity} чел.</p>
                ${room.is_available ? 
                    `<button class="book-btn" onclick="openBookingModal(${room.id})">Забронировать</button>` : 
                    '<p class="not-available">Недоступно</p>'}
                ${isAdmin ? `<button class="delete-btn" onclick="deleteRoom(${room.id})">Удалить</button>` : ''}
            </div>
        `;

        container.appendChild(roomCard);
    });
}

/* ========== ФУНКЦИИ БРОНИРОВАНИЯ ========== */

function initBookingModal() {
    const modal = document.getElementById('booking-modal');
    if (!modal) return;

    document.getElementById('close-booking-modal').addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    document.getElementById('booking-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const roomId = document.getElementById('booking-room-id').value;
        const checkIn = document.getElementById('check-in-date').value;
        const checkOut = document.getElementById('check-out-date').value;
        const guests = document.getElementById('guests-count').value;

        try {
            const response = await fetch('rooms_api.php?action=book_room', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ room_id: roomId, check_in: checkIn, check_out: checkOut, guests: guests })
            });

            const result = await response.json();
            if (result.success) {
                alert('Бронирование успешно!');
                modal.style.display = 'none';
                if (typeof loadRooms === 'function') {
                    await loadRooms();
                }
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            console.error('Ошибка бронирования:', error);
        }
    });
}

function openBookingModal(roomId) {
    const modal = document.getElementById('booking-modal');
    if (modal) {
        document.getElementById('booking-room-id').value = roomId;
        modal.style.display = 'block';
    }
}

/* ========== АДМИН-ФУНКЦИИ ========== */

async function deleteRoom(roomId) {
    if (!confirm('Вы уверены, что хотите удалить этот номер?')) return;

    try {
        const response = await fetch('rooms_api.php?action=delete_room', {
            method: 'DELETE',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ room_id: roomId })
        });

        const result = await response.json();
        if (result.success) {
            alert('Номер удален');
            if (typeof loadRooms === 'function') {
                await loadRooms();
            }
        } else {
            alert('Ошибка: ' + result.message);
        }
    } catch (error) {
        console.error('Ошибка удаления номера:', error);
    }
}

/* ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ========== */

function setupMobileMenu() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');

    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }
}

function setupSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#') return;
            
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 100,
                    behavior: 'smooth'
                });
                
                // Закрываем мобильное меню после клика
                const navLinks = document.querySelector('.nav-links');
                if (window.innerWidth <= 992 && navLinks) {
                    navLinks.classList.remove('active');
                }
            }
        });
    });
}

function setupDiscountForm() {
    const discountForm = document.querySelector('.discount-form');
    if (discountForm) {
        discountForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Спасибо! Мы свяжемся с вами в ближайшее время для уточнения деталей бронирования со скидкой.');
            this.reset();
        });
    }
}