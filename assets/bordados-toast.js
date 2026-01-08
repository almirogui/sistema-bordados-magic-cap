/**
 * SISTEMA TOAST PARA BORDADOS - VERSÃO SEGURA
 * Arquivo: bordados-toast.js
 * Local: wp-content/plugins/sistema-bordados-simples/assets/bordados-toast.js
 */

console.log('🍞 Sistema Toast Bordados carregando...');

// Namespace global para evitar conflitos
window.BordadosToast = window.BordadosToast || {};

/**
 * Inicializar sistema Toast
 */
window.BordadosToast.init = function() {
    // Verificar se já foi inicializado
    if (document.getElementById('bordados-toast-container')) {
        console.log('⚠️ Toast já inicializado');
        return;
    }
    
    try {
        console.log('🔧 Inicializando sistema Toast...');
        
        // Criar container do Toast
        const container = document.createElement('div');
        container.id = 'bordados-toast-container';
        container.className = 'bordados-toast-container';
        
        // Estilos CSS inline para garantir funcionamento
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            pointer-events: none;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        `;
        
        // Adicionar ao body
        document.body.appendChild(container);
        
        console.log('✅ Container Toast criado');
        
        // Adicionar CSS completo
        window.BordadosToast.addCSS();
        
        return true;
        
    } catch (error) {
        console.error('❌ Erro ao inicializar Toast:', error);
        return false;
    }
};

/**
 * Adicionar CSS do sistema Toast
 */
window.BordadosToast.addCSS = function() {
    // Verificar se CSS já foi adicionado
    if (document.getElementById('bordados-toast-css')) {
        return;
    }
    
    const style = document.createElement('style');
    style.id = 'bordados-toast-css';
    style.textContent = `
        /* ===================================
           SISTEMA TOAST BORDADOS
           =================================== */
        
        .bordados-toast-container {
            position: fixed !important;
            top: 20px !important;
            right: 20px !important;
            z-index: 99999 !important;
            pointer-events: none !important;
            max-width: 400px !important;
        }
        
        .bordados-toast {
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
            margin-bottom: 15px !important;
            padding: 16px 20px !important;
            min-height: 60px !important;
            display: flex !important;
            align-items: center !important;
            position: relative !important;
            overflow: hidden !important;
            opacity: 0 !important;
            transform: translateX(100%) !important;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
            pointer-events: auto !important;
            border-left: 4px solid #007cba !important;
            cursor: pointer !important;
        }
        
        .bordados-toast.show {
            opacity: 1 !important;
            transform: translateX(0) !important;
        }
        
        .bordados-toast.hide {
            opacity: 0 !important;
            transform: translateX(100%) !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            min-height: 0 !important;
            max-height: 0 !important;
        }
        
        /* Tipos de Toast */
        .bordados-toast.success {
            border-left-color: #28a745 !important;
        }
        
        .bordados-toast.error {
            border-left-color: #dc3545 !important;
        }
        
        .bordados-toast.warning {
            border-left-color: #ffc107 !important;
        }
        
        .bordados-toast.info {
            border-left-color: #17a2b8 !important;
        }
        
        /* Conteúdo do Toast */
        .toast-icon {
            font-size: 24px !important;
            margin-right: 12px !important;
            flex-shrink: 0 !important;
        }
        
        .toast-content {
            flex: 1 !important;
            line-height: 1.4 !important;
        }
        
        .toast-title {
            font-weight: 600 !important;
            color: #333 !important;
            margin: 0 0 4px 0 !important;
            font-size: 15px !important;
        }
        
        .toast-message {
            color: #666 !important;
            margin: 0 !important;
            font-size: 14px !important;
        }
        
        .toast-close {
            background: none !important;
            border: none !important;
            color: #999 !important;
            font-size: 18px !important;
            cursor: pointer !important;
            padding: 4px !important;
            margin-left: 8px !important;
            border-radius: 4px !important;
            transition: background-color 0.2s !important;
            flex-shrink: 0 !important;
        }
        
        .toast-close:hover {
            background: #f5f5f5 !important;
            color: #333 !important;
        }
        
        /* Barra de progresso */
        .toast-progress {
            position: absolute !important;
            bottom: 0 !important;
            left: 0 !important;
            height: 3px !important;
            background: rgba(0,0,0,0.1) !important;
            width: 100% !important;
            animation: toastProgress linear !important;
        }
        
        .bordados-toast.success .toast-progress {
            background: #28a745 !important;
        }
        
        .bordados-toast.error .toast-progress {
            background: #dc3545 !important;
        }
        
        .bordados-toast.warning .toast-progress {
            background: #ffc107 !important;
        }
        
        .bordados-toast.info .toast-progress {
            background: #17a2b8 !important;
        }
        
        @keyframes toastProgress {
            from {
                width: 100%;
            }
            to {
                width: 0%;
            }
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .bordados-toast-container {
                top: 10px !important;
                right: 10px !important;
                left: 10px !important;
                max-width: none !important;
            }
            
            .bordados-toast {
                padding: 14px 16px !important;
                border-radius: 8px !important;
            }
            
            .toast-icon {
                font-size: 20px !important;
                margin-right: 10px !important;
            }
            
            .toast-title {
                font-size: 14px !important;
            }
            
            .toast-message {
                font-size: 13px !important;
            }
        }
        
        /* Hover effects */
        .bordados-toast:hover .toast-progress {
            animation-play-state: paused !important;
        }
        
        .bordados-toast:hover {
            box-shadow: 0 12px 35px rgba(0,0,0,0.2) !important;
            transform: translateY(-2px) !important;
        }
    `;
    
    document.head.appendChild(style);
    console.log('✅ CSS Toast adicionado');
};

/**
 * Mostrar Toast
 */
window.BordadosToast.show = function(options) {
    try {
        // Verificar se sistema foi inicializado
        let container = document.getElementById('bordados-toast-container');
        if (!container) {
            console.log('⚠️ Container não encontrado, inicializando...');
            if (!window.BordadosToast.init()) {
                throw new Error('Falha ao inicializar Toast');
            }
            container = document.getElementById('bordados-toast-container');
        }
        
        // Configurações padrão
        const config = {
            type: options.type || 'info',
            title: options.title || '',
            message: options.message || 'Mensagem',
            duration: options.duration || 5000,
            closable: options.closable !== false,
            ...options
        };
        
        console.log('🍞 Criando Toast:', config);
        
        // Ícones por tipo
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        // Criar elemento Toast
        const toast = document.createElement('div');
        toast.className = `bordados-toast ${config.type}`;
        
        // HTML do Toast
        let html = `
            <div class="toast-icon">${icons[config.type] || icons.info}</div>
            <div class="toast-content">
        `;
        
        if (config.title) {
            html += `<div class="toast-title">${config.title}</div>`;
        }
        
        html += `<div class="toast-message">${config.message}</div>
            </div>
        `;
        
        if (config.closable) {
            html += `<button class="toast-close" type="button">×</button>`;
        }
        
        if (config.duration > 0) {
            html += `<div class="toast-progress" style="animation-duration: ${config.duration}ms"></div>`;
        }
        
        toast.innerHTML = html;
        
        // Event listeners
        if (config.closable) {
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => {
                window.BordadosToast.hide(toast);
            });
        }
        
        // Fechar ao clicar no toast
        toast.addEventListener('click', () => {
            if (config.closeOnClick !== false) {
                window.BordadosToast.hide(toast);
            }
        });
        
        // Adicionar ao container
        container.appendChild(toast);
        
        // Mostrar com animação
        setTimeout(() => {
            toast.classList.add('show');
        }, 50);
        
        // Auto-remover se duration > 0
        if (config.duration > 0) {
            setTimeout(() => {
                window.BordadosToast.hide(toast);
            }, config.duration);
        }
        
        console.log('✅ Toast criado e exibido');
        return toast;
        
    } catch (error) {
        console.error('❌ Erro ao criar Toast:', error);
        
        // Fallback para alert
        const fallbackMsg = (options.title ? options.title + ': ' : '') + options.message;
        alert(fallbackMsg);
        
        return null;
    }
};

/**
 * Esconder Toast
 */
window.BordadosToast.hide = function(toast) {
    if (!toast || !toast.parentNode) return;
    
    toast.classList.add('hide');
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 400);
};

/**
 * Métodos de conveniência
 */
window.BordadosToast.success = function(message, title = 'Sucesso!') {
    return window.BordadosToast.show({
        type: 'success',
        title: title,
        message: message,
        duration: 6000
    });
};

window.BordadosToast.error = function(message, title = 'Erro!') {
    return window.BordadosToast.show({
        type: 'error',
        title: title,
        message: message,
        duration: 8000
    });
};

window.BordadosToast.warning = function(message, title = 'Atenção!') {
    return window.BordadosToast.show({
        type: 'warning',
        title: title,
        message: message,
        duration: 7000
    });
};

window.BordadosToast.info = function(message, title = 'Informação') {
    return window.BordadosToast.show({
        type: 'info',
        title: title,
        message: message,
        duration: 5000
    });
};

/**
 * Limpar todos os Toasts
 */
window.BordadosToast.clear = function() {
    const container = document.getElementById('bordados-toast-container');
    if (container) {
        const toasts = container.querySelectorAll('.bordados-toast');
        toasts.forEach(toast => {
            window.BordadosToast.hide(toast);
        });
    }
};

// Auto-inicialização quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 DOM carregado - inicializando Toast...');
    window.BordadosToast.init();
});

// Inicialização alternativa se DOM já estiver carregado
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.BordadosToast.init);
} else {
    // DOM já carregado
    setTimeout(window.BordadosToast.init, 100);
}

console.log('✅ Sistema Toast Bordados carregado e pronto!');