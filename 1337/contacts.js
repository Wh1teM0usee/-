document.addEventListener('DOMContentLoaded', function() {
    console.group('[Contacts] Инициализация страницы контактов');
    const apiUrl = 'http://p95364dp.beget.tech/1337/contacts_api.php';
    const feedbackForm = document.getElementById('feedback-form');

    // Добавляем уникальный ID для каждого запроса
    let requestCounter = 0;

    // Обработка формы обратной связи
    if (feedbackForm) {
        console.log('[Contacts] Форма обратной связи найдена');
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                name: document.getElementById('name').value.trim(),
                email: document.getElementById('email').value.trim(),
                message: document.getElementById('message').value.trim(),
                action: 'send_feedback',
                requestId: ++requestCounter
            };
            
            console.groupCollapsed(`[Contacts] Отправка формы #${formData.requestId}`);
            console.table(formData);
            console.groupEnd();
            
            sendFeedback(formData);
        });
    } else {
        console.error('[Contacts] Форма обратной связи не найдена!');
    }

    // Отправка сообщения
    function sendFeedback(formData) {
        const submitBtn = feedbackForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.textContent;
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';
        
        const startTime = performance.now();
        
        console.group(`[Contacts] Запрос #${formData.requestId} начат`);
        console.log('Метод: POST');
        console.log('URL:', `${apiUrl}?action=send_feedback`);
        console.log('Данные:', formData);
        
        fetch(`${apiUrl}?action=send_feedback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Request-ID': formData.requestId
            },
            body: JSON.stringify(formData)
        })
        .then(async response => {
            const endTime = performance.now();
            const duration = (endTime - startTime).toFixed(2);
            
            console.log(`[Contacts] Ответ получен за ${duration} мс`);
            console.log('Статус:', response.status);
            
            const clone = response.clone(); // Клонируем response для повторного чтения
            const textResponse = await response.text();
            
            console.log('Ответ (текст):', textResponse);
            
            try {
                const data = JSON.parse(textResponse);
                console.log('Ответ (JSON):', data);
                
                if (!response.ok) {
                    console.error('[Contacts] Ошибка в ответе:', data.message || `HTTP ${response.status}`);
                    throw new Error(data.message || `Ошибка сервера: ${response.status}`);
                }
                
                return data;
            } catch (e) {
                console.error('[Contacts] Ошибка парсинга JSON:', e);
                console.log('Полный ответ:', await clone.text());
                throw new Error('Неверный формат ответа от сервера');
            }
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Ошибка при отправке сообщения');
            }
            
            console.log(`[Contacts] Запрос #${formData.requestId} успешен. ID сообщения:`, data.data.message_id);
            alert('Ваше сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.');
            feedbackForm.reset();
        })
        .catch(error => {
            console.error(`[Contacts] Ошибка в запросе #${formData.requestId}:`, {
                message: error.message,
                stack: error.stack
            });
            alert(`Ошибка: ${error.message}`);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
            console.groupEnd(); // Закрываем группу логов для этого запроса
        });
    }

    console.log('[Contacts] Инициализация завершена');
    console.groupEnd();
});


document.addEventListener('DOMContentLoaded', function() {
    console.group('[Contacts] Инициализация страницы контактов');
    const apiUrl = 'http://p95364dp.beget.tech/1337/contacts_api.php';
    const feedbackForm = document.getElementById('feedback-form');

    // Функция для обновления счетчика символов
    function updateCharCounter(inputElement, counterElement, maxLength) {
        const currentLength = inputElement.value.length;
        counterElement.textContent = `${currentLength}/${maxLength}`;
        
        // Изменяем цвет в зависимости от заполненности
        if (currentLength >= maxLength) {
            counterElement.classList.add('error');
            counterElement.classList.remove('warning');
        } else if (currentLength >= maxLength * 0.8) {
            counterElement.classList.add('warning');
            counterElement.classList.remove('error');
        } else {
            counterElement.classList.remove('warning', 'error');
        }
    }

    // Инициализация счетчиков
    if (feedbackForm) {
        console.log('[Contacts] Форма обратной связи найдена');
        
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const messageInput = document.getElementById('message');
        const nameCounter = document.getElementById('name-counter');
        const emailCounter = document.getElementById('email-counter');
        const messageCounter = document.getElementById('message-counter');
        
        // Обработчики событий для обновления счетчиков
        nameInput.addEventListener('input', () => {
            updateCharCounter(nameInput, nameCounter, 15);
        });
        
        emailInput.addEventListener('input', () => {
            updateCharCounter(emailInput, emailCounter, 20);
        });
        
        messageInput.addEventListener('input', () => {
            updateCharCounter(messageInput, messageCounter, 150);
        });
        
        // Инициализация начальных значений
        updateCharCounter(nameInput, nameCounter, 15);
        updateCharCounter(emailInput, emailCounter, 20);
        updateCharCounter(messageInput, messageCounter, 150);

        // Обработчик отправки формы
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                name: nameInput.value.trim(),
                email: emailInput.value.trim(),
                message: messageInput.value.trim(),
                action: 'send_feedback',
                requestId: ++requestCounter
            };
            
            // Проверка длины полей (на случай, если пользователь изменит maxlength через инструменты разработчика)
            if (formData.name.length > 15) {
                alert('Имя не должно превышать 15 символов');
                nameInput.focus();
                return;
            }
            
            if (formData.email.length > 20) {
                alert('Email не должен превышать 20 символов');
                emailInput.focus();
                return;
            }
            
            if (formData.message.length > 150) {
                alert('Сообщение не должно превышать 150 символов');
                messageInput.focus();
                return;
            }
            
            console.groupCollapsed(`[Contacts] Отправка формы #${formData.requestId}`);
            console.table(formData);
            console.groupEnd();
            
            sendFeedback(formData);
        });
    } else {
        console.error('[Contacts] Форма обратной связи не найдена!');
    }

});