document.addEventListener('DOMContentLoaded', function() {
    const apiUrl = '/1337/events_api.php';
    const eventsContainer = document.getElementById('events-container');

    // Форматирование даты
    function formatDate(dateString) {
        const options = { 
            day: 'numeric', 
            month: 'long', 
            year: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        return new Date(dateString).toLocaleDateString('ru-RU', options);
    }

    // Загрузка мероприятий
    function loadEvents() {
        eventsContainer.innerHTML = `
            <div class="loading">
                <div class="loading-spinner"></div>
                <p>Загрузка мероприятий...</p>
            </div>
        `;

        fetch(`${apiUrl}?action=get_events`)
            .then(response => response.json())
            .then(data => {
                console.log('Ответ API:', data);

                if (!data.success) {
                    showError(data.message || 'Ошибка при загрузке мероприятий');
                    return;
                }

                if (!data.data || data.data.length === 0) {
                    showNoEvents();
                    return;
                }

                renderEvents(data.data);
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showError('Не удалось загрузить мероприятия');
            });
    }

    // Отображение списка мероприятий
    function renderEvents(events) {
        eventsContainer.innerHTML = '';

        events.forEach(event => {
            const eventCard = document.createElement('div');
            eventCard.className = 'event-card';
            eventCard.innerHTML = `
                <img src="${event.image_path || '/images/default-event.jpg'}" 
                     alt="${event.title}" 
                     class="event-image">
                <div class="event-content">
                    <div class="event-date">
                        <i class="fas fa-calendar-alt"></i>
                        ${formatDate(event.event_date)}
                    </div>
                    <h3 class="event-title">${event.title}</h3>
                    <p class="event-description">${event.description}</p>
                    <button class="btn view-details" data-id="${event.id}">
                        Подробнее
                    </button>
                </div>
            `;
            eventsContainer.appendChild(eventCard);
        });

        // Добавляем обработчики для кнопок "Подробнее"
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                const eventId = this.getAttribute('data-id');
                showEventDetails(eventId);
            });
        });
    }

    // Показать детали мероприятия
    function showEventDetails(eventId) {
        fetch(`${apiUrl}?action=get_event_details`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ event_id: eventId })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Ошибка при загрузке данных мероприятия');
                return;
            }

            const event = data.data;
            alert(`
                ${event.title}\n
                Дата: ${formatDate(event.event_date)}\n
                Описание: ${event.description}
            `);
        })
        .catch(error => {
            console.error('Ошибка:', error);
            alert('Не удалось загрузить данные мероприятия');
        });
    }

    // Показать сообщение об ошибке
    function showError(message) {
        eventsContainer.innerHTML = `
            <div class="no-events">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Ошибка</h3>
                <p>${message}</p>
                <button class="btn retry-btn">Попробовать снова</button>
            </div>
        `;

        document.querySelector('.retry-btn').addEventListener('click', loadEvents);
    }

    // Показать сообщение об отсутствии мероприятий
    function showNoEvents() {
        eventsContainer.innerHTML = `
            <div class="no-events">
                <i class="fas fa-calendar-times"></i>
                <h3>Нет запланированных мероприятий</h3>
                <p>Следите за обновлениями, мы скоро анонсируем новые события!</p>
            </div>
        `;
    }

    // Инициализация
    loadEvents();
});