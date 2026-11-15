const dueDateInput = document.getElementById('due_date');
const taskForm = document.querySelector('.task-form');
const mobileNav = document.querySelector('.side-nav');
const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
const themeToggle = document.getElementById('theme-toggle');
const themeMeta = document.querySelector('meta[name="theme-color"]');

function updateTheme(theme, persist) {
    document.documentElement.setAttribute('data-theme', theme);

    if (persist) {
        window.localStorage.setItem('taskflow-theme', theme);
    }

    if (themeToggle) {
        const nextTheme = theme === 'dark' ? 'terang' : 'gelap';
        themeToggle.setAttribute('aria-label', `Aktifkan tema ${nextTheme}`);
        themeToggle.querySelector('.theme-toggle-label').textContent =
            `Mode ${nextTheme}`;
    }

    if (themeMeta) {
        themeMeta.setAttribute('content', theme === 'dark' ? '#13121d' : '#f6f5fb');
    }
}

if (dueDateInput) {
    const today = new Date();
    const localDate = new Date(today.getTime() - today.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);
    dueDateInput.min = localDate;
}

if (taskForm) {
    taskForm.addEventListener('submit', () => {
        const submitButton = taskForm.querySelector('.submit-button');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Menyimpan...';
        }
    });
}

if (mobileNav && mobileNavToggle) {
    mobileNavToggle.addEventListener('click', () => {
        const isExpanded = mobileNav.classList.toggle('is-expanded');
        mobileNavToggle.setAttribute('aria-expanded', String(isExpanded));
        mobileNavToggle.setAttribute(
            'aria-label',
            isExpanded ? 'Ciutkan navigasi' : 'Tampilkan navigasi'
        );
    });
}

if (themeToggle) {
    updateTheme(document.documentElement.getAttribute('data-theme') || 'light', false);
    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        updateTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
    });
}

document.querySelectorAll('.delete-task-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
