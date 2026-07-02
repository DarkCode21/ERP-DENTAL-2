document.addEventListener('DOMContentLoaded', function() {

    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const body = document.body;

    const menuItems = document.querySelectorAll('.menu-item');
    const submenuItems = document.querySelectorAll('.submenu-item');
    const hasSubmenuItems = document.querySelectorAll('.menu-item.has-submenu');

    // =============================================
    // PREVENIR SCROLL CHAINING EN EL SIDEBAR (ESCRITORIO)
    // =============================================
    if (sidebar) {
        sidebar.addEventListener('wheel', function(e) {
            // Obtener el elemento scrollable (puede ser el sidebar-menu o simplebar-content-wrapper)
            const scrollElement = sidebar.querySelector('.simplebar-content-wrapper') ||
                sidebar.querySelector('.sidebar-menu');

            if (scrollElement) {
                const scrollTop = scrollElement.scrollTop;
                const scrollHeight = scrollElement.scrollHeight;
                const height = scrollElement.clientHeight;
                const wheelDelta = e.deltaY;
                const isDeltaPositive = wheelDelta > 0;

                // Si está en el límite superior o inferior, prevenir scroll
                if ((isDeltaPositive && scrollTop + height >= scrollHeight) ||
                    (!isDeltaPositive && scrollTop <= 0)) {
                    // Está en el límite, prevenir propagación
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            } else {
                // Si no hay elemento scrollable (no hay scroll), siempre prevenir
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }, { passive: false });
    }

    // =============================================
    // PREVENIR SCROLL CHAINING EN MÓVILES (TOUCH)
    // =============================================
    if (sidebar) {
        let touchStartY = 0;
        let touchStartScrollTop = 0;

        sidebar.addEventListener('touchstart', function(e) {
            touchStartY = e.touches[0].clientY;
            const scrollElement = sidebar.querySelector('.simplebar-content-wrapper') ||
                sidebar.querySelector('.sidebar-menu');
            if (scrollElement) {
                touchStartScrollTop = scrollElement.scrollTop;
            }
        }, { passive: true });

        sidebar.addEventListener('touchmove', function(e) {
            const scrollElement = sidebar.querySelector('.simplebar-content-wrapper') ||
                sidebar.querySelector('.sidebar-menu');

            if (scrollElement) {
                const touchY = e.touches[0].clientY;
                const touchDelta = touchStartY - touchY;
                const scrollTop = scrollElement.scrollTop;
                const scrollHeight = scrollElement.scrollHeight;
                const height = scrollElement.clientHeight;

                const isScrollingDown = touchDelta > 0;
                const isScrollingUp = touchDelta < 0;

                // Prevenir scroll cuando:
                // 1. No hay contenido scrollable
                // 2. Se intenta hacer scroll hacia abajo estando en el final
                // 3. Se intenta hacer scroll hacia arriba estando en el inicio
                const hasNoScroll = scrollHeight <= height;
                const atBottom = scrollTop + height >= scrollHeight - 1;
                const atTop = scrollTop <= 1;

                if (hasNoScroll || (isScrollingDown && atBottom) || (isScrollingUp && atTop)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            } else {
                // Si no hay elemento scrollable, siempre prevenir
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }, { passive: false });
    }

    // =============================================
    // ESTADO INICIAL SEGÚN DISPOSITIVO
    // =============================================
    function setInitialState() {
        if (window.innerWidth <= 768) {
            // MÓVIL: siempre cerrada al cargar
            sidebar.classList.remove('open', 'hidden', 'closed');
            body.classList.remove('sidebar-open');
            mainContent.classList.remove('nopush', 'expanded');
        } else {
            // ESCRITORIO: restaurar desde localStorage o abierta por defecto
            const savedState = localStorage.getItem('richitheme_sidebar_hidden');
            if (savedState === 'true') {
                sidebar.classList.add('closed', 'hidden');
                mainContent.classList.add('nopush', 'expanded');
            } else {
                sidebar.classList.remove('closed', 'hidden');
                mainContent.classList.remove('nopush', 'expanded');
            }
        }
    }
    setInitialState();

    // =============================================
    // RESIZE - Reiniciar estado al cambiar tamaño
    // =============================================
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            setInitialState();
        }, 250);
    });

    // =============================================
    // TOGGLE SIDEBAR
    // =============================================
    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (window.innerWidth <= 768) {
            // MÓVIL: overlay con backdrop
            sidebar.classList.toggle('open');
            body.classList.toggle('sidebar-open');
        } else {
            // ESCRITORIO: push con localStorage
            sidebar.classList.toggle('closed');
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('nopush');
            mainContent.classList.toggle('expanded');

            // Guardar preferencia
            const isHidden = sidebar.classList.contains('hidden');
            localStorage.setItem('richitheme_sidebar_hidden', isHidden ? 'true' : 'false');
        }

    });

    // =============================================
    // CERRAR AL CLIC FUERA (solo móvil)
    // =============================================
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
            const insideSidebar = sidebar.contains(e.target);
            const onToggle = toggleBtn.contains(e.target);

            if (!insideSidebar && !onToggle) {
                sidebar.classList.remove('open');
                body.classList.remove('sidebar-open');
            }
        }
    });

    // =============================================
    // ACTIVE MENU ITEMS (sin submenú)
    // =============================================
    menuItems.forEach(item => {
        if (!item.classList.contains('has-submenu')) {
            item.addEventListener('click', function() {
                menuItems.forEach(i => i.classList.remove('active'));
                submenuItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        }
    });

    // =============================================
    // TOGGLE SUBMENÚ
    // =============================================
    hasSubmenuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const submenuId = 'submenu-' + this.getAttribute('data-submenu');
            const submenu = document.getElementById(submenuId);
            const icon = this.querySelector('.folder-icon');

            // Cerrar otros submenús
            document.querySelectorAll('.menu-item.has-submenu').forEach(otherItem => {
                if (otherItem !== this) {
                    otherItem.classList.remove('open');
                    const otherSub = document.getElementById('submenu-' + otherItem.getAttribute('data-submenu'));
                    if (otherSub) otherSub.classList.remove('open');

                    const otherIcon = otherItem.querySelector('.folder-icon');
                    if (otherIcon) {
                        otherIcon.classList.remove('fa-folder-open', 'fas');
                        otherIcon.classList.add('fa-folder-closed', 'far');
                    }
                }
            });

            // Toggle submenú actual
            if (submenu) {
                const isOpen = submenu.classList.toggle('open');
                this.classList.toggle('open', isOpen);

                // Recalcular SimpleBar después de la animación
                const scrollElement = document.querySelector('[data-simplebar]');
                if (scrollElement && window.SimpleBar) {
                    const instance = SimpleBar.instances.get(scrollElement);
                    if (instance) {
                        setTimeout(() => instance.recalculate(), 400);
                    }
                }

                // Cambiar icono de carpeta
                if (icon) {
                    if (isOpen) {
                        icon.classList.remove('fa-folder-closed', 'far');
                        icon.classList.add('fa-folder-open', 'fas');
                    } else {
                        icon.classList.remove('fa-folder-open', 'fas');
                        icon.classList.add('fa-folder-closed', 'far');
                    }
                }
            }
        });
    });

    // =============================================
    // ACTIVE SUBMENU ITEMS
    // =============================================
    submenuItems.forEach(item => {
        item.addEventListener('click', function() {
            menuItems.forEach(i => i.classList.remove('active'));
            submenuItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // =============================================
    // MANTENER SUBMENÚS ABIERTOS SI TIENEN ITEMS ACTIVOS
    // =============================================
    const activeMenuItems = document.querySelectorAll('.menu-item.active, .submenu-item.active');
    activeMenuItems.forEach(function(activeItem) {
        const parentSubmenu = activeItem.closest('.submenu');
        if (parentSubmenu) {
            parentSubmenu.classList.add('open');

            const parentMenuItem = document.querySelector(`[data-submenu="${parentSubmenu.id.replace('submenu-', '')}"]`);
            if (parentMenuItem) {
                parentMenuItem.classList.add('open');

                const icon = parentMenuItem.querySelector('.folder-icon');
                if (icon) {
                    icon.classList.remove('fa-folder-closed', 'far');
                    icon.classList.add('fa-folder-open', 'fas');
                }
            }
        }
    });


    // =============================================
    // MANTENER SUBMENÚS ANIDADOS ABIERTOS SI TIENEN ITEMS ACTIVOS
    // =============================================
    const activeNestedItems = document.querySelectorAll('.submenu-nested-item.active');
    activeNestedItems.forEach(function(activeItem) {
        // Abrir el submenú anidado que contiene el item activo
        const parentNestedSubmenu = activeItem.closest('.submenu-nested');
        if (parentNestedSubmenu) {
            parentNestedSubmenu.classList.add('open');

            // Marcar como open el enlace padre que controla este submenú anidado
            const parentId = parentNestedSubmenu.id.replace('submenu-', '');
            const parentLink = document.querySelector(`[data-submenu-child="${parentId}"]`);
            if (parentLink) {
                parentLink.classList.add('open');
            }

            // También abrir el submenú principal (segundo nivel)
            const mainSubmenu = parentNestedSubmenu.closest('.submenu');
            if (mainSubmenu) {
                mainSubmenu.classList.add('open');

                // Marcar como open y cambiar icono del item principal
                const mainMenuId = mainSubmenu.id.replace('submenu-', '');
                const mainMenuItem = document.querySelector(`[data-submenu="${mainMenuId}"]`);
                if (mainMenuItem) {
                    mainMenuItem.classList.add('open');

                    const icon = mainMenuItem.querySelector('.folder-icon');
                    if (icon) {
                        icon.classList.remove('fa-folder-closed', 'far');
                        icon.classList.add('fa-folder-open', 'fas');
                    }
                }
            }
        }
    });

    // =============================================
    // AJUSTAR ALTURA DEL SIDEBAR
    // =============================================
    function adjustSidebarHeight() {
        sidebar.style.height = window.innerHeight + 'px';
    }

    window.addEventListener('resize', adjustSidebarHeight);
    adjustSidebarHeight();


    // =============================================
    // MANEJO DE SUBMENÚS ANIDADOS (TERCER NIVEL)
    // =============================================
    const submenuItemsWithChildren = document.querySelectorAll('.submenu-item.has-children');

    submenuItemsWithChildren.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const submenuId = this.getAttribute('data-submenu-child');
            const nestedSubmenu = document.getElementById('submenu-' + submenuId);

            if (nestedSubmenu) {
                const isCurrentlyOpen = nestedSubmenu.classList.contains('open');

                // Cerrar otros submenús anidados del mismo nivel (hermanos)
                const parentSubmenu = this.closest('.submenu');
                if (parentSubmenu) {
                    const siblingNestedSubmenus = parentSubmenu.querySelectorAll('.submenu-nested.open');
                    siblingNestedSubmenus.forEach(sibling => {
                        if (sibling !== nestedSubmenu) {
                            sibling.classList.remove('open');
                            // Encontrar y actualizar el enlace hermano
                            const siblingId = sibling.id.replace('submenu-', '');
                            const siblingLink = parentSubmenu.querySelector(`[data-submenu-child="${siblingId}"]`);
                            if (siblingLink) {
                                siblingLink.classList.remove('open');
                            }
                        }
                    });
                }

                // Toggle el submenú actual
                nestedSubmenu.classList.toggle('open');
                this.classList.toggle('open');

                // Recalcular SimpleBar después de la animación
                const scrollElement = document.querySelector('[data-simplebar]');
                if (scrollElement && window.SimpleBar) {
                    const instance = SimpleBar.instances.get(scrollElement);
                    if (instance) {
                        setTimeout(() => instance.recalculate(), 400);
                    }
                }
            }
        });
    });

    // =============================================
    // ACTIVE STATE PARA ITEMS DE TERCER NIVEL
    // =============================================
    const nestedItems = document.querySelectorAll('.submenu-nested-item');
    nestedItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remover active de todos los items
            menuItems.forEach(i => i.classList.remove('active'));
            submenuItems.forEach(i => i.classList.remove('active'));
            nestedItems.forEach(i => i.classList.remove('active'));

            // Añadir active al item clickeado
            this.classList.add('active');
        });
    });

});