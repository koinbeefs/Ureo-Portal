// TAU-UREO Portal Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll messages to bottom
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // File upload drag and drop
    const fileUploadArea = document.querySelector('.file-upload-area');
    if (fileUploadArea) {
        const fileInput = document.getElementById('document_file');
        
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            if (e.dataTransfer.files.length && fileInput) {
                fileInput.files = e.dataTransfer.files;
            }
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Auto-refresh for real-time updates (every 30 seconds)
    setInterval(function() {
        // Check for new messages or status updates via AJAX
        checkForUpdates();
    }, 30000);
});

function checkForUpdates() {
    // AJAX call to check for updates
    fetch('check-updates.php')
        .then(response => response.json())
        .then(data => {
            if (data.hasUpdates) {
                showNotification('You have new updates!', 'info');
            }
        })
        .catch(error => console.error('Error checking updates:', error));
}

function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
