const closePublicNav = (header) => {
    header.querySelector('[data-public-nav-menu]')?.classList.add('hidden');
    header.querySelector('[data-public-nav-backdrop]')?.classList.add('hidden');
    header.querySelector('[data-public-nav-open-icon]')?.classList.add('hidden');
    header.querySelector('[data-public-nav-closed-icon]')?.classList.remove('hidden');
    header.querySelector('[data-public-nav-toggle]')?.setAttribute('aria-expanded', 'false');
};

const openPublicNav = (header) => {
    header.querySelector('[data-public-nav-menu]')?.classList.remove('hidden');
    header.querySelector('[data-public-nav-backdrop]')?.classList.remove('hidden');
    header.querySelector('[data-public-nav-open-icon]')?.classList.remove('hidden');
    header.querySelector('[data-public-nav-closed-icon]')?.classList.add('hidden');
    header.querySelector('[data-public-nav-toggle]')?.setAttribute('aria-expanded', 'true');
};

document.querySelectorAll('[data-public-header]').forEach((header) => {
    const toggle = header.querySelector('[data-public-nav-toggle]');

    toggle?.addEventListener('click', () => {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';
        isOpen ? closePublicNav(header) : openPublicNav(header);
    });

    header.querySelector('[data-public-nav-backdrop]')?.addEventListener('click', () => closePublicNav(header));

    header.querySelectorAll('[data-public-nav-menu] a').forEach((link) => {
        link.addEventListener('click', () => closePublicNav(header));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePublicNav(header);
        }
    });
});
