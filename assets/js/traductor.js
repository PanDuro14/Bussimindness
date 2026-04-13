// ============================================================
//  assets/js/traductor.js  —  Traductor ES ↔ EN con botón visible
// ============================================================

const Traductor = (() => {

    const _originales = new Map();
    let _idiomaActual = 'es';
    let _traduciendo  = false;

    const EXCLUIR_TAGS = new Set([
        'SCRIPT','STYLE','META','LINK','INPUT','TEXTAREA',
        'SELECT','OPTION','NOSCRIPT','CODE','PRE','BUTTON'
    ]);

    // ── Detectar ruta base del proyecto ───────────────────
    function getBase() {
        // Detecta si está en localhost/Bussimindness o en Railway
        const host = window.location.hostname;
        if (host === 'localhost' || host === '127.0.0.1') {
            return '/Bussimindness';
        }
        return ''; // En Railway la raíz es /
    }

    // ── Nodos de texto visibles ────────────────────────────
    function obtenerNodosTexto(raiz = document.body) {
        const nodos = [];
        const walker = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                const padre = node.parentElement;
                if (!padre) return NodeFilter.FILTER_REJECT;
                if (EXCLUIR_TAGS.has(padre.tagName)) return NodeFilter.FILTER_REJECT;
                if (padre.closest('script,style,[data-no-traducir],#traductor-widget')) return NodeFilter.FILTER_REJECT;
                if (node.textContent.trim().length < 2) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });
        let n;
        while ((n = walker.nextNode())) nodos.push(n);
        return nodos;
    }

    // ── Llamar a la API de traducción ─────────────────────
    async function traducirTexto(texto, idioma) {
        try {
            const res = await fetch(getBase() + '/api/traducir.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ texto, idioma })
            });
            if (!res.ok) return texto;
            const data = await res.json();
            return data.traduccion || texto;
        } catch {
            return texto;
        }
    }

    // ── Traducir en lotes ─────────────────────────────────
    async function traducirEnLotes(items, idioma, lote = 6) {
        const resultados = [];
        for (let i = 0; i < items.length; i += lote) {
            const promesas = items.slice(i, i + lote).map(item =>
                traducirTexto(item.texto, idioma).then(t => ({ ...item, traduccion: t }))
            );
            resultados.push(...await Promise.all(promesas));
        }
        return resultados;
    }

    // ── Actualizar estado visual del botón ─────────────────
    function actualizarBoton(idioma, cargando = false) {
        const btn     = document.getElementById('traductor-widget');
        const bandera = document.getElementById('tr-bandera');
        const label   = document.getElementById('tr-label');
        const spin    = document.getElementById('tr-spin');
        if (!btn) return;

        if (cargando) {
            if (spin)    spin.style.display   = 'inline-block';
            if (label)   label.textContent    = '...';
            if (bandera) bandera.textContent   = '⟳';
            btn.style.opacity = '0.7';
            btn.style.cursor  = 'not-allowed';
        } else {
            if (spin)    spin.style.display   = 'none';
            btn.style.opacity = '1';
            btn.style.cursor  = 'pointer';
            if (idioma === 'en') {
                if (bandera) bandera.textContent = '🇺🇸';
                if (label)   label.textContent   = 'EN';
                btn.title = 'Cambiar a Español';
            } else {
                if (bandera) bandera.textContent = '🇲🇽';
                if (label)   label.textContent   = 'ES';
                btn.title = 'Switch to English';
            }
        }
    }

    // ── Traducir / revertir toda la página ─────────────────
    async function traducirPagina(idioma) {
        if (_traduciendo) return;
        if (idioma === _idiomaActual) return;
        _traduciendo = true;
        actualizarBoton(idioma, true);

        if (idioma === 'es') {
            // Revertir a español
            _originales.forEach((original, ref) => {
                if (ref && ref.nodeType === Node.TEXT_NODE) {
                    ref.textContent = original;
                }
            });
            _originales.clear();
            _idiomaActual = 'es';
            _traduciendo  = false;
            actualizarBoton('es');
            guardarPreferencia('es');
            return;
        }

        // Obtener nodos a traducir
        const nodos = obtenerNodosTexto();
        const items = nodos
            .filter(n => !_originales.has(n))
            .map(n => ({ nodo: n, texto: n.textContent.trim() }))
            .filter(i => i.texto.length > 1);

        // Guardar originales
        items.forEach(({ nodo, texto }) => _originales.set(nodo, texto));

        // Traducir
        const traducidos = await traducirEnLotes(items, idioma);
        traducidos.forEach(({ nodo, traduccion }) => {
            if (nodo && traduccion) nodo.textContent = traduccion;
        });

        _idiomaActual = idioma;
        _traduciendo  = false;
        actualizarBoton(idioma);
        guardarPreferencia(idioma);
    }

    // ── Guardar preferencia ────────────────────────────────
    function guardarPreferencia(idioma) {
        localStorage.setItem('buss_idioma', idioma);
        // Si hay sesión activa, guardar en BD
        if (document.body.dataset.userId) {
            fetch(getBase() + '/api/usuario/set_idioma.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ idioma })
            }).catch(() => {});
        }
    }

    // ── Crear widget flotante ──────────────────────────────
    function crearWidget() {
        if (document.getElementById('traductor-widget')) return;

        const widget = document.createElement('div');
        widget.id    = 'traductor-widget';
        widget.setAttribute('data-no-traducir', '');
        widget.title = 'Switch to English';
        widget.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            border: 2px solid #1a3a5c;
            border-radius: 50px;
            padding: 8px 18px 8px 12px;
            cursor: pointer;
            font-family: sans-serif;
            font-size: 0.9em;
            font-weight: 700;
            color: #1a3a5c;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
            transition: all 0.2s ease;
            user-select: none;
        `;

        widget.innerHTML = `
            <span id="tr-bandera" style="font-size:1.3em">🇲🇽</span>
            <span id="tr-label">ES</span>
            <span id="tr-spin" style="display:none;animation:tr-spin 0.8s linear infinite;font-size:1.1em">⟳</span>
        `;

        // Hover
        widget.addEventListener('mouseenter', () => {
            if (!_traduciendo) widget.style.background = '#1a3a5c', widget.style.color = '#fff';
        });
        widget.addEventListener('mouseleave', () => {
            if (!_traduciendo) widget.style.background = '#fff', widget.style.color = '#1a3a5c';
        });

        // Click
        widget.addEventListener('click', () => {
            if (_traduciendo) return;
            const nuevo = _idiomaActual === 'es' ? 'en' : 'es';
            traducirPagina(nuevo);
        });

        document.body.appendChild(widget);

        // CSS animación
        if (!document.getElementById('tr-styles')) {
            const s = document.createElement('style');
            s.id = 'tr-styles';
            s.textContent = `@keyframes tr-spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }`;
            document.head.appendChild(s);
        }
    }

    // ── Inicializar ────────────────────────────────────────
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }

        crearWidget();

        // Detectar idioma guardado: data-idioma del <body> (sesión PHP)
        // o localStorage (visitante sin sesión)
        const idiomaGuardado =
            document.body.dataset.idioma ||
            localStorage.getItem('buss_idioma') ||
            'es';

        if (idiomaGuardado === 'en') {
            traducirPagina('en');
        }
    }

    return { init, traducirPagina, idioma: () => _idiomaActual };
})();

Traductor.init();