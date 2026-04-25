document.addEventListener('DOMContentLoaded', function() {
    const hiddenGenreInput = document.getElementById('genre');
    const currentGenresContainer = document.querySelector('.current-genres');
    const genreInputWrapper = document.querySelector('.genre-input-wrapper');
    const autocompleteDropdown = document.getElementById('genreDropdown');
    
    if (!hiddenGenreInput || !currentGenresContainer || !genreInputWrapper) return;

    hiddenGenreInput.style.display = 'none';

    const visibleInput = document.createElement('input');
    visibleInput.type = 'text';
    visibleInput.className = 'genre-visible-input';
    visibleInput.placeholder = 'Ketik genre dan tekan Enter...';
    visibleInput.autocomplete = 'off';
    
    genreInputWrapper.insertBefore(visibleInput, genreInputWrapper.firstChild);

    let currentGenres = new Set();
    document.querySelectorAll('.genre-tag').forEach(tag => {
        const genreText = tag.textContent.trim().replace('×', '').trim();
        if (genreText) currentGenres.add(genreText);
    });

    let lastValue = '';

    function updateHiddenInput() {
        hiddenGenreInput.value = Array.from(currentGenres).join(', ');
    }

    function addGenreTag(genre) {
        genre = genre.trim();
        if (!genre || currentGenres.has(genre)) return;

        currentGenres.add(genre);

        const tag = document.createElement('span');
        tag.className = 'genre-tag';
        tag.textContent = genre;
        tag.setAttribute('data-genre', genre);
        
        tag.addEventListener('click', function() {
            currentGenres.delete(genre);
            tag.remove();
            updateHiddenInput();
        });

        currentGenresContainer.appendChild(tag);
        updateHiddenInput();
    }

    async function fetchGenreSuggestions(term) {
        if (!term) {
            hideAutocomplete();
            return;
        }

        try {
            const response = await fetch(`get_genres.php?term=${encodeURIComponent(term)}`);
            const genres = await response.json();
            
            if (genres && genres.length > 0) {
                showAutocomplete(genres);
            } else {
                hideAutocomplete();
            }
        } catch (error) {
            console.error('Error fetching genres:', error);
            hideAutocomplete();
        }
    }

    function showAutocomplete(genres) {
        if (!autocompleteDropdown) return;
        
        autocompleteDropdown.innerHTML = '';
        autocompleteDropdown.style.display = 'block';
        
        genres.forEach(genre => {
            if (currentGenres.has(genre)) return;
            
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = genre;
            item.addEventListener('click', function() {
                const currentValue = visibleInput.value.trim();
                
                const lastCommaIndex = currentValue.lastIndexOf(',');
                
                if (lastCommaIndex >= 0) {
                    const beforeLastComma = currentValue.substring(0, lastCommaIndex);
                    visibleInput.value = beforeLastComma + ', ' + genre + ', ';
                } else {
                    visibleInput.value = genre + ', ';
                }
                
                hideAutocomplete();
                visibleInput.focus();
            });
            autocompleteDropdown.appendChild(item);
        });
        
        if (autocompleteDropdown.children.length === 0) {
            hideAutocomplete();
        }
    }

    function hideAutocomplete() {
        if (autocompleteDropdown) {
            autocompleteDropdown.style.display = 'none';
        }
    }

    visibleInput.addEventListener('input', function(e) {
        let currentValue = e.target.value;
        let cursorPos = e.target.selectionStart;
        
        if (currentValue.length > lastValue.length) {
            let addedChar = currentValue[currentValue.length - 1];
            
            if (addedChar === ' ') {
                let beforeSpace = currentValue.slice(0, -1);
                
                if (beforeSpace.trim() && !beforeSpace.endsWith(',')) {
                    e.target.value = beforeSpace + ', ';
                    e.target.setSelectionRange(beforeSpace.length + 2, beforeSpace.length + 2);
                    currentValue = e.target.value;
                }
            }
        }
        
        lastValue = e.target.value;
        
        const lastCommaIndex = currentValue.lastIndexOf(',');
        const currentWord = lastCommaIndex >= 0 
            ? currentValue.substring(lastCommaIndex + 1).trim() 
            : currentValue.trim();
        
        if (currentWord.length > 0) {
            fetchGenreSuggestions(currentWord);
        } else {
            hideAutocomplete();
        }
    });

    visibleInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const value = this.value.trim();
            if (value) {
                const genres = value.split(',').map(g => g.trim()).filter(g => g.length > 0);
                genres.forEach(g => addGenreTag(g));
                this.value = '';
                hideAutocomplete();
            }
        } else if (e.key === 'Escape') {
            hideAutocomplete();
        } else if (e.key === 'Backspace') {
            let value = e.target.value;
            let cursorPos = e.target.selectionStart;
            
            if (cursorPos >= 2 && value.substring(cursorPos - 2, cursorPos) === ', ') {
                e.preventDefault();
                e.target.value = value.substring(0, cursorPos - 2) + value.substring(cursorPos);
                e.target.setSelectionRange(cursorPos - 2, cursorPos - 2);
            }
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target !== visibleInput && e.target !== autocompleteDropdown) {
            hideAutocomplete();
        }
    });

    document.querySelectorAll('.genre-tag').forEach(tag => {
        const genreText = tag.textContent.trim().replace('×', '').trim();
        tag.addEventListener('click', function() {
            currentGenres.delete(genreText);
            tag.remove();
            updateHiddenInput();
        });
    });

    updateHiddenInput();
});
