// Barra superior de herramientas en «Modo pestaña» (Ajustes → Apariencia del proyecto).
// Cada sección es una pestaña y solo se muestran los iconos de la sección elegida.
(function () {
    const navs = document.querySelectorAll('.breadcrumb-pestanas');
    if (!navs.length) return;

    navs.forEach(nav => {
        const tabs = Array.from(nav.querySelectorAll('.bpest-tab'));
        const paneles = Array.from(nav.querySelectorAll('.bpest-panel'));
        if (tabs.length < 2 || tabs.length !== paneles.length) return;

        const mostrar = i => {
            tabs.forEach((t, j) => {
                const activa = j === i;
                t.classList.toggle('active', activa);
                t.setAttribute('aria-selected', activa ? 'true' : 'false');
                t.tabIndex = activa ? 0 : -1;
            });
            paneles.forEach((p, j) => { p.hidden = j !== i; });
        };

        nav.addEventListener('click', e => {
            const tab = e.target.closest('.bpest-tab');
            if (tab) mostrar(tabs.indexOf(tab));
        });

        nav.addEventListener('keydown', e => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            const actual = tabs.findIndex(t => t.getAttribute('aria-selected') === 'true');
            const paso = e.key === 'ArrowRight' ? 1 : -1;
            const siguiente = (Math.max(actual, 0) + paso + tabs.length) % tabs.length;
            e.preventDefault();
            tabs[siguiente].focus();
            mostrar(siguiente);
        });
    });
})();
