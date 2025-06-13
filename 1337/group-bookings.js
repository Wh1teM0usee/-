document.addEventListener('DOMContentLoaded', function() {
    console.group('[GroupBookings] Инициализация страницы групповых заездов');
    const apiUrl = 'http://p95364dp.beget.tech/1337/group_bookings_api.php';
    const bookingForm = document.getElementById('group-booking-form');

    // Элементы формы
    const nameInput = document.getElementById('gb-name');
    const phoneInput = document.getElementById('gb-phone');
    const emailInput = document.getElementById('gb-email');
    const companyInput = document.getElementById('gb-company');
    const notesInput = document.getElementById('gb-notes');
    
    // Счетчики символов
    const nameCounter = document.getElementById('name-counter');
    const phoneCounter = document.getElementById('phone-counter');
    const emailCounter = document.getElementById('email-counter');
    const companyCounter = document.getElementById('company-counter');
    const notesCounter = document.getElementById('notes-counter');

    // Лимиты символов
    const limits = {
        name: 30,
        phone: 12,
        email: 50,
        company: 50,
        notes: 300
    };

    // Функция для обновления счетчиков символов
    function updateCounters() {
        nameCounter.textContent = `${nameInput.value.length}/${limits.name}`;
        phoneCounter.textContent = `${phoneInput.value.length}/${limits.phone}`;
        emailCounter.textContent = `${emailInput.value.length}/${limits.email}`;
        companyCounter.textContent = `${companyInput.value.length}/${limits.company}`;
        notesCounter.textContent = `${notesInput.value.length}/${limits.notes}`;

        // Подсветка при превышении лимита
        nameCounter.classList.toggle('limit-exceeded', nameInput.value.length > limits.name);
        phoneCounter.classList.toggle('limit-exceeded', phoneInput.value.length > limits.phone);
        emailCounter.classList.toggle('limit-exceeded', emailInput.value.length > limits.email);
        companyCounter.classList.toggle('limit-exceeded', companyInput.value.length > limits.company);
        notesCounter.classList.toggle('limit-exceeded', notesInput.value.length > limits.notes);
    }

    // Маска для телефона (автоматическое добавление +7)
    phoneInput.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 0) {
            value = value[0] === '7' ? '+' + value : '+7' + value;
            this.value = value.substring(0, limits.phone);
        } else {
            this.value = '';
        }
        updateCounters();
    });

    // Обработчики изменения полей
    [nameInput, emailInput, companyInput, notesInput].forEach(input => {
        input.addEventListener('input', function() {
            if (this.maxLength > 0) {
                this.value = this.value.substring(0, this.maxLength);
            }
            updateCounters();
        });
    });

    // Инициализация счетчиков
    updateCounters();

    // Обработка формы группового бронирования
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Валидация данных
            const errors = [];
            
            // Проверка длины полей
            if (nameInput.value.length > limits.name) {
                errors.push(`Имя не должно превышать ${limits.name} символов`);
            }
            
            if (!/^\+7\d{10}$/.test(phoneInput.value)) {
                errors.push('Телефон должен быть в формате +79531445679');
            }
            
            if (emailInput.value.length > limits.email) {
                errors.push(`Email не должен превышать ${limits.email} символов`);
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)) {
                errors.push('Введите корректный email');
            }
            
            if (companyInput.value.length > limits.company) {
                errors.push(`Название компании не должно превышать ${limits.company} символов`);
            }
            
            if (notesInput.value.length > limits.notes) {
                errors.push(`Дополнительные пожелания не должны превышать ${limits.notes} символов`);
            }
            
            // Проверка обязательных полей
            if (!nameInput.value.trim()) errors.push('Введите ваше имя');
            if (!phoneInput.value.trim()) errors.push('Введите телефон');
            if (!emailInput.value.trim()) errors.push('Введите email');
            if (!document.getElementById('gb-checkin').value) errors.push('Укажите дату заезда');
            if (!document.getElementById('gb-checkout').value) errors.push('Укажите дату выезда');
            if (!document.getElementById('gb-guests').value) errors.push('Укажите количество гостей');
            if (!document.getElementById('gb-type').value) errors.push('Выберите тип группы');
            
            // Если есть ошибки - показываем их
            if (errors.length > 0) {
                alert('Ошибки в форме:\n\n' + errors.join('\n'));
                return;
            }
            
            // Подготовка данных для отправки
            const formData = {
                name: nameInput.value.trim(),
                phone: phoneInput.value.trim(),
                email: emailInput.value.trim(),
                company: companyInput.value.trim(),
                checkin: document.getElementById('gb-checkin').value,
                checkout: document.getElementById('gb-checkout').value,
                guests: document.getElementById('gb-guests').value,
                rooms: document.getElementById('gb-rooms').value || null,
                group_type: document.getElementById('gb-type').value,
                notes: notesInput.value.trim(),
                action: 'group_booking_request'
            };
            
            // Отправка данных
            sendGroupBookingRequest(formData);
        });
    } else {
        console.error('[GroupBookings] Форма группового бронирования не найдена!');
    }

    // Функция отправки запроса на сервер
    function sendGroupBookingRequest(formData) {
        const submitBtn = bookingForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.textContent;
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';
        
        console.group('Отправка запроса на групповое бронирование');
        console.log('Данные формы:', formData);
        
        fetch(`${apiUrl}?action=group_booking_request`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(async response => {
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || `Ошибка сервера: ${response.status}`);
            }
            
            if (!data.success) {
                throw new Error(data.message || 'Неизвестная ошибка');
            }
            
            console.log('Успешный ответ:', data);
            alert('Ваш запрос на групповое бронирование успешно отправлен! Наш менеджер свяжется с вами в ближайшее время.');
            bookingForm.reset();
            updateCounters();
        })
        .catch(error => {
            console.error('Ошибка при отправке запроса:', error);
            alert(`Ошибка: ${error.message}`);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
            console.groupEnd();
        });
    }

    console.log('[GroupBookings] Инициализация завершена');
    console.groupEnd();
});