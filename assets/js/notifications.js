/**
 * Kalibrasyon Belge Yönetim Sistemi - Bildirim Sistemi
 * ---------------------------------------------------
 * Bu dosya, sitede kullanılan bildirim sistemini yönetir.
 */

// Bildirim gösterme fonksiyonu
function showNotification(title, message, type = 'info', duration = 5000) {
    // Bootstrap Toast bildirimi için CSS ekle
    var cssId = 'toastCSS';
    if (!document.getElementById(cssId)) {
        var head = document.getElementsByTagName('head')[0];
        var style = document.createElement('style');
        style.id = cssId;
        style.innerHTML = `
            .notification-container {
                position: fixed;
                bottom: 15px;
                right: 15px;
                z-index: 9999;
            }
            .toast {
                min-width: 300px;
                opacity: 0;
                transition: opacity 0.3s ease-in-out;
            }
            .toast.show {
                opacity: 1;
            }
            .toast:not(:last-child) {
                margin-bottom: 10px;
            }
        `;
        head.appendChild(style);
    }
    
    // Container oluştur
    var container = document.querySelector('.notification-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    
    // Toast bildirimini oluştur
    var toastId = 'toast-' + Date.now();
    var backgroundColor = 'bg-info';
    var icon = '<i class="bi bi-info-circle"></i>';
    
    if (type === 'success') {
        backgroundColor = 'bg-success';
        icon = '<i class="bi bi-check-circle"></i>';
    } else if (type === 'danger' || type === 'error') {
        backgroundColor = 'bg-danger';
        icon = '<i class="bi bi-exclamation-circle"></i>';
    } else if (type === 'warning') {
        backgroundColor = 'bg-warning';
        icon = '<i class="bi bi-exclamation-triangle"></i>';
    }
    
    var toast = document.createElement('div');
    toast.className = 'toast';
    toast.id = toastId;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.setAttribute('data-bs-delay', duration);
    toast.innerHTML = `
        <div class="toast-header ${backgroundColor} text-white">
            ${icon} <strong class="me-auto">${title}</strong>
            <small>Şimdi</small>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
    `;
    
    container.appendChild(toast);
    
    // Animasyon için timeout ekle
    setTimeout(function() {
        // Bootstrap Toast'ı başlat
        var toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();
    }, 10);
    
    // Toast otomatik temizleme (DOM'dan kaldırma)
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Başarı bildirimi
function showSuccess(message, title = 'Başarılı', duration = 5000) {
    showNotification(title, message, 'success', duration);
}

// Hata bildirimi
function showError(message, title = 'Hata', duration = 5000) {
    showNotification(title, message, 'danger', duration);
}

// Uyarı bildirimi
function showWarning(message, title = 'Uyarı', duration = 5000) {
    showNotification(title, message, 'warning', duration);
}

// Bilgi bildirimi
function showInfo(message, title = 'Bilgi', duration = 5000) {
    showNotification(title, message, 'info', duration);
}

// Sayfa geçiş animasyonları için
document.addEventListener('DOMContentLoaded', function() {
    // Sayfa içeriğini gösteren animasyon
    const pageContent = document.querySelector('.container');
    if (pageContent) {
        pageContent.style.opacity = 0;
        pageContent.style.transition = 'opacity 0.3s ease-in-out';
        setTimeout(() => {
            pageContent.style.opacity = 1;
        }, 100);
    }
    
    // Link tıklamalarını yakala (sayfa geçişi için)
    document.querySelectorAll('a').forEach(link => {
        // Dış bağlantıları, indirme bağlantılarını veya özel bağlantıları atla
        if (link.getAttribute('target') === '_blank' || 
            link.getAttribute('download') || 
            link.getAttribute('href') === '#' ||
            link.getAttribute('href').startsWith('javascript:') ||
            link.getAttribute('data-bs-toggle')) {
            return;
        }
        
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Aynı sayfa içi bağlantıları atla
            if (href.startsWith('#')) {
                return;
            }
            
            e.preventDefault();
            
            // Sayfa içeriğini gizle
            if (pageContent) {
                pageContent.style.opacity = 0;
                
                // Çıkış animasyonu tamamlandıktan sonra sayfaya git
                setTimeout(() => {
                    window.location.href = href;
                }, 300);
            } else {
                window.location.href = href;
            }
        });
    });
    
    // Form gönderimlerini yakala
    document.querySelectorAll('form').forEach(form => {
        // AJAX formlarını atla
        if (form.getAttribute('data-ajax') === 'true') {
            return;
        }
        
        form.addEventListener('submit', function(e) {
            // Sayfa içeriğini gizle
            if (pageContent) {
                pageContent.style.opacity = 0;
            }
        });
    });
});