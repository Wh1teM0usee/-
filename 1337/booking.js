document.addEventListener('DOMContentLoaded', function() {
    initPage();
});

async function initPage() {
    await checkAuthStatus();
    await loadRooms();
    initModals();
    
    const addRoomBtn = document.getElementById('add-room-btn');
    if (addRoomBtn) {
        addRoomBtn.addEventListener('click', () => {
            openRoomModal();
        });
    }
}

async function loadRooms() {
    const container = document.getElementById('rooms-container');
    if (!container) return;
    
    container.innerHTML = `
        <div class="loading">
            <div class="loading-spinner"></div>
            <p>Загрузка номеров...</p>
        </div>
    `;
    
    try {
        const response = await fetch('api.php?action=get_rooms', {
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Ошибка загрузки номеров');
        
        const result = await response.json();
        
        if (result.success) {
            renderRooms(result.data);
        } else {
            container.innerHTML = '<p>Не удалось загрузить номера. Пожалуйста, попробуйте позже.</p>';
        }
    } catch (error) {
        console.error('Ошибка:', error);
        container.innerHTML = `<p>Ошибка: ${error.message}</p>`;
    }
}

function renderRooms(rooms) {
    const container = document.getElementById('rooms-container');
    if (!container) return;
    
    container.innerHTML = '';
    const isAdmin = localStorage.getItem('role') === 'admin';
    
    rooms.forEach(room => {
        const roomCard = document.createElement('div');
        roomCard.className = `room-card ${room.is_available ? '' : 'booked-room'}`;
        
        roomCard.innerHTML = `
            ${!room.is_available ? '<div class="booked-label">Забронировано</div>' : ''}
            <img src="${room.image_path || '/1337/images/placeholder.jpg'}" alt="${room.title}" class="room-image">
            <div class="room-content">
                <h3 class="room-title">${room.title}</h3>
                <p class="room-description">${room.description}</p>
                <div class="room-details">
                    <span class="room-price">${room.price_per_night} ₽/ночь</span>
                    <span class="room-capacity">Вместимость: ${room.capacity} чел.</span>
                </div>
                ${room.is_available ? 
                    `<div class="room-actions">
                        <button class="book-btn" onclick="openBookingModal(${room.id})">
                            <i class="fas fa-calendar-check"></i> Забронировать
                        </button>
                        ${isAdmin ? `
                            <button class="admin-btn edit" onclick="editRoom(${room.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="admin-btn delete" onclick="deleteRoom(${room.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>` : 
                    `<p class="not-available">Номер недоступен</p>
                    ${isAdmin ? `
                        <div class="room-actions">
                            <button class="admin-btn edit" onclick="editRoom(${room.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="admin-btn delete" onclick="deleteRoom(${room.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    ` : ''}`
                }
            </div>
        `;
        
        container.appendChild(roomCard);
    });
}

function initModals() {
    const bookingModal = document.getElementById('booking-modal');
    const bookingClose = bookingModal.querySelector('.close-modal');
    const bookingForm = document.getElementById('booking-form');
    
    bookingClose.addEventListener('click', () => {
        bookingModal.style.display = 'none';
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === bookingModal) {
            bookingModal.style.display = 'none';
        }
    });
    
    bookingForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const roomId = document.getElementById('booking-room-id').value;
        const checkIn = document.getElementById('check-in-date').value;
        const checkOut = document.getElementById('check-out-date').value;
        const guests = document.getElementById('guests-count').value;
        
        if (!checkIn || !checkOut || !guests) {
            alert('Пожалуйста, заполните все поля');
            return;
        }
        
        try {
            const response = await fetch('api.php?action=book', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    room_id: roomId,
                    date_from: checkIn,
                    date_to: checkOut,
                    guest_name: localStorage.getItem('username') || 'Гость',
                    guest_email: 'user@example.com',
                    guests: guests
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Номер успешно забронирован!');
                bookingModal.style.display = 'none';
                await loadRooms();
            } else {
                alert(`Ошибка: ${result.message}`);
            }
        } catch (error) {
            console.error('Ошибка бронирования:', error);
            alert('Произошла ошибка при бронировании. Пожалуйста, попробуйте позже.');
        }
    });
    
    const roomModal = document.getElementById('room-modal');
    const roomClose = roomModal.querySelector('.close-modal');
    const roomForm = document.getElementById('room-form');
    
    roomClose.addEventListener('click', () => {
        roomModal.style.display = 'none';
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === roomModal) {
            roomModal.style.display = 'none';
        }
    });
    
    roomForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const roomId = document.getElementById('room-id').value;
        const isEdit = !!roomId;
        
        const roomData = {
            title: document.getElementById('room-title').value,
            description: document.getElementById('room-description').value,
            price_per_night: document.getElementById('room-price').value,
            capacity: document.getElementById('room-capacity').value,
            image_path: document.getElementById('room-image').value,
            is_available: document.getElementById('room-available').value
        };
        
        try {
            let response;
            
            if (isEdit) {
                roomData.id = roomId;
                response = await fetch('api.php?action=admin_update_room', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify(roomData)
                });
            } else {
                response = await fetch('api.php?action=admin_add_room', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify(roomData)
                });
            }
            
            const result = await response.json();
            
            if (result.success) {
                alert(`Номер ${isEdit ? 'обновлен' : 'добавлен'} успешно!`);
                roomModal.style.display = 'none';
                await loadRooms();
            } else {
                alert(`Ошибка: ${result.message}`);
            }
        } catch (error) {
            console.error('Ошибка:', error);
            alert('Произошла ошибка. Пожалуйста, попробуйте позже.');
        }
    });
}

function openBookingModal(roomId) {
    const modal = document.getElementById('booking-modal');
    if (!modal) return;
    
    document.getElementById('booking-room-id').value = roomId;
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('check-in-date').min = today;
    document.getElementById('check-out-date').min = today;
    modal.style.display = 'block';
}

function openRoomModal(roomData = null) {
    const modal = document.getElementById('room-modal');
    const title = document.getElementById('room-modal-title');
    const form = document.getElementById('room-form');
    
    if (roomData) {
        title.textContent = 'Редактировать номер';
        document.getElementById('room-id').value = roomData.id;
        document.getElementById('room-title').value = roomData.title;
        document.getElementById('room-description').value = roomData.description;
        document.getElementById('room-price').value = roomData.price_per_night;
        document.getElementById('room-capacity').value = roomData.capacity;
        document.getElementById('room-image').value = roomData.image_path || '';
        document.getElementById('room-available').value = roomData.is_available ? '1' : '0';
    } else {
        title.textContent = 'Добавить номер';
        form.reset();
        document.getElementById('room-id').value = '';
        document.getElementById('room-available').value = '1';
    }
    
    modal.style.display = 'block';
}

async function editRoom(roomId) {
    try {
        const response = await fetch(`api.php?action=get_room&id=${roomId}`, {
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Ошибка загрузки данных номера');
        
        const result = await response.json();
        
        if (result.success && result.data) {
            openRoomModal(result.data);
        } else {
            alert('Не удалось загрузить данные номера');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        alert('Произошла ошибка. Пожалуйста, попробуйте позже.');
    }
}

async function deleteRoom(roomId) {
    if (!confirm('Вы уверены, что хотите удалить этот номер?')) return;
    
    try {
        const response = await fetch('api.php?action=admin_delete_room', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ id: roomId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Номер успешно удален');
            await loadRooms();
        } else {
            alert(`Ошибка: ${result.message}`);
        }
    } catch (error) {
        console.error('Ошибка:', error);
        alert('Произошла ошибка при удалении. Пожалуйста, попробуйте позже.');
    }
}

async function checkAuthStatus() {
    const authMessage = document.getElementById('auth-message');
    const logoutBtn = document.getElementById('logout-btn');
    const loginBtn = document.getElementById('login-btn');
    const adminPanel = document.getElementById('admin-panel');

    if (authMessage) authMessage.textContent = '';
    if (logoutBtn) logoutBtn.style.display = 'none';
    if (loginBtn) loginBtn.style.display = 'block';
    if (adminPanel) adminPanel.style.display = 'none';

    try {
        const response = await fetch('api.php?action=check_auth', {
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Ошибка проверки авторизации');
        
        const result = await response.json();
        
        if (result.success && result.data?.authenticated) {
            if (authMessage) authMessage.textContent = `Вы вошли как ${result.data.username || 'Гость'}`;
            if (logoutBtn) logoutBtn.style.display = 'block';
            if (loginBtn) loginBtn.style.display = 'none';
            
            if (adminPanel && result.data.role === 'admin') {
                adminPanel.style.display = 'block';
            }
            
            localStorage.setItem('role', result.data.role || 'user');
        } else {
            if (authMessage) authMessage.textContent = 'Вы не авторизованы';
            if (logoutBtn) logoutBtn.style.display = 'none';
            if (loginBtn) loginBtn.style.display = 'block';
        }
    } catch (error) {
        console.error('Ошибка проверки авторизации:', error);
        if (authMessage) authMessage.textContent = 'Ошибка проверки авторизации';
        if (logoutBtn) logoutBtn.style.display = 'none';
        if (loginBtn) loginBtn.style.display = 'block';
    }
}

window.openBookingModal = openBookingModal;
window.editRoom = editRoom;
window.deleteRoom = deleteRoom;