(function () {
    const $ = (id) => document.getElementById(id);
    const readConfig = (id) => {
        const el = $(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent || "{}"); } catch (e) { return null; }
    };

    function init() {
        const cfg = readConfig("laptro-support-config");
        const els = {
            launcher: $("laptro-chatbot-launcher"),
            panel: $("laptro-chatbot-panel"),
            close: $("laptro-chatbot-close"),
            box: $("laptro-chatbot-messages"),
            input: $("laptro-chatbot-input"),
            send: $("laptro-chatbot-send"),
            productHelp: $("laptro-chatbot-product-help"),
            orderHelp: $("laptro-chatbot-order-help"),
            support: $("laptro-chatbot-support")
        };
        if (!cfg || Object.values(els).some(function (v) { return !v; })) return;

        let closed = String(cfg.conversationStatus || "") === "closed";
        let lastId = 0;

        const addMsg = (m) => {
            const who = m.sender_type === "customer" ? "user" : "bot";
            const row = document.createElement("div");
            row.className = "laptro-chatbot-message laptro-chatbot-message-" + who;
            const bubble = document.createElement("p");
            bubble.className = "laptro-chatbot-bubble";
            bubble.textContent = m.message_text || "";
            row.appendChild(bubble);
            els.box.appendChild(row);
            els.box.scrollTop = els.box.scrollHeight;
        };
        const notice = (t) => addMsg({ sender_type: "system", message_text: t });
        const syncInput = () => {
            els.input.disabled = false;
            els.send.disabled = false;
            els.productHelp.disabled = false;
            els.orderHelp.disabled = false;
            els.support.disabled = false;
            els.input.placeholder = closed ? "Ask a new question to reopen chat..." : "Ask about a product, stock, or your order...";
        };

        const fetchMessages = () => {
            if (!cfg.apiMessagesUrl) return;
            const url = cfg.apiMessagesUrl + (lastId ? "&since_id=" + encodeURIComponent(String(lastId)) : "");
            fetch(url, { credentials: "same-origin" })
                .then((r) => r.json())
                .then((d) => {
                    const msgs = Array.isArray(d.messages) ? d.messages : [];
                    msgs.forEach((m) => { addMsg(m); lastId = Math.max(lastId, Number(m.id || 0)); });
                    closed = d.status === "closed";
                    syncInput();
                })
                .catch(() => { /* ignore network blips */ });
        };

        const send = () => {
            const text = (els.input.value || "").trim();
            if (!text) return;
            if (!cfg.apiSendUrl) return notice("This chat is unavailable right now.");

            const body = new URLSearchParams({ conversation_id: String(cfg.conversationId || 0), message: text }).toString();
            fetch(cfg.apiSendUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body
            })
                .then((r) => r.json())
                .then((d) => {
                    closed = false;
                    const msgs = Array.isArray(d.messages) ? d.messages : [];
                    msgs.forEach((m) => { addMsg(m); lastId = Math.max(lastId, Number(m.id || 0)); });
                    els.input.value = "";
                    syncInput();
                    fetchMessages();
                })
                .catch(() => notice("Message could not be sent. Please try again."));
        };

        const requestSupport = () => {
            if (!cfg.apiSupportUrl) return;
            const body = new URLSearchParams({ reason: "Customer asked to speak to support." }).toString();
            fetch(cfg.apiSupportUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body
            })
                .then((r) => r.json())
                .then((d) => {
                    closed = false;
                    if (d.message) {
                        els.box.innerHTML = "";
                        lastId = 0;
                        notice(d.message);
                    }
                    syncInput();
                    fetchMessages();
                })
                .catch(() => notice("Support could not be contacted right now. Please try again."));
        };

        const open = () => { els.panel.classList.add("is-open"); els.launcher.classList.add("is-hidden"); els.panel.setAttribute("aria-hidden", "false"); els.input.focus(); };
        const hide = () => { els.panel.classList.remove("is-open"); els.launcher.classList.remove("is-hidden"); els.panel.setAttribute("aria-hidden", "true"); };

        (Array.isArray(cfg.messages) ? cfg.messages : []).forEach((m) => {
            addMsg(m);
            lastId = Math.max(lastId, Number(m.id || 0));
        });
        els.launcher.addEventListener("click", open);
        els.close.addEventListener("click", hide);
        els.send.addEventListener("click", send);
        els.input.addEventListener("keydown", (e) => e.key === "Enter" && send());
        els.productHelp.addEventListener("click", () => {
            els.input.value = "Can you help me find a laptop and tell me what is in stock?";
            send();
        });
        els.orderHelp.addEventListener("click", () => {
            els.input.value = "Can you check my latest order status?";
            send();
        });
        els.support.addEventListener("click", requestSupport);

        syncInput();
        fetchMessages();
        window.setInterval(fetchMessages, 4000);
    }

    document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", init) : init();
})();
