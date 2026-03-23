(function () {
    const $ = (id) => document.getElementById(id);
    const readConfig = () => {
        const el = $("laptro-assistance-config");
        if (!el) return null;
        try { return JSON.parse(el.textContent || "{}"); } catch (e) { return null; }
    };
    const escapeHtml = (v) => (v || "").replace(/[&<>\"']/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[ch]));

    function init() {
        const cfg = readConfig();
        const els = {
            list: $("assistance-conversation-list"),
            header: $("assistance-chat-header"),
            messages: $("assistance-chat-messages"),
            input: $("assistance-chat-input"),
            send: $("assistance-chat-send"),
            close: $("assistance-chat-close-conversation")
        };
        if (!cfg || Object.values(els).some((v) => !v)) return;

        let selectedId = 0;
        let closed = false;
        const conversations = {};
        const lastMessageByConversation = {};
        const seenMessageIdsByConversation = {};

        const title = (row) =>
            row.customer_name && row.customer_email ? `${row.customer_name} (${row.customer_email})`
            : row.customer_email ? row.customer_email
            : row.session_key || `Conversation #${row.id}`;

        const updateInput = () => {
            const disable = !selectedId;
            els.input.disabled = disable;
            els.send.disabled = disable;
            els.close.disabled = disable || closed;
            els.input.placeholder = !selectedId ? "Select a conversation first" : (closed ? "Reply to reopen this conversation..." : "Reply to customer...");
        };

        const appendMessage = (msg) => {
            const conversationKey = String(selectedId || 0);
            const msgId = Number(msg.id || 0);
            if (msgId > 0) {
                if (!seenMessageIdsByConversation[conversationKey]) {
                    seenMessageIdsByConversation[conversationKey] = new Set();
                }
                if (seenMessageIdsByConversation[conversationKey].has(msgId)) {
                    return;
                }
                seenMessageIdsByConversation[conversationKey].add(msgId);
            }

            const row = document.createElement("div");
            const type = String(msg.sender_type || "").toLowerCase();
            const cls = type === "admin" ? "assistance-message-admin"
                : type === "customer" ? "assistance-message-customer"
                : "assistance-message-system";
            row.className = "assistance-message " + cls;
            const p = document.createElement("p");
            p.className = "assistance-message-bubble";
            p.textContent = msg.message_text || "";
            row.appendChild(p);
            els.messages.appendChild(row);
            els.messages.scrollTop = els.messages.scrollHeight;
        };
        const appendSystemNotice = (text) => appendMessage({ sender_type: "system", message_text: text });

        const renderList = (rows) => {
            els.list.innerHTML = "";
            rows.forEach((row) => {
                conversations[String(row.id)] = row;
                const item = document.createElement("button");
                item.type = "button";
                item.className = "assistance-conversation-item" + (Number(row.id) === Number(selectedId) ? " is-active" : "");
                item.dataset.id = row.id;
                item.innerHTML = `<strong>${escapeHtml(title(row))}</strong><span>${escapeHtml((row.last_message || "").slice(0, 80) || "No messages yet")}</span>`;
                item.addEventListener("click", () => openConversation(Number(row.id)));
                els.list.appendChild(item);
            });
            updateInput();
        };

        const renderMessages = (msgs) => {
            els.messages.innerHTML = "";
            seenMessageIdsByConversation[String(selectedId || 0)] = new Set();
            msgs.forEach(appendMessage);
        };

        function openConversation(id) {
            selectedId = id;
            const row = conversations[String(id)];
            closed = !!(row && row.status === "closed");
            els.header.textContent = row ? title(row) : `Conversation #${id}`;
            updateInput();

            fetch(cfg.apiMessagesUrl + "&conversation_id=" + encodeURIComponent(String(id)), { credentials: "same-origin" })
                .then((r) => r.json())
                .then((d) => {
                    renderMessages(Array.isArray(d.messages) ? d.messages : []);
                    const newest = (Array.isArray(d.messages) ? d.messages : []).slice(-1)[0];
                    if (newest) lastMessageByConversation[String(id)] = Number(newest.id || 0);
                    fetchConversations();
                });
        }

        function fetchConversations() {
            fetch(cfg.apiListUrl, { credentials: "same-origin" })
                .then((r) => r.json())
                .then((d) => {
                    const rows = Array.isArray(d.conversations) ? d.conversations : [];
                    renderList(rows);
                    if (selectedId && conversations[String(selectedId)]) {
                        closed = conversations[String(selectedId)].status === "closed";
                        updateInput();
                    }
                    if (!selectedId && rows.length) openConversation(Number(rows[0].id));
                });
        }

        function pollMessages() {
            if (!selectedId || !cfg.apiMessagesUrl) return;
            const sinceId = lastMessageByConversation[String(selectedId)] || 0;
            const url = cfg.apiMessagesUrl + "&conversation_id=" + encodeURIComponent(String(selectedId)) +
                (sinceId ? "&since_id=" + encodeURIComponent(String(sinceId)) : "");

            fetch(url, { credentials: "same-origin" })
                .then((r) => r.json())
                .then((d) => {
                    const msgs = Array.isArray(d.messages) ? d.messages : [];
                    msgs.forEach((m) => {
                        appendMessage(m);
                        lastMessageByConversation[String(selectedId)] = Math.max(lastMessageByConversation[String(selectedId)] || 0, Number(m.id || 0));
                    });
                    if (d.conversation && d.conversation.status === "closed") {
                        closed = true;
                        updateInput();
                    }
                });
        }

        const send = () => {
            const text = (els.input.value || "").trim();
            if (!text || !selectedId) return;
            if (!cfg.apiSendUrl) return;
            const body = new URLSearchParams({
                conversation_id: String(selectedId),
                message: text,
                csrf_token: String(cfg.csrfToken || "")
            }).toString();
            fetch(cfg.apiSendUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body
            })
                .then(async (r) => {
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok) {
                        throw new Error(data.error || "Message could not be sent.");
                    }
                    return data;
                })
                .then((d) => {
                    closed = false;
                    updateInput();
                    const msgs = Array.isArray(d.messages) ? d.messages : [];
                    msgs.forEach((m) => {
                        appendMessage(m);
                        lastMessageByConversation[String(selectedId)] = Math.max(lastMessageByConversation[String(selectedId)] || 0, Number(m.id || 0));
                    });
                    els.input.value = "";
                    fetchConversations();
                })
                .catch((err) => {
                    appendSystemNotice(err.message || "Message could not be sent.");
                });
        };

        const closeConversation = () => {
            if (!selectedId || closed) return;
            if (!cfg.apiCloseUrl) return;
            const body = new URLSearchParams({
                conversation_id: String(selectedId),
                csrf_token: String(cfg.csrfToken || "")
            }).toString();
            fetch(cfg.apiCloseUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body
            }).then(async (r) => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    throw new Error(data.error || "Conversation could not be closed.");
                }
                return data;
            }).then((d) => {
                if (d && d.ok) {
                    closed = true;
                    updateInput();
                    if (d.message) appendMessage({ sender_type: "system", message_text: d.message });
                    fetchConversations();
                }
            }).catch((err) => {
                appendSystemNotice(err.message || "Conversation could not be closed.");
            });
        };

        els.send.addEventListener("click", send);
        els.input.addEventListener("keydown", (e) => e.key === "Enter" && send());
        els.close.addEventListener("click", closeConversation);

        updateInput();
        fetchConversations();
        window.setInterval(fetchConversations, 5000);
        window.setInterval(pollMessages, 4000);
    }

    document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", init) : init();
})();
