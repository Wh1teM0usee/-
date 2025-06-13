document.addEventListener('DOMContentLoaded', function() {
    console.group('[BlogPost] Инициализация страницы статьи');
    
    // Конфигурация
    const config = {
        apiUrl: 'http://p95364dp.beget.tech/1337/blog_api.php',
        defaultImage: '/1337/images/blog-default.jpg',
        blogPageUrl: '/1337/blog.html'
    };
    
    // Элементы DOM
    const postContainer = document.getElementById('post-container');
    
    // Получаем ID статьи из URL
    const postId = getPostIdFromUrl();
    if (!postId) return;
    
    // Загружаем статью
    loadPost(postId);
    
    // Функции ===================================================
    
    function getPostIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const postId = urlParams.get('id');
        
        console.log('[BlogPost] ID статьи из URL:', postId);
        
        if (!postId || !/^\d+$/.test(postId)) {
            const errorMsg = 'Неверный ID статьи в URL';
            console.error('[BlogPost]', errorMsg);
            showError(errorMsg);
            return null;
        }
        
        return postId;
    }
    
    async function loadPost(postId) {
        console.log('[BlogPost] Загрузка статьи ID:', postId);
        
        showLoading();
        
        try {
            const response = await fetchPostData(postId);
            console.log('[BlogPost] Данные статьи:', response);
            
            if (!validatePostData(response.data)) {
                throw new Error('Получены неполные данные статьи');
            }
            
            renderPost(response.data);
        } catch (error) {
            console.error('[BlogPost] Ошибка:', error);
            showError(`Ошибка загрузки: ${error.message}`);
        }
    }
    
    async function fetchPostData(postId) {
        const url = `${config.apiUrl}?action=get_post&id=${postId}`;
        console.log('[BlogPost] Запрос к API:', url);
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        });
        
        console.log('[BlogPost] Статус ответа:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Ошибка при получении данных');
        }
        
        return data;
    }
    
    function validatePostData(post) {
        if (!post) return false;
        if (!post.title || !post.content) return false;
        return true;
    }
    
    function renderPost(post) {
    console.log('[BlogPost] Рендеринг статьи:', post);
    
    // Используем full_content если он есть, иначе content
    const postContent = post.full_content || post.content;
    
    const imageUrl = post.image_path 
        ? post.image_path.replace(/[!?]/g, m => m === '!' ? '1' : '2')
        : config.defaultImage;
    
    const html = `
        <div class="post-header">
            <div class="post-date">${formatDate(post.created_at)}</div>
            <h1 class="post-title">${escapeHtml(post.title)}</h1>
        </div>
        
        <img src="${imageUrl}" alt="${escapeHtml(post.title)}" 
             class="post-image"
             onerror="this.src='${config.defaultImage}'">
        
        <div class="post-content">
            ${postContent.replace(/\n/g, '<br><br>')}
        </div>
        
        <a href="${config.blogPageUrl}" class="back-link">
            ← Вернуться к списку статей
        </a>
    `;
    
    postContainer.innerHTML = html;
    console.log('[BlogPost] Статья успешно отображена');
}
    
    function formatDate(dateString) {
        try {
            if (!dateString) return 'Дата не указана';
            
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            
            const date = new Date(dateString);
            return date.toLocaleDateString('ru-RU', options);
        } catch (e) {
            console.error('[BlogPost] Ошибка форматирования даты:', e);
            return dateString || 'Дата не указана';
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showLoading() {
        postContainer.innerHTML = `
            <div class="loading">
                <div class="loading-spinner"></div>
                <p>Загрузка статьи...</p>
            </div>
        `;
    }
    
    function showError(message) {
        postContainer.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Ошибка</h3>
                <p>${message}</p>
                <a href="${config.blogPageUrl}" class="btn">
                    Вернуться в блог
                </a>
            </div>
        `;
    }
    
    console.groupEnd();
});