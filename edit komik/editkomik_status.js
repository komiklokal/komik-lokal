document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            const statusBadge = this.nextElementSibling;
            if (statusBadge) {
                statusBadge.textContent = this.value;
                statusBadge.className = 'status-badge ' + this.value.toLowerCase();
            }
        });
    }
});