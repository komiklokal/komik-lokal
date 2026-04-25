function saveAllFields() {
    const sinopsis = document.getElementById('sinopsis-input').value;
    const status = document.getElementById('status-input').value;
    const genre = document.getElementById('genre').value;

    console.log("Data yang dikirim:", { sinopsis, status, genre });

    fetch('editkomik_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            sinopsis: sinopsis,
            status: status,
            genre: genre
        })
    })
    .then(response => {
        if (!response.ok) {
            console.error("HTTP Error:", response.status);
            throw new Error("Network response was not ok");
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showPopupNotification();
        } else {
            alert('Gagal memperbarui data.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat memperbarui data.');
    });
}

function calculateHeightByGenreCount(value) {
    if (!value.trim()) return 48;
    
    const genres = value.split(',').map(g => g.trim()).filter(g => g.length > 0);
    const genreCount = genres.length;
    
    const averageGenreLength = genres.reduce((sum, genre) => sum + genre.length, 0) / genreCount || 0;
    const genresPerLine = Math.max(1, Math.floor(60 / (averageGenreLength + 2)));
    const estimatedLines = Math.ceil(genreCount / genresPerLine);
    
    const baseHeight = 48;
    const lineHeight = 24;
    const calculatedHeight = baseHeight + ((estimatedLines - 1) * lineHeight);
    
    return Math.min(128, Math.max(48, calculatedHeight));
}

document.addEventListener('DOMContentLoaded', function() {
    const genreInput = document.getElementById('genre');
    const genreDropdown = document.getElementById('genreDropdown');

    if (genreInput && genreDropdown) {
        let selectedGenres = genreInput.value.split(',').map(g => g.trim()).filter(g => g !== '');
        genreInput.value = selectedGenres.join(', ');

        function updateDropdown(items) {
            genreDropdown.innerHTML = '';
            if (items.length === 0) {
                genreDropdown.style.display = 'none';
                return;
            }

            items.forEach(item => {
                const div = document.createElement('div');
                div.textContent = item;
                div.className = 'autocomplete-item';
                div.onclick = function() {
                    if (!selectedGenres.includes(item)) {
                        selectedGenres.push(item);
                        genreInput.value = selectedGenres.join(', ');
                    }
                    genreDropdown.style.display = 'none';
                    const newHeight = calculateHeightByGenreCount(genreInput.value);
                    genreInput.style.height = newHeight + 'px';
                };
                genreDropdown.appendChild(div);
            });

            genreDropdown.style.display = 'block';
        }

        let debounceTimeout;
        genreInput.addEventListener('input', function(e) {
            const currentInput = e.target.value;
            const lastGenre = currentInput.split(',').pop().trim();

            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                if (lastGenre) {
                    fetch('get_genres.php?term=' + encodeURIComponent(lastGenre))
                        .then(response => response.json())
                        .then(data => {
                            const filteredData = data.filter(genre => !selectedGenres.includes(genre));
                            updateDropdown(filteredData);
                        })
                        .catch(error => console.error('Error:', error));
                } else {
                    genreDropdown.style.display = 'none';
                }
            }, 300);

            const newHeight = calculateHeightByGenreCount(this.value);
            this.style.height = newHeight + 'px';
        });

        genreInput.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && genreInput.value === '') {
                selectedGenres.pop();
                genreInput.value = selectedGenres.join(', ');
                e.preventDefault();
                const newHeight = calculateHeightByGenreCount(this.value);
                this.style.height = newHeight + 'px';
            }
        });

        genreInput.addEventListener('paste', function() {
            setTimeout(() => {
                const newHeight = calculateHeightByGenreCount(this.value);
                this.style.height = newHeight + 'px';
            }, 10);
        });

        document.addEventListener('click', function(e) {
            if (!genreInput.contains(e.target) && !genreDropdown.contains(e.target)) {
                genreDropdown.style.display = 'none';
            }
        });
    }
});

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

document.addEventListener('DOMContentLoaded', function() {
    const genreInput = document.getElementById('genre');
    const genreListInput = document.getElementById('genre_list');
    const currentGenres = document.querySelector('.current-genres');
    
    function updateGenreList() {
        const tags = currentGenres.querySelectorAll('.genre-tag');
        const genres = Array.from(tags).map(tag => tag.getAttribute('data-genre'));
        genreListInput.value = genres.join(', ');
    }
    
    function addGenreTag(genre) {
        const genreTag = document.createElement('span');
        genreTag.className = 'genre-tag';
        genreTag.textContent = genre;
        genreTag.setAttribute('data-genre', genre);
        
        genreTag.addEventListener('click', function() {
            genreTag.remove();
            updateGenreList();
        });
        
        currentGenres.appendChild(genreTag);
        genreInput.value = '';
        updateGenreList();
    }
    
    genreInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const genre = this.value.trim();
            if (genre) {
                addGenreTag(genre);
            }
        }
    });
    
    document.querySelectorAll('.genre-tag').forEach(tag => {
        tag.addEventListener('click', function() {
            tag.remove();
            updateGenreList();
        });
    });
});
