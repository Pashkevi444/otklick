/* Отклик — встраиваемый чат-виджет. Подключается одним <script> на сайт бизнеса.
 * Читает data-otklik-tenant / data-otklik-channel со своего тега, общается с
 * публичным API по токену сессии (без cookie, cross-origin). */
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

    var css =
        '.otk-btn{position:fixed;right:20px;bottom:20px;width:60px;height:60px;border-radius:50%;border:0;' +
        'background:#2E74B5;color:#fff;font-size:26px;cursor:pointer;box-shadow:0 8px 24px rgba(31,78,121,.35);z-index:2147483000}' +
        '.otk-btn:hover{background:#255f96}' +
        '.otk-panel{position:fixed;right:20px;bottom:90px;width:360px;max-width:calc(100vw - 40px);height:520px;max-height:calc(100vh - 120px);' +
        'background:#fff;border-radius:16px;box-shadow:0 18px 60px rgba(31,78,121,.28);display:none;flex-direction:column;overflow:hidden;z-index:2147483000;' +
        'font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif}' +
        '.otk-panel.open{display:flex}' +
        '.otk-head{background:#1F4E79;color:#fff;padding:14px 16px;font-weight:600}' +
        '.otk-body{flex:1;overflow-y:auto;padding:14px;background:#f6f8fb}' +
        '.otk-msg{max-width:80%;padding:9px 12px;border-radius:14px;margin-bottom:8px;font-size:14px;line-height:1.4;white-space:pre-wrap;word-wrap:break-word}' +
        '.otk-bot{background:#fff;border:1px solid #e6ebf2;color:#222;border-bottom-left-radius:4px}' +
        '.otk-me{background:#2E74B5;color:#fff;margin-left:auto;border-bottom-right-radius:4px}' +
        '.otk-foot{display:flex;gap:8px;padding:10px;border-top:1px solid #eef1f5;background:#fff}' +
        '.otk-input{flex:1;border:1px solid #d7dee8;border-radius:10px;padding:9px 11px;font-size:14px;outline:none}' +
        '.otk-input:focus{border-color:#2E74B5}' +
        '.otk-send{border:0;background:#2E74B5;color:#fff;border-radius:10px;padding:0 14px;cursor:pointer;font-size:14px}' +
        '.otk-send:disabled{opacity:.5;cursor:default}';
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    var btn = document.createElement('button');
    btn.className = 'otk-btn';
    btn.setAttribute('aria-label', 'Открыть чат');
    btn.textContent = '💬';

    var panel = document.createElement('div');
    panel.className = 'otk-panel';
    panel.innerHTML =
        '<div class="otk-head">Чат с администратором</div>' +
        '<div class="otk-body"></div>' +
        '<div class="otk-foot">' +
        '<input class="otk-input" type="text" placeholder="Напишите сообщение…" />' +
        '<button class="otk-send">→</button>' +
        '</div>';

    document.body.appendChild(btn);
    document.body.appendChild(panel);

    var body = panel.querySelector('.otk-body');
    var input = panel.querySelector('.otk-input');
    var sendBtn = panel.querySelector('.otk-send');

    function addMsg(text, who) {
        var el = document.createElement('div');
        el.className = 'otk-msg ' + (who === 'me' ? 'otk-me' : 'otk-bot');
        el.textContent = text;
        body.appendChild(el);
        body.scrollTop = body.scrollHeight;
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
        return post('/session', {})
            .then(function (data) {
                token = data.token;
                if (data.greeting) addMsg(data.greeting, 'bot');
            })
            .catch(function () {
                addMsg('Не удалось подключиться к чату. Попробуйте позже.', 'bot');
            })
            .finally(function () {
                starting = false;
            });
    }

    function send() {
        var text = (input.value || '').trim();
        if (!text || sending) return;
        if (!token) {
            startSession().then(send);
            return;
        }
        addMsg(text, 'me');
        input.value = '';
        sending = true;
        sendBtn.disabled = true;
        post('/message', { token: token, text: text })
            .then(function (data) {
                addMsg(data.reply, 'bot');
            })
            .catch(function () {
                addMsg('Сообщение не доставлено. Попробуйте ещё раз.', 'bot');
            })
            .finally(function () {
                sending = false;
                sendBtn.disabled = false;
                input.focus();
            });
    }

    btn.addEventListener('click', function () {
        panel.classList.toggle('open');
        if (panel.classList.contains('open')) {
            startSession();
            input.focus();
        }
    });
    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') send();
    });
})();
