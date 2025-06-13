document.addEventListener('DOMContentLoaded', function() {
    console.group('[About] Инициализация страницы О нас');
    const apiUrl = 'http://p95364dp.beget.tech/1337/about_api.php';
    const feedbackForm = document.getElementById('feedback-form');

    // Добавляем уникальный ID для каждого запроса
    let requestCounter = 0;

    // Обработка формы обратной связи
    if (feedbackForm) {
        console.log('[About] Форма обратной связи найдена');
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                name: document.getElementById('name').value.trim(),
                email: document.getElementById('email').value.trim(),
                message: document.getElementById('message').value.trim(),
                action: 'send_feedback',
                requestId: ++requestCounter
            };
            
            console.groupCollapsed(`[About] Отправка формы #${formData.requestId}`);
            console.table(formData);
            console.groupEnd();
            
            sendFeedback(formData);
        });
    } else {
        console.error('[About] Форма обратной связи не найдена!');
    }

    // Отправка сообщения
    function sendFeedback(formData) {
        const submitBtn = feedbackForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.textContent;
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';
        
        const startTime = performance.now();
        
        console.group(`[About] Запрос #${formData.requestId} начат`);
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
            
            console.log(`[About] Ответ получен за ${duration} мс`);
            console.log('Статус:', response.status);
            
            const clone = response.clone(); // Клонируем response для повторного чтения
            const textResponse = await response.text();
            
            console.log('Ответ (текст):', textResponse);
            
            try {
                const data = JSON.parse(textResponse);
                console.log('Ответ (JSON):', data);
                
                if (!response.ok) {
                    console.error('[About] Ошибка в ответе:', data.message || `HTTP ${response.status}`);
                    throw new Error(data.message || `Ошибка сервера: ${response.status}`);
                }
                
                return data;
            } catch (e) {
                console.error('[About] Ошибка парсинга JSON:', e);
                console.log('Полный ответ:', await clone.text());
                throw new Error('Неверный формат ответа от сервера');
            }
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Ошибка при отправке сообщения');
            }
            
            console.log(`[About] Запрос #${formData.requestId} успешен. ID сообщения:`, data.data.message_id);
            alert('Ваше сообщение успешно отправлено! Мы свяжемся с вами в ближайшее время.');
            feedbackForm.reset();
        })
        .catch(error => {
            console.error(`[About] Ошибка в запросе #${formData.requestId}:`, {
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

    console.log('[About] Инициализация завершена');
    console.groupEnd();
});