document.addEventListener('DOMContentLoaded', function() {
    const filterBtn = document.getElementById('filterBtn');
    const filterDropdown = document.getElementById('filterDropdown');
    const resetBtn = document.getElementById('resetBtn');
    const searchForm = document.querySelector('.search-form');

    if (filterBtn && filterDropdown) {
        filterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            filterDropdown.classList.toggle('active');
        });

        document.addEventListener('click', function(e) {
            if (!filterDropdown.contains(e.target) && e.target !== filterBtn) {
                filterDropdown.classList.remove('active');
            }
        });

        filterDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    const filterHeaders = document.querySelectorAll('.filter-header');
    filterHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const toggleType = this.getAttribute('data-toggle');
            const optionsDiv = document.getElementById(toggleType + 'Options');
            
            if (optionsDiv) {
                if (optionsDiv.style.display === 'none' || optionsDiv.style.display === '') {
                    optionsDiv.style.display = 'grid';
                    this.classList.add('active');
                } else {
                    optionsDiv.style.display = 'none';
                    this.classList.remove('active');
                }
            }
        });
    });

    const genreCheckboxes = document.querySelectorAll('input[name="genre[]"]');
    const allGenreCheckbox = genreCheckboxes[0]; 

    if (allGenreCheckbox) {
        allGenreCheckbox.addEventListener('change', function() {
            if (this.checked) {
                genreCheckboxes.forEach((cb, index) => {
                    if (index > 0) cb.checked = false;
                });
            }
        });

        genreCheckboxes.forEach((cb, index) => {
            if (index > 0) {
                cb.addEventListener('change', function() {
                    if (this.checked) {
                        allGenreCheckbox.checked = false;
                    }
                    const anyChecked = Array.from(genreCheckboxes).slice(1).some(c => c.checked);
                    if (!anyChecked) {
                        allGenreCheckbox.checked = true;
                    }
                });
            }
        });
    }

    const statusCheckboxes = document.querySelectorAll('input[name="status[]"]');
    const allStatusCheckbox = statusCheckboxes[0]; 

    if (allStatusCheckbox) {
        allStatusCheckbox.addEventListener('change', function() {
            if (this.checked) {
                statusCheckboxes.forEach((cb, index) => {
                    if (index > 0) cb.checked = false;
                });
            }
        });

        statusCheckboxes.forEach((cb, index) => {
            if (index > 0) {
                cb.addEventListener('change', function() {
                    if (this.checked) {
                        allStatusCheckbox.checked = false;
                    }
                    const anyChecked = Array.from(statusCheckboxes).slice(1).some(c => c.checked);
                    if (!anyChecked) {
                        allStatusCheckbox.checked = true;
                    }
                });
            }
        });
    }

    const ratingCheckboxes = document.querySelectorAll('input[name="rating[]"]');
    const allRatingCheckbox = ratingCheckboxes[0];

    if (allRatingCheckbox) {
        allRatingCheckbox.addEventListener('change', function() {
            if (this.checked) {
                ratingCheckboxes.forEach((cb, index) => {
                    if (index > 0) cb.checked = false;
                });
            }
        });

        ratingCheckboxes.forEach((cb, index) => {
            if (index > 0) {
                cb.addEventListener('change', function() {
                    if (this.checked) {
                        allRatingCheckbox.checked = false;
                    }
                    const anyChecked = Array.from(ratingCheckboxes).slice(1).some(c => c.checked);
                    if (!anyChecked) {
                        allRatingCheckbox.checked = true;
                    }
                });
            }
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            const newestSort = document.querySelector('input[name="sort"][value="newest"]');
            if (newestSort) newestSort.checked = true;

            if (allGenreCheckbox) allGenreCheckbox.checked = true;
            if (allStatusCheckbox) allStatusCheckbox.checked = true;
            if (allRatingCheckbox) allRatingCheckbox.checked = true;
            
            genreCheckboxes.forEach((cb, index) => {
                if (index > 0) cb.checked = false;
            });
            statusCheckboxes.forEach((cb, index) => {
                if (index > 0) cb.checked = false;
            });
            ratingCheckboxes.forEach((cb, index) => {
                if (index > 0) cb.checked = false;
            });
            
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.value = '';
            }
            
            if (searchForm) {
                searchForm.submit();
            }
        });
    }
});
