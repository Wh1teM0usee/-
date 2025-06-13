document.addEventListener('DOMContentLoaded', function() {
    const apiUrl = 'http://p95364dp.beget.tech/1337/services_api.php';
    const servicesContainer = document.getElementById('services-container');

    // Загрузка услуг
    function loadServices() {
        servicesContainer.innerHTML = `
            <div class="loading">
                <div class="loading-spinner"></div>
                <p>Загрузка услуг...</p>
            </div>
        `;

        fetch(`${apiUrl}?action=get_services`, {
            credentials: 'include'
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errData => {
                    throw new Error(errData.message || `Ошибка сервера: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data || data.success === false) {
                throw new Error(data?.message || 'Неверный формат данных');
            }

            if (!data.data || data.data.length === 0) {
                showNoServices();
                return;
            }

            renderServices(data.data);
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showError(`Не удалось загрузить услуги: ${error.message}`);
        });
    }

    // Отображение списка услуг
    function renderServices(services) {
        servicesContainer.innerHTML = '';

        services.forEach(service => {
            const serviceCard = document.createElement('div');
            serviceCard.className = 'service-card';
            serviceCard.innerHTML = `
                <div class="service-icon">
                    <i class="fas ${service.icon || 'fa-concierge-bell'}"></i>
                </div>
                <h3 class="service-title">${service.title}</h3>
                <p class="service-description">${service.description}</p>
                ${service.price ? `<div class="service-price">${service.price} ₽</div>` : ''}
                <button class="btn book-service" data-id="${service.id}" data-title="${service.title}">
                    Заказать
                </button>
            `;
            servicesContainer.appendChild(serviceCard);
        });

        // Обработчики для кнопок "Заказать"
        document.querySelectorAll('.book-service').forEach(button => {
            button.addEventListener('click', function() {
                const serviceId = this.getAttribute('data-id');
                const serviceTitle = this.getAttribute('data-title');
                bookService(serviceId, serviceTitle);
            });
        });
    }

    // Заказ услуги
    function bookService(serviceId, serviceTitle) {
        // Проверка авторизации и отправка заказа
        fetch(`${apiUrl}?action=book_service`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                service_id: serviceId,
                service_title: serviceTitle
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errData => {
                    throw new Error(errData.message || `Ошибка сервера: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Ошибка при заказе услуги');
            }
            
            alert(`Услуга "${serviceTitle}" успешно заказана!`);
        })
        .catch(error => {
            console.error('Ошибка:', error);
            alert(`Ошибка: ${error.message}`);
        });
    }

    // Показать сообщение об ошибке
    function showError(message) {
        servicesContainer.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Ошибка</h3>
                <p>${message}</p>
                <button class="btn retry-btn">Попробовать снова</button>
            </div>
        `;

        document.querySelector('.retry-btn').addEventListener('click', loadServices);
    }

    // Показать сообщение об отсутствии услуг
    function showNoServices() {
        servicesContainer.innerHTML = `
            <div class="no-services">
                <i class="fas fa-concierge-bell"></i>
                <h3>Услуги временно недоступны</h3>
                <p>Приносим извинения за временные неудобства. Пожалуйста, попробуйте позже.</p>
            </div>
        `;
    }

    // Инициализация
    loadServices();
});