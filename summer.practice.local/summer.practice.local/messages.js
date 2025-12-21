document.addEventListener('DOMContentLoaded', function() {
    // Настройка обработчиков для всех сообщений
    const setupMessageHandlers = () => {
        const messages = document.querySelectorAll('.message-popup, .error-popup');
        const header = document.querySelector('header');
        const headerHeight = header ? header.offsetHeight : 0;
        
        messages.forEach(msg => {
            // Позиционируем сообщение под хедером
            msg.style.top = `${headerHeight}px`;
            msg.style.position = 'fixed';
            msg.style.zIndex = '1001';
            msg.style.width = 'calc(100% - 40px)';
            msg.style.maxWidth = '800px';
            msg.style.left = '50%';
            msg.style.transform = 'translateX(-50%)';
            msg.style.margin = '0';
            
            // Добавляем кнопку закрытия
            const closeBtn = document.createElement('span');
            closeBtn.innerHTML = '&times;';
            closeBtn.className = 'message-close';
            msg.appendChild(closeBtn);
            
            // Обработчики событий (остаются без изменений)
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dismissMessage(msg);
            });
            
            msg.addEventListener('click', () => {
                dismissMessage(msg);
            });
            
            const autoDismiss = setTimeout(() => {
                dismissMessage(msg);
            }, 5000);
            
            msg.addEventListener('mouseenter', () => {
                clearTimeout(autoDismiss);
            });
            
            msg.addEventListener('mouseleave', () => {
                setTimeout(() => {
                    dismissMessage(msg);
                }, 2000);
            });
        });
    };
    
    const dismissMessage = (msg) => {
        if (msg && msg.style.opacity !== '0') {
            msg.style.opacity = '0';
            setTimeout(() => {
                msg.remove();
            }, 500);
        }
    };
    
    setupMessageHandlers();
});