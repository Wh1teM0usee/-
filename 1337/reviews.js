document.addEventListener('DOMContentLoaded', function() {
    const apiUrl = 'http://p95364dp.beget.tech/1337/reviews_api.php';
    const reviewsList = document.getElementById('reviews-list');
    const addReviewForm = document.getElementById('add-review-form');
    const ratingStars = document.getElementById('rating-stars');
    const ratingValue = document.getElementById('rating-value');
    const reviewFormContainer = document.getElementById('review-form-container');
    const submitReviewBtn = document.getElementById('submit-review');
    const reviewTextArea = document.getElementById('review-text');
    const charCounter = document.getElementById('char-counter');

    // Инициализация счетчика символов
    if (reviewTextArea && charCounter) {
        reviewTextArea.addEventListener('input', function() {
            const currentLength = this.value.length;
            charCounter.textContent = `${currentLength}/200`;
            
            if (currentLength > 200) {
                charCounter.classList.add('limit-exceeded');
            } else {
                charCounter.classList.remove('limit-exceeded');
            }
        });
    }

    // Инициализация звезд рейтинга
    function initRatingStars() {
        const stars = ratingStars.querySelectorAll('.star');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                ratingValue.value = rating;
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseover', function() {
                const hoverRating = parseInt(this.getAttribute('data-rating'));
                
                stars.forEach((s, index) => {
                    if (index < hoverRating) {
                        s.classList.add('hover');
                    } else {
                        s.classList.remove('hover');
                    }
                });
            });
            
            star.addEventListener('mouseout', function() {
                stars.forEach(s => s.classList.remove('hover'));
            });
        });
    }

    // Загрузка отзывов
    function loadReviews() {
        reviewsList.innerHTML = `
            <div class="loading">
                <div class="loading-spinner"></div>
                <p>Загрузка отзывов...</p>
            </div>
        `;

        fetch(`${apiUrl}?action=get_reviews`, {
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
                showNoReviews();
                return;
            }

            renderReviews(data.data);
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showError(`Не удалось загрузить отзывы: ${error.message}`);
        });
    }

    // Отображение списка отзывов
    function renderReviews(reviews) {
        reviewsList.innerHTML = '';
        
        if (reviews.length === 0) {
            showNoReviews();
            return;
        }
        
        const reviewsGrid = document.createElement('div');
        reviewsGrid.className = 'reviews-grid';
        
        reviews.forEach(review => {
            const reviewCard = document.createElement('div');
            reviewCard.className = 'review-card';
            
            // Получаем первую букву имени автора
            const authorInitial = review.author_name ? review.author_name.charAt(0).toUpperCase() : 'Г';
            
            // Форматируем дату
            const reviewDate = new Date(review.created_at);
            const formattedDate = reviewDate.toLocaleDateString('ru-RU', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Создаем звезды рейтинга
            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                starsHtml += i <= review.rating ? '★' : '☆';
            }
            
            reviewCard.innerHTML = `
                <div class="review-author">${authorInitial}</div>
                <div class="review-text">${review.text}</div>
                <div class="review-rating" title="Оценка: ${review.rating}">${starsHtml}</div>
                <div class="review-date">${formattedDate}</div>
            `;
            
            reviewsGrid.appendChild(reviewCard);
        });
        
        reviewsList.appendChild(reviewsGrid);
    }

    // Отправка нового отзыва
    function submitReview(event) {
        event.preventDefault();
        
        const reviewText = document.getElementById('review-text').value.trim();
        const rating = parseInt(ratingValue.value);
        
        if (!reviewText || rating < 1 || rating > 5) {
            alert('Пожалуйста, заполните все поля и выберите оценку');
            return;
        }
        
        if (reviewText.length > 200) {
            alert('Текст отзыва не должен превышать 200 символов');
            return;
        }
        
        submitReviewBtn.disabled = true;
        submitReviewBtn.textContent = 'Отправка...';
        
        fetch(`${apiUrl}?action=add_review`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                text: reviewText,
                rating: rating
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
                throw new Error(data.message || 'Ошибка при отправке отзыва');
            }
            
            // Очищаем форму
            document.getElementById('review-text').value = '';
            ratingValue.value = '0';
            ratingStars.querySelectorAll('.star').forEach(star => {
                star.classList.remove('active');
            });
            
            // Обновляем счетчик символов
            charCounter.textContent = '0/200';
            charCounter.classList.remove('limit-exceeded');
            
            // Обновляем список отзывов
            loadReviews();
            
            // Показываем сообщение об успехе
            alert('Ваш отзыв успешно добавлен! Спасибо!');
        })
        .catch(error => {
            console.error('Ошибка:', error);
            alert(`Ошибка: ${error.message}`);
        })
        .finally(() => {
            submitReviewBtn.disabled = false;
            submitReviewBtn.textContent = 'Отправить отзыв';
        });
    }

    // Проверка авторизации для формы отзыва
    function checkAuthForReviewForm() {
        fetch('/1337/auth_check.php', {
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.logged_in) {
                reviewFormContainer.innerHTML = `
                    <div class="auth-required">
                        <p>Чтобы оставить отзыв, пожалуйста, <a href="/1337/login.html">войдите</a> или <a href="/1337/register.html">зарегистрируйтесь</a>.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Ошибка проверки авторизации:', error);
        });
    }

    // Показать сообщение об ошибке
    function showError(message) {
        reviewsList.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Ошибка</h3>
                <p>${message}</p>
                <button class="btn retry-btn">Попробовать снова</button>
            </div>
        `;

        document.querySelector('.retry-btn').addEventListener('click', loadReviews);
    }

    // Показать сообщение об отсутствии отзывов
    function showNoReviews() {
        reviewsList.innerHTML = `
            <div class="no-reviews">
                <i class="fas fa-comment-alt"></i>
                <h3>Отзывов пока нет</h3>
                <p>Будьте первым, кто оставит отзыв о нашем отеле!</p>
            </div>
        `;
    }

    // Инициализация
    initRatingStars();
    loadReviews();
    checkAuthForReviewForm();
    
    if (addReviewForm) {
        addReviewForm.addEventListener('submit', submitReview);
    }
});