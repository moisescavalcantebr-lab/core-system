document.addEventListener('DOMContentLoaded', function () {

    if (!window.dashboardCards) return;

    const MAX_CARDS = 10;

    const activeContainer = document.getElementById('activeCards');
    const availableContainer = document.getElementById('availableCards');

    if (!activeContainer) return;

    let active = JSON.parse(localStorage.getItem('dashboardActive'));

    if (!active || active.length === 0) {
        active = Object.keys(window.dashboardCards).slice(0, 5);
        localStorage.setItem('dashboardActive', JSON.stringify(active));
    }

    function render() {

        activeContainer.innerHTML = '';
        if (availableContainer) availableContainer.innerHTML = '';

        Object.keys(window.dashboardCards).forEach(key => {

            const data = window.dashboardCards[key];

            if (active.includes(key)) {

                const card = document.createElement('div');
                card.className = 'c-dashboard-card';
                card.innerHTML = `
                    <h4>${data.title}</h4>
                    <div class="c-metric">${data.value}</div>
                `;

                card.addEventListener('click', () => removeCard(key));
                activeContainer.appendChild(card);

            } else if (availableContainer) {

                const item = document.createElement('div');
                item.className = 'c-available-item';
                item.textContent = data.title;
                item.addEventListener('click', () => addCard(key));
                availableContainer.appendChild(item);
            }

        });
    }

    function addCard(key) {
        if (active.length >= MAX_CARDS) return;
        active.push(key);
        save();
    }

    function removeCard(key) {
        active = active.filter(k => k !== key);
        save();
    }

    function save() {
        localStorage.setItem('dashboardActive', JSON.stringify(active));
        render();
    }

    render();
});