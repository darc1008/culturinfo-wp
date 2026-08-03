(function () {
  'use strict';

  const menuButton = document.querySelector('.menu-toggle');
  const menu = document.querySelector('.primary-menu');
  const searchButton = document.querySelector('.search-toggle');
  const searchPanel = document.querySelector('.search-panel');

  if (menuButton && menu) {
    menuButton.addEventListener('click', function () {
      const open = menu.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', String(open));
      document.body.classList.toggle('menu-open', open);
    });

    menu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        menu.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-open');
      });
    });
  }

  if (searchButton && searchPanel) {
    searchButton.addEventListener('click', function () {
      const open = searchPanel.classList.toggle('is-open');
      searchButton.setAttribute('aria-expanded', String(open));
      if (open) {
        const field = searchPanel.querySelector('.search-field');
        if (field) field.focus();
      }
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (menu) menu.classList.remove('is-open');
    if (menuButton) menuButton.setAttribute('aria-expanded', 'false');
    if (searchPanel) searchPanel.classList.remove('is-open');
    if (searchButton) searchButton.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('menu-open');
  });
}());
