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
    // Курсор лайв-поллинга: id последнего показанного сообщения сервера. Переживает
    // перезагрузку, иначе после reload поллинг переотдал бы всю историю заново.
    var lastId = '';
    (function () {
        try {
            var saved = JSON.parse(localStorage.getItem(SKEY) || '{}');
            if (saved && typeof saved.token === 'string') token = saved.token;
            if (saved && Array.isArray(saved.msgs)) history = saved.msgs;
            if (saved && typeof saved.lastId === 'string') lastId = saved.lastId;
        } catch (e) {
            /* приватный режим / переполнение — работаем без памяти */
        }
    })();
    function persist() {
        try {
            localStorage.setItem(SKEY, JSON.stringify({ token: token, msgs: history.slice(-60), lastId: lastId }));
        } catch (e) {
            /* запись недоступна — не падаем */
        }
    }
    function resetSession() {
        token = null;
        history = [];
        lastId = '';
        try {
            localStorage.removeItem(SKEY);
        } catch (e) {
            /* игнор */
        }
    }

    // Фирменный цвет виджета задаётся через CSS-переменные --otk-a (акцент) и
    // --otk-b (тёмный край градиента). По умолчанию — бренд «Отклик»; реальный
    // цвет бизнеса подтягивается с /config и переопределяет переменные.
    var css = [
        ':root{--otk-a:#2E74B5;--otk-b:#1F4E79}',
        '.otk-launcher{position:fixed;right:22px;bottom:22px;width:62px;height:62px;border:0;border-radius:50%;cursor:pointer;z-index:2147483000;',
        'background:linear-gradient(135deg,var(--otk-a),var(--otk-b));box-shadow:0 12px 30px rgba(16,42,73,.42),inset 0 1px 1px rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;',
        'transition:transform .28s cubic-bezier(.2,.85,.25,1),box-shadow .28s ease}',
        '.otk-launcher::before{content:"";position:absolute;inset:-6px;border-radius:50%;border:2px solid var(--otk-a);opacity:.5;animation:otk-ring 2.6s ease-out infinite;pointer-events:none}',
        '.otk-launcher:hover{transform:translateY(-3px) scale(1.06);box-shadow:0 18px 40px rgba(16,42,73,.5),inset 0 1px 1px rgba(255,255,255,.4)}',
        '.otk-launcher svg{width:29px;height:29px;fill:#fff;transition:transform .35s cubic-bezier(.2,.85,.25,1)}',
        '.otk-launcher.otk-on::before{display:none}',
        '.otk-launcher.otk-on svg{transform:rotate(90deg) scale(.88)}',
        '.otk-panel{position:fixed;right:22px;bottom:98px;width:380px;max-width:calc(100vw - 32px);height:566px;max-height:calc(100vh - 130px);z-index:2147483000;',
        'background:rgba(255,255,255,.96);border:1px solid rgba(255,255,255,.7);border-radius:22px;box-shadow:0 28px 80px rgba(16,42,73,.32);display:flex;flex-direction:column;overflow:hidden;',
        'font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;opacity:0;transform:translateY(24px) scale(.95);pointer-events:none;transform-origin:bottom right;',
        'transition:opacity .32s ease,transform .42s cubic-bezier(.2,.9,.25,1)}',
        '.otk-panel.otk-open{opacity:1;transform:none;pointer-events:auto}',
        // Стеклянная шапка: градиент бренда + светящийся блик сверху.
        '.otk-head{position:relative;background:linear-gradient(135deg,var(--otk-a),var(--otk-b));color:#fff;padding:16px 16px;display:flex;align-items:center;gap:11px;overflow:hidden}',
        '.otk-head::after{content:"";position:absolute;top:-60%;left:-20%;width:80%;height:200%;background:radial-gradient(closest-side,rgba(255,255,255,.28),transparent);pointer-events:none}',
        '.otk-ava{position:relative;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.2);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;flex:0 0 auto;box-shadow:inset 0 0 0 1px rgba(255,255,255,.25)}',
        '.otk-ava svg{width:22px;height:22px;fill:#fff}',
        '.otk-ttl{position:relative;font-weight:700;font-size:15.5px;line-height:1.1;letter-spacing:.2px}',
        '.otk-sub{position:relative;font-size:12px;opacity:.92;display:flex;align-items:center;gap:5px;margin-top:3px}',
        '.otk-dot{width:7px;height:7px;border-radius:50%;background:#7CF6C3;box-shadow:0 0 0 0 rgba(124,246,195,.7);animation:otk-pulse 2s infinite}',
        '.otk-x{position:relative;margin-left:auto;background:rgba(255,255,255,.16);border:0;color:#fff;width:31px;height:31px;border-radius:10px;cursor:pointer;font-size:15px;transition:background .2s,transform .2s}',
        '.otk-x:hover{background:rgba(255,255,255,.3);transform:rotate(90deg)}',
        '.otk-body{flex:1;overflow-y:auto;padding:16px 14px;background:linear-gradient(180deg,#f4f7fc,#eef3fa);display:flex;flex-direction:column;gap:9px}',
        '.otk-body::-webkit-scrollbar{width:7px}.otk-body::-webkit-scrollbar-thumb{background:#cdd8e6;border-radius:9px}',
        '.otk-msg{max-width:82%;padding:10px 13px;border-radius:17px;font-size:14px;line-height:1.42;white-space:pre-wrap;word-wrap:break-word;animation:otk-in .34s cubic-bezier(.2,.85,.25,1) both}',
        '.otk-bot{align-self:flex-start;background:#fff;color:#1f2a3a;border-bottom-left-radius:5px;box-shadow:0 3px 12px rgba(16,42,73,.08);border:1px solid rgba(16,42,73,.04)}',
        '.otk-me{align-self:flex-end;background:linear-gradient(135deg,var(--otk-a),var(--otk-b));color:#fff;border-bottom-right-radius:5px;box-shadow:0 4px 14px rgba(16,42,73,.18)}',
        '.otk-typing{align-self:flex-start;background:#fff;border-radius:17px;border-bottom-left-radius:5px;padding:13px 15px;display:flex;gap:5px;box-shadow:0 3px 12px rgba(16,42,73,.08);animation:otk-in .3s ease both}',
        '.otk-typing span{width:7px;height:7px;border-radius:50%;background:#9fb2c9;animation:otk-bounce 1.2s infinite}',
        '.otk-typing span:nth-child(2){animation-delay:.2s}.otk-typing span:nth-child(3){animation-delay:.4s}',
        '.otk-foot{display:flex;gap:9px;padding:11px;border-top:1px solid rgba(16,42,73,.06);background:rgba(255,255,255,.85);backdrop-filter:blur(6px);align-items:flex-end}',
        '.otk-in{flex:1;border:1.5px solid #dde5ef;border-radius:14px;padding:10px 13px;font-size:14px;outline:none;resize:none;max-height:96px;font-family:inherit;transition:border-color .2s,box-shadow .2s}',
        '.otk-in:focus{border-color:var(--otk-a);box-shadow:0 0 0 3px rgba(46,116,181,.12)}',
        '.otk-send{flex:0 0 auto;width:43px;height:43px;border:0;border-radius:14px;background:linear-gradient(135deg,var(--otk-a),var(--otk-b));color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .2s,opacity .2s,box-shadow .2s;box-shadow:0 4px 12px rgba(16,42,73,.2)}',
        '.otk-send:hover{transform:translateY(-1px) scale(1.04)}.otk-send:disabled{opacity:.45;cursor:default;transform:none;box-shadow:none}',
        '.otk-send svg{width:19px;height:19px;fill:#fff}',
        '.otk-pow{text-align:center;font-size:11px;color:#9aa7b8;padding:6px 0 9px;background:rgba(255,255,255,.85)}',
        '.otk-pow a{color:var(--otk-a);text-decoration:none;font-weight:600}.otk-pow a:hover{text-decoration:underline}',
        '.otk-oper{display:none;font-size:11px;color:var(--otk-b);background:rgba(46,116,181,.1);text-align:center;padding:5px 10px;border-top:1px solid rgba(46,116,181,.18)}',
        '.otk-oper.otk-on{display:block}',
        '.otk-img{margin-top:8px;max-width:220px;max-height:220px;width:auto;border-radius:13px;cursor:zoom-in;display:block;object-fit:cover;box-shadow:0 2px 10px rgba(16,42,73,.14);transition:transform .2s ease}',
        '.otk-img:hover{transform:scale(1.03)}',
        // Время под сообщением (мелкое, полупрозрачное; у «своих» — белёсое).
        '.otk-time{font-size:10.5px;opacity:.6;margin-top:4px;text-align:right;font-variant-numeric:tabular-nums}',
        '.otk-me .otk-time{color:rgba(255,255,255,.85)}',
        // Разделитель дней: «Сегодня» / «Вчера» / «5 мар» по центру ленты.
        '.otk-day{align-self:center;font-size:11px;color:#7b8aa0;background:rgba(255,255,255,.7);border:1px solid rgba(16,42,73,.06);border-radius:11px;padding:3px 11px;margin:4px 0;box-shadow:0 1px 4px rgba(16,42,73,.05)}',
        // Кнопки-инструменты ввода (эмодзи, скрепка) слева от поля.
        '.otk-tool{flex:0 0 auto;width:34px;height:34px;border:0;border-radius:11px;background:rgba(46,116,181,.08);color:var(--otk-a);cursor:pointer;font-size:18px;line-height:1;display:flex;align-items:center;justify-content:center;transition:background .2s,transform .2s}',
        '.otk-tool:hover{background:rgba(46,116,181,.16);transform:translateY(-1px)}',
        '.otk-tool svg{width:18px;height:18px;fill:var(--otk-a)}',
        // Панель эмодзи над полем ввода.
        '.otk-emoji{display:none;flex-wrap:wrap;gap:3px;padding:8px 10px;border-top:1px solid rgba(16,42,73,.06);background:rgba(255,255,255,.95);max-height:128px;overflow-y:auto}',
        '.otk-emoji.otk-on{display:flex}',
        '.otk-emoji button{border:0;background:none;cursor:pointer;font-size:21px;line-height:1;padding:4px;border-radius:8px;transition:background .15s,transform .15s}',
        '.otk-emoji button:hover{background:rgba(46,116,181,.1);transform:scale(1.18)}',
        '.otk-lightbox{position:fixed;inset:0;z-index:2147483600;background:rgba(8,15,30,0);display:flex;align-items:center;justify-content:center;padding:24px;cursor:zoom-out;transition:background .28s ease}',
        '.otk-lightbox.otk-lb-on{background:rgba(8,15,30,.88)}',
        '.otk-lightbox img{max-width:92vw;max-height:88vh;border-radius:14px;box-shadow:0 28px 80px rgba(0,0,0,.6);transform:scale(.9);opacity:0;transition:transform .32s cubic-bezier(.2,.85,.25,1),opacity .25s ease}',
        '.otk-lightbox.otk-lb-on img{transform:none;opacity:1}',
        '.otk-lightbox .otk-lb-x{position:absolute;top:18px;right:20px;width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,.15);color:#fff;font-size:18px;cursor:pointer}',
        '@keyframes otk-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}',
        '@keyframes otk-bounce{0%,60%,100%{transform:translateY(0);opacity:.5}30%{transform:translateY(-5px);opacity:1}}',
        '@keyframes otk-pulse{0%{box-shadow:0 0 0 0 rgba(124,246,195,.6)}70%{box-shadow:0 0 0 7px rgba(124,246,195,0)}100%{box-shadow:0 0 0 0 rgba(124,246,195,0)}}',
        '@keyframes otk-ring{0%{transform:scale(1);opacity:.5}70%{transform:scale(1.35);opacity:0}100%{opacity:0}}',
        '@media (prefers-reduced-motion:reduce){.otk-launcher,.otk-panel,.otk-msg,.otk-dot,.otk-typing span,.otk-launcher::before{transition:none;animation:none}}',
    ].join('');
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    // Перекраска под фирменный цвет бизнеса. Принимает один HEX (#RRGGBB),
    // тёмный край градиента вычисляем затемнением — чтобы бизнесу хватило одного
    // выбора цвета в кабинете.
    function darken(hex, f) {
        var m = /^#?([0-9a-f]{6})$/i.exec(String(hex || ''));
        if (!m) return null;
        var n = parseInt(m[1], 16);
        var r = Math.round(((n >> 16) & 255) * f);
        var g = Math.round(((n >> 8) & 255) * f);
        var b = Math.round((n & 255) * f);
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }
    function applyTheme(color) {
        var dark = darken(color, 0.74);
        if (!dark) return;
        var root = document.documentElement;
        root.style.setProperty('--otk-a', color);
        root.style.setProperty('--otk-b', dark);
    }

    var chatIcon =
        '<svg viewBox="0 0 24 24"><path d="M12 3C6.9 3 2.8 6.3 2.8 10.5c0 2 .95 3.8 2.5 5.2-.1.95-.5 2-.95 2.7-.2.3 0 .7.4.65 1.4-.2 2.6-.7 3.5-1.3.85.2 1.75.3 2.7.3 5.1 0 9.2-3.3 9.2-7.5S17.1 3 12 3z"/></svg>';
    var closeMark = '✕';
    var sendIcon = '<svg viewBox="0 0 24 24"><path d="M3.4 20.4l17.45-7.48a1 1 0 0 0 0-1.84L3.4 3.6a1 1 0 0 0-1.4.92V9.5c0 .5.37.92.87.98l9.13 1.52-9.13 1.52a1 1 0 0 0-.87.98v4.98a1 1 0 0 0 1.4.92z"/></svg>';
    var clipIcon = '<svg viewBox="0 0 24 24"><path d="M16.5 6.5l-7.8 7.8a2 2 0 1 0 2.83 2.83l8.49-8.49a4 4 0 1 0-5.66-5.66l-8.49 8.49a6 6 0 0 0 8.49 8.49l7.07-7.07-1.41-1.41-7.07 7.07a4 4 0 0 1-5.66-5.66l8.49-8.49a2 2 0 1 1 2.83 2.83l-8.49 8.49a.5.5 0 0 1-.71-.71l7.8-7.8z"/></svg>';

    var launcher = document.createElement('button');
    launcher.className = 'otk-launcher';
    launcher.setAttribute('aria-label', 'Открыть чат');
    launcher.innerHTML = chatIcon;

    var panel = document.createElement('div');
    panel.className = 'otk-panel';
    panel.innerHTML =
        '<div class="otk-head">' +
        '<div class="otk-ava">' + chatIcon + '</div>' +
        '<div><div class="otk-ttl">Отклик</div><div class="otk-sub"><span class="otk-dot"></span>Администратор в сети</div></div>' +
        '<button class="otk-x" aria-label="Закрыть">' + closeMark + '</button>' +
        '</div>' +
        '<div class="otk-body"></div>' +
        '<div class="otk-emoji"></div>' +
        '<div class="otk-foot">' +
        '<button class="otk-tool otk-emoji-btn" type="button" aria-label="Эмодзи">😊</button>' +
        '<button class="otk-tool otk-attach-btn" type="button" aria-label="Прикрепить фото">' + clipIcon + '</button>' +
        '<input class="otk-file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" hidden />' +
        '<textarea class="otk-in" rows="1" placeholder="Напишите сообщение…"></textarea>' +
        '<button class="otk-send" aria-label="Отправить">' + sendIcon + '</button>' +
        '</div>' +
        '<div class="otk-oper">👤 Оператор на связи — отвечает лично</div>' +
        '<div class="otk-pow">Работает на <a href="https://otcl1ck.ru" target="_blank" rel="noopener">Отклик</a></div>';

    document.body.appendChild(launcher);
    document.body.appendChild(panel);

    var body = panel.querySelector('.otk-body');
    var input = panel.querySelector('.otk-in');
    var sendBtn = panel.querySelector('.otk-send');
    var closeBtn = panel.querySelector('.otk-x');
    var operEl = panel.querySelector('.otk-oper');
    var emojiPanel = panel.querySelector('.otk-emoji');
    var emojiBtn = panel.querySelector('.otk-emoji-btn');
    var attachBtn = panel.querySelector('.otk-attach-btn');
    var fileInput = panel.querySelector('.otk-file');
    var typingEl = null;
    // Дата последнего отрисованного сообщения — чтобы вставлять разделитель «день».
    var lastDayKey = '';

    // Человекочитаемая дата для разделителя: «Сегодня» / «Вчера» / «5 мар 2025».
    function dayLabel(d) {
        var today = new Date();
        var yest = new Date();
        yest.setDate(today.getDate() - 1);
        var k = d.toDateString();
        if (k === today.toDateString()) return 'Сегодня';
        if (k === yest.toDateString()) return 'Вчера';
        var opts = { day: 'numeric', month: 'short' };
        if (d.getFullYear() !== today.getFullYear()) opts.year = 'numeric';
        return d.toLocaleDateString('ru-RU', opts);
    }

    // Вставляет разделитель дня перед сообщением, если день сменился.
    function maybeDaySeparator(d) {
        var key = d.toDateString();
        if (key === lastDayKey) return;
        lastDayKey = key;
        var sep = document.createElement('div');
        sep.className = 'otk-day';
        sep.textContent = dayLabel(d);
        body.appendChild(sep);
    }

    function setOperator(active) {
        operEl.classList.toggle('otk-on', !!active);
    }

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

    function addMsg(text, who, noSave, extraImages, ts) {
        // Метка времени: серверная (если пришла) либо момент показа. Для лайв-чата
        // клиентского времени достаточно, а в истории оно переживает перезагрузку.
        var when = ts || new Date().toISOString();
        if (!noSave) {
            history.push({ t: String(text), w: who, ts: when, imgs: extraImages || [] });
            persist();
        }

        var date = new Date(when);
        if (isNaN(date.getTime())) date = new Date();
        maybeDaySeparator(date);

        var el = document.createElement('div');
        el.className = 'otk-msg ' + (who === 'me' ? 'otk-me' : 'otk-bot');

        // Вытащим ссылки на картинки и покажем их как фото (клик — увеличить).
        var images = [];
        var clean = String(text).replace(IMG_RE, function (u) { images.push(u); return ''; });
        clean = clean.replace(/\n{3,}/g, '\n\n').replace(/[ \t]+\n/g, '\n').trim();
        // Картинки могут прийти отдельным полем (сервер уже вынес их из текста).
        if (extraImages && extraImages.length) {
            extraImages.forEach(function (u) { if (images.indexOf(u) < 0) images.push(u); });
        }

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

        // Время сообщения — мелкой строкой под текстом/фото.
        var tm = document.createElement('div');
        tm.className = 'otk-time';
        tm.textContent = date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        el.appendChild(tm);

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

    // Кликабельные подсказки под сообщением бота (даты/время/услуги мастера
    // записи) — как кнопки в мессенджерах; нажатие отправляет подпись.
    function clearChips() {
        var old = body.querySelector('.otk-chips');
        if (old) old.remove();
    }
    function renderChips(options) {
        clearChips();
        if (!options || !options.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'otk-chips';
        wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;margin:2px 0 8px';
        options.forEach(function (label) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = label;
            b.style.cssText = 'border:1px solid var(--otk-a);background:#fff;color:var(--otk-a);border-radius:14px;padding:5px 12px;font-size:13px;cursor:pointer;line-height:1.2';
            b.addEventListener('click', function () { clearChips(); reallySend(label); });
            wrap.appendChild(b);
        });
        body.appendChild(wrap);
        body.scrollTop = body.scrollHeight;
    }

    function reallySend(text) {
        clearChips();
        addMsg(text, 'me');
        input.value = '';
        input.style.height = 'auto';
        sending = true;
        sendBtn.disabled = true;
        showTyping();
        post('/message', { token: token, text: text })
            .then(function (data) {
                hideTyping();
                // Курсор поллинга двигаем за уже показанный ответ, чтобы не задвоить.
                if (data.lastId) { lastId = data.lastId; persist(); }
                // При перехвате оператором бот молчит (reply пустой) — ничего не рисуем,
                // ответ оператора придёт лайв-поллингом.
                if (data.reply || (data.images && data.images.length)) {
                    addMsg(data.reply, 'bot', false, data.images, data.createdAt);
                }
                renderChips(data.options);
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

    // Лайв-поллинг: тянем ответы оператора и статус «оператор на связи» раз в ~3с.
    // Пропускаем во время отправки/старта (чтобы не задвоить ответ) и на скрытой
    // вкладке (экономим запросы).
    var primed = false;
    var seenIds = {};
    function poll() {
        if (!token || sending || starting || document.hidden) return;
        post('/poll', { token: token, after: lastId })
            .then(function (data) {
                if (data.messages && data.messages.length) {
                    if (!primed && !lastId && history.length) {
                        // Восстановили старую сессию без курсора (диалог создан до
                        // появления поллинга): бэклог уже показан из history — не
                        // дублируем его, только ставим курсор на последнее.
                        data.messages.forEach(function (m) { seenIds[m.id] = 1; });
                        lastId = data.messages[data.messages.length - 1].id;
                    } else {
                        data.messages.forEach(function (m) {
                            lastId = m.id;
                            if (seenIds[m.id]) return; // страховка от дублей
                            seenIds[m.id] = 1;
                            addMsg(m.text, 'bot', false, m.images, m.createdAt);
                        });
                    }
                    persist();
                }
                primed = true;
                setOperator(data.operatorActive);
            })
            .catch(function () { /* сеть моргнула — попробуем на следующем тике */ });
    }
    setInterval(poll, 3000);

    // Фирменный цвет бизнеса (если задан в кабинете) — красим кнопку и шапку
    // ещё до открытия чата. Сбой/таймаут — остаёмся на брендовом цвете «Отклик».
    fetch(api + '/config', { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { if (data && data.color) applyTheme(data.color); })
        .catch(function () { /* нет конфига — дефолтная тема */ });

    // Восстанавливаем прошлую переписку (если страницу перезагрузили) — вместе с
    // временем и картинками каждого сообщения.
    if (history.length) {
        history.forEach(function (m) { addMsg(m.t, m.w, true, m.imgs || [], m.ts); });
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

    // Эмодзи-пикер: вставляет смайл в позицию курсора.
    function insertAtCursor(el, text) {
        var s = el.selectionStart;
        var e = el.selectionEnd;
        if (typeof s === 'number' && typeof e === 'number') {
            el.value = el.value.slice(0, s) + text + el.value.slice(e);
            el.selectionStart = el.selectionEnd = s + text.length;
        } else {
            el.value += text;
        }
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 96) + 'px';
    }
    var EMOJIS = ['😊', '🙂', '😉', '😄', '😅', '😂', '🥰', '😍', '👍', '👌', '🙏', '🤝', '💪', '🔥', '✨', '🎉', '❤️', '💜', '✅', '📅', '💈', '💇', '💅', '💡', '📍', '📞', '🤔', '🙌'];
    EMOJIS.forEach(function (e) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = e;
        b.addEventListener('click', function () { insertAtCursor(input, e); input.focus(); });
        emojiPanel.appendChild(b);
    });
    emojiBtn.addEventListener('click', function () {
        emojiPanel.classList.toggle('otk-on');
        if (emojiPanel.classList.contains('otk-on')) body.scrollTop = body.scrollHeight;
    });

    // Прикрепить фото: бот распознаёт картинку (vision) и отвечает; если не вышло —
    // картинку увидит администратор, диалог уходит к человеку.
    attachBtn.addEventListener('click', function () { if (!sending) fileInput.click(); });
    fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        fileInput.value = '';
        if (file) uploadImage(file);
    });
    function uploadImage(file) {
        if (sending) return;
        if (!file.type || file.type.indexOf('image/') !== 0) return;
        if (file.size > 5 * 1024 * 1024) { addBotOnce('Файл больше 5 МБ — выберите фото поменьше.'); return; }
        emojiPanel.classList.remove('otk-on');
        var go = function () {
            if (!token) return;
            sending = true;
            sendBtn.disabled = true;
            var caption = (input.value || '').trim();
            input.value = '';
            input.style.height = 'auto';
            var fd = new FormData();
            fd.append('token', token);
            fd.append('image', file);
            if (caption) fd.append('caption', caption);
            showTyping();
            fetch(api + '/upload', { method: 'POST', headers: { Accept: 'application/json' }, body: fd })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(function (data) {
                    hideTyping();
                    addMsg(caption, 'me', false, data.images || [], data.createdAt);
                    if (data.lastId) { lastId = data.lastId; persist(); }
                    if (data.reply) addMsg(data.reply, 'bot', false, [], data.replyAt);
                    setOperator(data.operatorActive);
                })
                .catch(function (err) {
                    hideTyping();
                    if (err && err.status === 403) resetSession();
                    addBotOnce('Не удалось отправить фото. Попробуйте ещё раз.');
                })
                .finally(function () { sending = false; sendBtn.disabled = false; });
        };
        if (!token) { startSession().then(function () { if (token) go(); }); } else { go(); }
    }
})();
