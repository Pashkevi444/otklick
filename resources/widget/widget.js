/* Отклик — встраиваемый чат-виджет. Подключается одним <script> на сайт бизнеса.
 * Читает data-otklik-tenant / data-otklik-channel со своего тега, общается с
 * публичным API по токену сессии (без cookie, cross-origin). Без зависимостей. */
(function () {
    'use strict';

    var script =
        document.currentScript ||
        (function () {
            var list = document.querySelectorAll('script[data-otklik-channel]');
            return list[list.length - 1];
        })();
    if (!script) return;

    var tenant = script.getAttribute('data-otklik-tenant');
    var channel = script.getAttribute('data-otklik-channel');
    if (!tenant || !channel) return;

    var api;
    try {
        api = new URL(script.src).origin + '/widget/v1/' + tenant + '/' + channel;
    } catch (e) {
        return;
    }

    var token = null;
    var starting = false;
    var sending = false;

    // Память диалога: токен сессии + история сообщений переживают перезагрузку
    // страницы (ключ привязан к конкретному виджету tenant/channel).
    var SKEY = 'otklik:' + tenant + ':' + channel;
    var history = [];
    (function () {
        try {
            var saved = JSON.parse(localStorage.getItem(SKEY) || '{}');
            if (saved && typeof saved.token === 'string') token = saved.token;
            if (saved && Array.isArray(saved.msgs)) history = saved.msgs;
        } catch (e) {
            /* приватный режим / переполнение — работаем без памяти */
        }
    })();
    function persist() {
        try {
            localStorage.setItem(SKEY, JSON.stringify({ token: token, msgs: history.slice(-60) }));
        } catch (e) {
            /* запись недоступна — не падаем */
        }
    }
    function resetSession() {
        token = null;
        history = [];
        try {
            localStorage.removeItem(SKEY);
        } catch (e) {
            /* игнор */
        }
    }

    var css = [
        '.otk-launcher{position:fixed;right:22px;bottom:22px;width:60px;height:60px;border:0;border-radius:50%;cursor:pointer;z-index:2147483000;',
        'background:linear-gradient(135deg,#2E74B5,#1F4E79);box-shadow:0 10px 28px rgba(31,78,121,.4);display:flex;align-items:center;justify-content:center;',
        'transition:transform .25s cubic-bezier(.2,.8,.2,1),box-shadow .25s ease}',
        '.otk-launcher:hover{transform:translateY(-2px) scale(1.05);box-shadow:0 14px 34px rgba(31,78,121,.5)}',
        '.otk-launcher svg{width:28px;height:28px;fill:#fff;transition:transform .3s ease}',
        '.otk-launcher.otk-on svg{transform:rotate(90deg) scale(.9)}',
        '.otk-panel{position:fixed;right:22px;bottom:94px;width:374px;max-width:calc(100vw - 32px);height:560px;max-height:calc(100vh - 130px);z-index:2147483000;',
        'background:#fff;border-radius:20px;box-shadow:0 24px 70px rgba(16,42,73,.34);display:flex;flex-direction:column;overflow:hidden;',
        'font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;opacity:0;transform:translateY(22px) scale(.96);pointer-events:none;',
        'transition:opacity .3s ease,transform .32s cubic-bezier(.2,.85,.25,1)}',
        '.otk-panel.otk-open{opacity:1;transform:none;pointer-events:auto}',
        '.otk-head{background:linear-gradient(135deg,#2E74B5,#1F4E79);color:#fff;padding:15px 16px;display:flex;align-items:center;gap:11px}',
        '.otk-ava{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex:0 0 auto}',
        '.otk-ava svg{width:21px;height:21px;fill:#fff}',
        '.otk-ttl{font-weight:700;font-size:15px;line-height:1.1}',
        '.otk-sub{font-size:12px;opacity:.85;display:flex;align-items:center;gap:5px;margin-top:2px}',
        '.otk-dot{width:7px;height:7px;border-radius:50%;background:#7CF6C3;box-shadow:0 0 0 0 rgba(124,246,195,.7);animation:otk-pulse 2s infinite}',
        '.otk-x{margin-left:auto;background:rgba(255,255,255,.15);border:0;color:#fff;width:30px;height:30px;border-radius:9px;cursor:pointer;font-size:15px;transition:background .2s}',
        '.otk-x:hover{background:rgba(255,255,255,.28)}',
        '.otk-body{flex:1;overflow-y:auto;padding:16px 14px;background:#f3f6fb;display:flex;flex-direction:column;gap:9px}',
        '.otk-body::-webkit-scrollbar{width:7px}.otk-body::-webkit-scrollbar-thumb{background:#cdd8e6;border-radius:9px}',
        '.otk-msg{max-width:82%;padding:10px 13px;border-radius:16px;font-size:14px;line-height:1.42;white-space:pre-wrap;word-wrap:break-word;animation:otk-in .32s cubic-bezier(.2,.85,.25,1) both}',
        '.otk-bot{align-self:flex-start;background:#fff;color:#1f2a3a;border-bottom-left-radius:5px;box-shadow:0 2px 8px rgba(16,42,73,.07)}',
        '.otk-me{align-self:flex-end;background:linear-gradient(135deg,#2E74B5,#255f96);color:#fff;border-bottom-right-radius:5px}',
        '.otk-typing{align-self:flex-start;background:#fff;border-radius:16px;border-bottom-left-radius:5px;padding:13px 15px;display:flex;gap:5px;box-shadow:0 2px 8px rgba(16,42,73,.07)}',
        '.otk-typing span{width:7px;height:7px;border-radius:50%;background:#9fb2c9;animation:otk-bounce 1.2s infinite}',
        '.otk-typing span:nth-child(2){animation-delay:.2s}.otk-typing span:nth-child(3){animation-delay:.4s}',
        '.otk-foot{display:flex;gap:9px;padding:11px;border-top:1px solid #eef2f7;background:#fff;align-items:flex-end}',
        '.otk-in{flex:1;border:1.5px solid #dde5ef;border-radius:13px;padding:10px 13px;font-size:14px;outline:none;resize:none;max-height:96px;font-family:inherit;transition:border-color .2s}',
        '.otk-in:focus{border-color:#2E74B5}',
        '.otk-send{flex:0 0 auto;width:42px;height:42px;border:0;border-radius:13px;background:linear-gradient(135deg,#2E74B5,#1F4E79);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .2s,opacity .2s}',
        '.otk-send:hover{transform:translateY(-1px)}.otk-send:disabled{opacity:.45;cursor:default;transform:none}',
        '.otk-send svg{width:19px;height:19px;fill:#fff}',
        '.otk-pow{text-align:center;font-size:11px;color:#9aa7b8;padding:6px 0 9px;background:#fff}',
        '.otk-img{margin-top:8px;max-width:220px;max-height:220px;width:auto;border-radius:13px;cursor:zoom-in;display:block;object-fit:cover;box-shadow:0 2px 10px rgba(16,42,73,.14);transition:transform .2s ease}',
        '.otk-img:hover{transform:scale(1.03)}',
        '.otk-lightbox{position:fixed;inset:0;z-index:2147483600;background:rgba(8,15,30,0);display:flex;align-items:center;justify-content:center;padding:24px;cursor:zoom-out;transition:background .28s ease}',
        '.otk-lightbox.otk-lb-on{background:rgba(8,15,30,.88)}',
        '.otk-lightbox img{max-width:92vw;max-height:88vh;border-radius:14px;box-shadow:0 28px 80px rgba(0,0,0,.6);transform:scale(.9);opacity:0;transition:transform .32s cubic-bezier(.2,.85,.25,1),opacity .25s ease}',
        '.otk-lightbox.otk-lb-on img{transform:none;opacity:1}',
        '.otk-lightbox .otk-lb-x{position:absolute;top:18px;right:20px;width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,.15);color:#fff;font-size:18px;cursor:pointer}',
        '@keyframes otk-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}',
        '@keyframes otk-bounce{0%,60%,100%{transform:translateY(0);opacity:.5}30%{transform:translateY(-5px);opacity:1}}',
        '@keyframes otk-pulse{0%{box-shadow:0 0 0 0 rgba(124,246,195,.6)}70%{box-shadow:0 0 0 7px rgba(124,246,195,0)}100%{box-shadow:0 0 0 0 rgba(124,246,195,0)}}',
        '@media (prefers-reduced-motion:reduce){.otk-launcher,.otk-panel,.otk-msg,.otk-dot,.otk-typing span{transition:none;animation:none}}',
    ].join('');
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    var chatIcon =
        '<svg viewBox="0 0 24 24"><path d="M12 3C6.9 3 2.8 6.3 2.8 10.5c0 2 .95 3.8 2.5 5.2-.1.95-.5 2-.95 2.7-.2.3 0 .7.4.65 1.4-.2 2.6-.7 3.5-1.3.85.2 1.75.3 2.7.3 5.1 0 9.2-3.3 9.2-7.5S17.1 3 12 3z"/></svg>';
    var closeMark = '✕';
    var sendIcon = '<svg viewBox="0 0 24 24"><path d="M3.4 20.4l17.45-7.48a1 1 0 0 0 0-1.84L3.4 3.6a1 1 0 0 0-1.4.92V9.5c0 .5.37.92.87.98l9.13 1.52-9.13 1.52a1 1 0 0 0-.87.98v4.98a1 1 0 0 0 1.4.92z"/></svg>';

    var launcher = document.createElement('button');
    launcher.className = 'otk-launcher';
    launcher.setAttribute('aria-label', 'Открыть чат');
    launcher.innerHTML = chatIcon;

    var panel = document.createElement('div');
    panel.className = 'otk-panel';
    panel.innerHTML =
        '<div class="otk-head">' +
        '<div class="otk-ava">' + chatIcon + '</div>' +
        '<div><div class="otk-ttl">Отклик</div><div class="otk-sub"><span class="otk-dot"></span>Обычно отвечает сразу</div></div>' +
        '<button class="otk-x" aria-label="Закрыть">' + closeMark + '</button>' +
        '</div>' +
        '<div class="otk-body"></div>' +
        '<div class="otk-foot">' +
        '<textarea class="otk-in" rows="1" placeholder="Напишите сообщение…"></textarea>' +
        '<button class="otk-send" aria-label="Отправить">' + sendIcon + '</button>' +
        '</div>' +
        '<div class="otk-pow">Работает на Отклик</div>';

    document.body.appendChild(launcher);
    document.body.appendChild(panel);

    var body = panel.querySelector('.otk-body');
    var input = panel.querySelector('.otk-in');
    var sendBtn = panel.querySelector('.otk-send');
    var closeBtn = panel.querySelector('.otk-x');
    var typingEl = null;

    var IMG_RE = /(https?:\/\/[^\s<>"']+\.(?:png|jpe?g|gif|webp)(?:\?[^\s<>"']*)?)/gi;

    function openLightbox(url) {
        var ov = document.createElement('div');
        ov.className = 'otk-lightbox';
        var img = document.createElement('img');
        img.src = url;
        var x = document.createElement('button');
        x.className = 'otk-lb-x';
        x.setAttribute('aria-label', 'Закрыть');
        x.textContent = '✕';
        ov.appendChild(img);
        ov.appendChild(x);
        var close = function () {
            ov.classList.remove('otk-lb-on');
            setTimeout(function () { ov.remove(); }, 300);
        };
        ov.addEventListener('click', close);
        document.body.appendChild(ov);
        requestAnimationFrame(function () { ov.classList.add('otk-lb-on'); });
    }

    function addMsg(text, who, noSave) {
        if (!noSave) {
            history.push({ t: String(text), w: who });
            persist();
        }

        var el = document.createElement('div');
        el.className = 'otk-msg ' + (who === 'me' ? 'otk-me' : 'otk-bot');

        // Вытащим ссылки на картинки и покажем их как фото (клик — увеличить).
        var images = [];
        var clean = String(text).replace(IMG_RE, function (u) { images.push(u); return ''; });
        clean = clean.replace(/\n{3,}/g, '\n\n').replace(/[ \t]+\n/g, '\n').trim();

        if (clean) {
            var t = document.createElement('div');
            t.textContent = clean;
            el.appendChild(t);
        }
        images.forEach(function (url) {
            var img = document.createElement('img');
            img.className = 'otk-img';
            img.src = url;
            img.alt = 'Фото';
            img.loading = 'lazy';
            img.addEventListener('click', function () { openLightbox(url); });
            img.addEventListener('load', function () { body.scrollTop = body.scrollHeight; });
            el.appendChild(img);
        });

        body.appendChild(el);
        body.scrollTop = body.scrollHeight;
        return el;
    }

    // Не спамим одинаковыми сообщениями бота (защита от «бесконечного цикла»).
    // Это техническое уведомление — в историю диалога не сохраняем.
    function addBotOnce(text) {
        var last = body.querySelector('.otk-bot:last-of-type');
        if (last && last.textContent === text) return;
        addMsg(text, 'bot', true);
    }

    function showTyping() {
        if (typingEl) return;
        typingEl = document.createElement('div');
        typingEl.className = 'otk-typing';
        typingEl.innerHTML = '<span></span><span></span><span></span>';
        body.appendChild(typingEl);
        body.scrollTop = body.scrollHeight;
    }
    function hideTyping() {
        if (typingEl) { typingEl.remove(); typingEl = null; }
    }

    function post(path, payload) {
        return fetch(api + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(payload || {}),
        }).then(function (r) {
            return r.ok ? r.json() : Promise.reject(r);
        });
    }

    function startSession() {
        if (token || starting) return Promise.resolve();
        starting = true;
        showTyping();
        return post('/session', {})
            .then(function (data) {
                token = data.token;
                persist();
                hideTyping();
                if (data.greeting) addMsg(data.greeting, 'bot');
            })
            .catch(function () {
                hideTyping();
                addBotOnce('Чат сейчас недоступен. Попробуйте чуть позже или свяжитесь с нами другим способом.');
            })
            .finally(function () { starting = false; });
    }

    function send() {
        var text = (input.value || '').trim();
        if (!text || sending) return;
        if (!token) {
            // Одна попытка подключиться, потом отправка. Без рекурсивного спама.
            if (starting) return;
            startSession().then(function () { if (token) reallySend(text); });
            return;
        }
        reallySend(text);
    }

    function reallySend(text) {
        addMsg(text, 'me');
        input.value = '';
        input.style.height = 'auto';
        sending = true;
        sendBtn.disabled = true;
        showTyping();
        post('/message', { token: token, text: text })
            .then(function (data) {
                hideTyping();
                addMsg(data.reply, 'bot');
            })
            .catch(function (err) {
                hideTyping();
                // Сессия устарела (например, виджет переподключали) — сбросим
                // токен, при следующей отправке начнётся новая.
                if (err && err.status === 403) resetSession();
                addBotOnce('Сообщение не доставлено. Попробуйте ещё раз чуть позже.');
            })
            .finally(function () {
                sending = false;
                sendBtn.disabled = false;
                input.focus();
            });
    }

    function toggle(open) {
        var willOpen = open === undefined ? !panel.classList.contains('otk-open') : open;
        panel.classList.toggle('otk-open', willOpen);
        launcher.classList.toggle('otk-on', willOpen);
        launcher.setAttribute('aria-label', willOpen ? 'Свернуть чат' : 'Открыть чат');
        if (willOpen) {
            startSession();
            setTimeout(function () { input.focus(); }, 300);
        }
    }

    // Восстанавливаем прошлую переписку (если страницу перезагрузили).
    if (history.length) {
        history.forEach(function (m) { addMsg(m.t, m.w, true); });
    }

    launcher.addEventListener('click', function () { toggle(); });
    closeBtn.addEventListener('click', function () { toggle(false); });
    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });
    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 96) + 'px';
    });
})();
