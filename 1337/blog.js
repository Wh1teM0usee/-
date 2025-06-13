document.addEventListener('DOMContentLoaded', function() {
    console.group('[Blog] Инициализация страницы блога');
    const apiUrl = 'http://p95364dp.beget.tech/1337/blog_api.php';
    const postsContainer = document.getElementById('blog-posts-container');

    // Загрузка постов
    function loadPosts() {
        console.log('[Blog] Загрузка списка статей');
        postsContainer.innerHTML = `
            <div class="loading">
                <div class="loading-spinner"></div>
                <p>Загрузка статей...</p>
            </div>
        `;

        fetch(`${apiUrl}?action=get_posts`, {
            credentials: 'include'
        })
        .then(async response => {
            console.log('[Blog] Ответ получен. Статус:', response.status);
            
            const data = await response.json();
            console.log('[Blog] Полученные данные:', data);
            
            if (!response.ok) {
                throw new Error(data.message || `Ошибка сервера: ${response.status}`);
            }

            if (!data || data.success === false) {
                throw new Error(data?.message || 'Неверный формат данных');
            }

            if (!data.data || data.data.length === 0) {
                showNoPosts();
                return;
            }

            renderPosts(data.data);
        })
        .catch(error => {
            console.error('[Blog] Ошибка загрузки статей:', error);
            showError(`Не удалось загрузить статьи: ${error.message}`);
        });
    }

    // Отображение постов
    function renderPosts(posts) {
        console.log('[Blog] Рендеринг статей. Количество:', posts.length);
        
        postsContainer.innerHTML = '';

        posts.forEach(post => {
            const postElement = document.createElement('div');
            postElement.className = 'blog-post';
            
            // Формируем путь к изображению
            const imagePath = post.image_path 
                ? post.image_path.replace('!', '1').replace('?', '2') // исправляем пути из примера
                : '/1337/images/blog-default.jpg';
            
            // Обрезаем контент для превью
            const excerpt = post.content.length > 150 
                ? post.content.substring(0, 150) + '...' 
                : post.content;
            
            postElement.innerHTML = `
                <img src="${imagePath}" alt="${post.title}" class="post-image">
                <div class="post-content">
                    <div class="post-date">${formatDate(post.created_at)}</div>
                    <h3 class="post-title">${post.title}</h3>
                    <p class="post-excerpt">${excerpt}</p>
                    <a href="/1337/blog-post.html?id=${post.id}" class="read-more">Читать далее →</a>
                </div>
            `;
            
            postsContainer.appendChild(postElement);
        });
    }

    // Форматирование даты
    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('ru-RU', options);
    }

    // Показать сообщение об ошибке
    function showError(message) {
        console.error('[Blog] Отображение ошибки:', message);
        
        postsContainer.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Ошибка</h3>
                <p>${message}</p>
                <button class="btn retry-btn">Попробовать снова</button>
            </div>
        `;

        document.querySelector('.retry-btn').addEventListener('click', loadPosts);
    }

    // Показать сообщение об отсутствии постов
    function showNoPosts() {
        console.log('[Blog] Нет статей для отображения');
        
        postsContainer.innerHTML = `
            <div class="no-posts">
                <i class="fas fa-newspaper"></i>
                <h3>Статей пока нет</h3>
                <p>Загляните сюда позже, мы готовим для вас интересные материалы.</p>
            </div>
        `;
    }

    // Инициализация
    loadPosts();
    console.log('[Blog] Инициализация завершена');
    console.groupEnd();
});