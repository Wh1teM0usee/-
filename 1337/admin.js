document.addEventListener('DOMContentLoaded', async function() {
    // Проверяем статус авторизации
    await checkAuthStatus();
    
    // Если пользователь не авторизован или не админ - перенаправляем
    if (localStorage.getItem('isLoggedIn') !== 'true' || localStorage.getItem('role') !== 'admin') {
        window.location.href = '/1337/login.html';
        return;
    }
    
    // Загружаем список пользователей
    await loadUsers();
    
    // Настраиваем кнопки
    setupButtons();
    
    // Настраиваем модальные окна
    setupModals();
});

// Проверка статуса авторизации (можно вынести в отдельный файл)
async function checkAuthStatus() {
    try {
        const response = await fetch('/1337/api.php?action=check_auth', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const result = await response.json();
        
        if (result.success && result.data?.authenticated && result.data.user) {
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('username', result.data.user.username || 'Гость');
            localStorage.setItem('userId', result.data.user.id || '');
            localStorage.setItem('role', result.data.user.role || 'user');
        } else {
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('username');
            localStorage.removeItem('userId');
            localStorage.removeItem('role');
        }
        
        updateAuthUI();
    } catch (error) {
        console.error('Error checking auth status:', error);
        localStorage.removeItem('isLoggedIn');
        updateAuthUI();
    }
}

// Обновление UI авторизации
function updateAuthUI() {
    const authMessage = document.getElementById('auth-message');
    const logoutBtn = document.getElementById('logout-btn');
    const loginBtn = document.getElementById('login-btn');

    if (!authMessage) return;

    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const username = localStorage.getItem('username') || 'Гость';

    if (isLoggedIn) {
        authMessage.textContent = `Вы вошли как ${username}`;
        if (logoutBtn) logoutBtn.style.display = 'block';
        if (loginBtn) loginBtn.style.display = 'none';
    } else {
        authMessage.textContent = 'Вы не авторизованы';
        if (logoutBtn) logoutBtn.style.display = 'none';
        if (loginBtn) loginBtn.style.display = 'block';
    }
}

// Настройка кнопок
function setupButtons() {
    // Кнопка выхода
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async function() {
            try {
                const response = await fetch('/1337/api.php?action=logout', {
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
                localStorage.removeItem('userId');
                localStorage.removeItem('role');
                window.location.href = '/1337/login.html';
            }
        });
    }
    
    // Кнопка создания пользователя
    document.getElementById('create-user-btn').addEventListener('click', () => editUser(null));
    
    // Кнопка обновления списка
    document.getElementById('refresh-users-btn').addEventListener('click', loadUsers);
}

// Настройка модальных окон
function setupModals() {
    // Закрытие модальных окон при клике на крестик
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        });
    });
    
    // Закрытие модальных окон при клике вне их
    window.addEventListener('click', (event) => {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // Обработка формы пользователя
    document.getElementById('user-form').addEventListener('submit', saveUser);
    
    // Кнопка отмены в форме
    document.getElementById('cancel-user-btn').addEventListener('click', () => {
        document.getElementById('user-modal').style.display = 'none';
    });
    
    // Превью аватара
    document.getElementById('modal-avatar').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('avatar-preview').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
}

// Загрузка списка пользователей
async function loadUsers() {
    try {
        const container = document.getElementById('users-container');
        container.innerHTML = `
            <div class="loading">
                <div class="loading-spinner"></div>
                <p>Загрузка списка пользователей...</p>
            </div>
        `;
        
        const response = await fetch('/1337/users_api.php?action=get_users', {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Не удалось загрузить пользователей');
        }
        
        renderUsers(data.data.users);
        
    } catch (error) {
        console.error('Ошибка загрузки пользователей:', error);
        showError('Не удалось загрузить список пользователей: ' + error.message);
    }
}

// Отображение списка пользователей
function renderUsers(users) {
    const container = document.getElementById('users-container');
    
    if (!users || users.length === 0) {
        container.innerHTML = '<p>Нет пользователей</p>';
        return;
    }
    
    let html = `
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Дата регистрации</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    users.forEach(user => {
        html += `
            <tr>
                <td>${user.id}</td>
                <td>${user.username}</td>
                <td>${user.first_name || '-'}</td>
                <td>${user.email}</td>
                <td>
                    <span class="status-badge ${user.role === 'admin' ? 'status-active' : 'status-inactive'}">
                        ${user.role === 'admin' ? 'Админ' : 'Пользователь'}
                    </span>
                </td>
                <td>${user.created_at}</td>
                <td>
                    <div class="action-btns">
                        <button class="action-btn view" data-user-id="${user.id}" title="Просмотр">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn edit" data-user-id="${user.id}" title="Редактировать">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn delete" data-user-id="${user.id}" title="Удалить">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `</tbody></table>`;
    container.innerHTML = html;
    
    // Добавляем обработчики событий для кнопок действий
    document.querySelectorAll('.action-btn.view').forEach(btn => {
        btn.addEventListener('click', () => viewUser(btn.getAttribute('data-user-id')));
    });
    
    document.querySelectorAll('.action-btn.edit').forEach(btn => {
        btn.addEventListener('click', () => editUser(btn.getAttribute('data-user-id')));
    });
    
    document.querySelectorAll('.action-btn.delete').forEach(btn => {
        btn.addEventListener('click', () => deleteUser(btn.getAttribute('data-user-id')));
    });
}

// Просмотр пользователя
function viewUser(userId) {
    fetch(`/1337/users_api.php?action=get_user&id=${userId}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Не удалось загрузить данные пользователя');
        }
        
        const user = data.data.user;
        const modal = document.getElementById('view-user-modal');
        const content = document.getElementById('user-view-content');
        
        content.innerHTML = `
            <div class="profile-content">
                <div class="avatar-container" style="text-align: center;">
                    <img src="${user.avatar_path || '/1337/images/default-avatar.png'}" 
                         alt="Аватар" class="avatar-preview">
                </div>
                <div class="profile-info">
                    <div class="form-group">
                        <label>Логин:</label>
                        <p>${user.username}</p>
                    </div>
                    <div class="form-group">
                        <label>Имя:</label>
                        <p>${user.first_name || 'Не указано'}</p>
                    </div>
                    <div class="form-group">
                        <label>Фамилия:</label>
                        <p>${user.last_name || 'Не указано'}</p>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <p>${user.email || 'Не указан'}</p>
                    </div>
                    <div class="form-group">
                        <label>Телефон:</label>
                        <p>${user.phone || 'Не указан'}</p>
                    </div>
                    <div class="form-group">
                        <label>Дата рождения:</label>
                        <p>${user.birth_date || 'Не указана'}</p>
                    </div>
                    <div class="form-group">
                        <label>Роль:</label>
                        <p>
                            <span class="status-badge ${user.role === 'admin' ? 'status-active' : 'status-inactive'}">
                                ${user.role === 'admin' ? 'Администратор' : 'Пользователь'}
                            </span>
                        </p>
                    </div>
                    <div class="form-group">
                        <label>Дата регистрации:</label>
                        <p>${user.created_at}</p>
                    </div>
                </div>
            </div>
        `;
        
        // Устанавливаем обработчики для кнопок в модальном окне
        document.getElementById('edit-viewed-user-btn').onclick = () => {
            modal.style.display = 'none';
            editUser(userId);
        };
        
        document.getElementById('delete-viewed-user-btn').onclick = () => {
            modal.style.display = 'none';
            deleteUser(userId);
        };
        
        // Показываем модальное окно
        modal.style.display = 'block';
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showError(error.message);
    });
}

// Редактирование пользователя
function editUser(userId) {
    const modal = document.getElementById('user-modal');
    const form = document.getElementById('user-form');
    
    if (userId) {
        // Редактирование существующего пользователя
        document.getElementById('modal-title').textContent = 'Редактирование пользователя';
        document.getElementById('modal-user-id').value = userId;
        
        fetch(`/1337/users_api.php?action=get_user&id=${userId}`, {
            method: 'GET',
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Не удалось загрузить данные пользователя');
            }
            
            const user = data.data.user;
            document.getElementById('modal-username').value = user.username;
            document.getElementById('modal-email').value = user.email;
            document.getElementById('modal-first-name').value = user.first_name || '';
            document.getElementById('modal-last-name').value = user.last_name || '';
            document.getElementById('modal-phone').value = user.phone || '';
            document.getElementById('modal-birth-date').value = user.birth_date || '';
            document.getElementById('modal-role').value = user.role;
            
            // Устанавливаем аватар
            const avatarPreview = document.getElementById('avatar-preview');
            avatarPreview.src = user.avatar_path || '/1337/images/default-avatar.png';
            
            // Пароль оставляем пустым
            document.getElementById('modal-password').value = '';
            
            modal.style.display = 'block';
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showError(error.message);
        });
    } else {
        // Создание нового пользователя
        document.getElementById('modal-title').textContent = 'Создание пользователя';
        document.getElementById('modal-user-id').value = '';
        
        // Очищаем форму
        form.reset();
        document.getElementById('avatar-preview').src = '/1337/images/default-avatar.png';
        modal.style.display = 'block';
    }
}

// Сохранение пользователя
async function saveUser(event) {
    event.preventDefault();
    
    const form = document.getElementById('user-form');
    const modal = document.getElementById('user-modal');
    const saveBtn = form.querySelector('button[type="submit"]');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';
    
    try {
        const formData = new FormData();
        const userId = document.getElementById('modal-user-id').value;
        
        if (userId) {
            formData.append('action', 'update_user');
            formData.append('id', userId);
        } else {
            formData.append('action', 'create_user');
        }
        
        formData.append('username', document.getElementById('modal-username').value);
        formData.append('email', document.getElementById('modal-email').value);
        formData.append('first_name', document.getElementById('modal-first-name').value);
        formData.append('last_name', document.getElementById('modal-last-name').value);
        formData.append('phone', document.getElementById('modal-phone').value);
        formData.append('birth_date', document.getElementById('modal-birth-date').value);
        formData.append('role', document.getElementById('modal-role').value);
        
        const password = document.getElementById('modal-password').value;
        if (password) {
            formData.append('password', password);
        }
        
        const avatarInput = document.getElementById('modal-avatar');
        if (avatarInput.files.length > 0) {
            formData.append('avatar', avatarInput.files[0]);
        }
        
        const response = await fetch('/1337/users_api.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Ошибка сохранения пользователя');
        }
        
        showMessage(userId ? 'Пользователь успешно обновлен' : 'Пользователь успешно создан');
        modal.style.display = 'none';
        loadUsers(); // Обновляем список пользователей
        
    } catch (error) {
        console.error('Ошибка:', error);
        showError(error.message);
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Сохранить';
    }
}

// Удаление пользователя
async function deleteUser(userId) {
    if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) {
        return;
    }
    
    try {
        const response = await fetch(`/1337/users_api.php?action=delete_user&id=${userId}`, {
            method: 'GET',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Не удалось удалить пользователя');
        }
        
        showMessage('Пользователь успешно удален');
        loadUsers(); // Обновляем список пользователей
        
    } catch (error) {
        console.error('Ошибка:', error);
        showError(error.message);
    }
}

// Показать сообщение об успехе
function showMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'profile-message';
    messageDiv.textContent = message;
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.padding = '10px 20px';
    messageDiv.style.backgroundColor = '#4CAF50';
    messageDiv.style.color = 'white';
    messageDiv.style.borderRadius = '4px';
    messageDiv.style.zIndex = '1000';
    messageDiv.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => messageDiv.remove(), 500);
    }, 3000);
}

// Показать сообщение об ошибке
function showError(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'profile-message';
    messageDiv.textContent = message;
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.padding = '10px 20px';
    messageDiv.style.backgroundColor = '#f44336';
    messageDiv.style.color = 'white';
    messageDiv.style.borderRadius = '4px';
    messageDiv.style.zIndex = '1000';
    messageDiv.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => messageDiv.remove(), 500);
    }, 3000);
}