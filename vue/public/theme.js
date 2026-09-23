// Dark before anything is drawn, as useAppTheme decides it. A file rather than inline in
// index.html, because the Content-Security-Policy allows no inline script.
(function () {
    var mode = null;
    try { mode = localStorage.getItem('kso.theme.mode'); } catch (e) {}
    var dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    if (dark) document.documentElement.classList.add('kso-dark');
})();
