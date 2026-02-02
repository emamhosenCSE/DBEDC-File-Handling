/**
 * Search Autocomplete
 * Handles global search functionality with autocomplete
 */

const SearchAutocomplete = {
    currentInput: null,
    suggestions: [],
    selectedIndex: -1,
    minQueryLength: 2,
    debounceTimer: null,

    init(inputElement) {
        this.currentInput = inputElement;
        inputElement.setAttribute('autocomplete', 'off');
        inputElement.setAttribute('aria-expanded', 'false');
        inputElement.setAttribute('aria-haspopup', 'listbox');
        inputElement.setAttribute('role', 'combobox');

        // Create suggestions container
        const container = document.createElement('div');
        container.className = 'search-suggestions';
        container.setAttribute('role', 'listbox');
        container.style.display = 'none';
        inputElement.parentNode.style.position = 'relative';
        inputElement.parentNode.appendChild(container);

        // Event listeners
        inputElement.addEventListener('input', (e) => this.onInput(e));
        inputElement.addEventListener('keydown', (e) => this.onKeydown(e));
        inputElement.addEventListener('blur', () => setTimeout(() => this.hide(), 150));
        inputElement.addEventListener('focus', () => {
            if (this.suggestions.length > 0) {
                this.show();
            }
        });
    },

    async onInput(e) {
        const query = e.target.value.trim();

        // Clear previous timer
        clearTimeout(this.debounceTimer);

        if (query.length < this.minQueryLength) {
            this.hide();
            return;
        }

        // Debounce search
        this.debounceTimer = setTimeout(async () => {
            try {
                const response = await API.get(`api/search.php?q=${encodeURIComponent(query)}&limit=8`);
                this.suggestions = response.results || [];
                this.render();
                this.show();
            } catch (error) {
                console.error('Search error:', error);
                this.suggestions = [];
                this.hide();
            }
        }, 300);
    },

    onKeydown(e) {
        if (this.suggestions.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.suggestions.length - 1);
                this.updateSelection();
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                this.updateSelection();
                break;
            case 'Enter':
                e.preventDefault();
                if (this.selectedIndex >= 0) {
                    this.selectItem(this.selectedIndex);
                }
                break;
            case 'Escape':
                this.hide();
                this.currentInput.blur();
                break;
        }
    },

    updateSelection() {
        const items = document.querySelectorAll('.suggestion-item');
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === this.selectedIndex);
        });
    },

    selectItem(index) {
        const item = this.suggestions[index];
        if (!item) return;

        // Navigate to the item
        switch (item.type) {
            case 'letter':
                loadView('letters', { id: item.id });
                break;
            case 'task':
                loadView('tasks', { id: item.id });
                break;
            default:
                console.log('Unknown item type:', item.type);
        }

        showToast(`Navigating to ${item.type}: ${item.title}`, 'info');
        this.hide();
    },

    render() {
        const container = this.currentInput.parentNode.querySelector('.search-suggestions');
        if (!container) return;

        if (this.suggestions.length === 0) {
            container.innerHTML = '<div class="suggestion-item no-results">No results found</div>';
            return;
        }

        container.innerHTML = this.suggestions.map((item, index) => `
            <div class="suggestion-item"
                 role="option"
                 data-index="${index}"
                 data-type="${item.type}"
                 data-id="${item.id}"
                 onclick="SearchAutocomplete.selectItem(${index})">
                <div class="suggestion-icon">
                    ${this.getIconForType(item.type)}
                </div>
                <div class="suggestion-content">
                    <div class="suggestion-title">${this.highlightMatch(item.title, this.currentInput.value)}</div>
                    <div class="suggestion-meta">${item.type} • ${item.meta || ''}</div>
                </div>
            </div>
        `).join('');

        this.selectedIndex = -1;
    },

    highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    },

    getIconForType(type) {
        const icons = {
            letter: '📄',
            task: '📋',
            user: '👤',
            department: '🏢'
        };
        return icons[type] || '📄';
    },

    show() {
        const container = this.currentInput.parentNode.querySelector('.search-suggestions');
        if (container && this.suggestions.length > 0) {
            container.style.display = 'block';
            this.currentInput.setAttribute('aria-expanded', 'true');
        }
    },

    hide() {
        const container = this.currentInput.parentNode.querySelector('.search-suggestions');
        if (container) {
            container.style.display = 'none';
            this.currentInput.setAttribute('aria-expanded', 'false');
        }
        this.selectedIndex = -1;
        this.suggestions = [];
    }
};