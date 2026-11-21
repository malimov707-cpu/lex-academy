export function courtroomEvidence(root) {
    return [...root.querySelectorAll('[data-evidence-open]')].map(({ dataset }) => ({
        id: dataset.evidenceId,
        title: dataset.evidenceTitle,
        summary: dataset.evidenceSummary,
        body: dataset.evidenceBody,
        source: dataset.evidenceSource,
        version: dataset.evidenceVersion,
        type: dataset.evidenceType,
    }));
}

export function initialiseEvidenceBoard(root) {
    const items = courtroomEvidence(root);
    const status = root.querySelector('[data-evidence-board-status]');
    const select = root.querySelector('[data-evidence-select]');
    const choose = (id, focus = false, updateForm = true) => {
        const item = items.find(item => item.id === String(id));
        if (!item) return;
        root.dataset.boardEvidenceId = item.id;
        if (select && updateForm) select.value = item.id;
        root.querySelectorAll('[data-evidence-open]').forEach(button => {
            if (button.dataset.evidenceId === item.id) button.setAttribute('aria-current', 'true');
            else button.removeAttribute('aria-current');
        });
        if (status) status.textContent = `Doskada: ${item.title} (${items.indexOf(item) + 1}/${items.length})`;
        window.dispatchEvent(new CustomEvent('courtroom-evidence', { detail: { id: item.id, focus } }));
    };
    root.querySelectorAll('[data-evidence-step]').forEach(button => {
        button.disabled = items.length < 2;
        button.addEventListener('click', () => {
            const current = items.findIndex(item => item.id === root.dataset.boardEvidenceId);
            choose(items[(current + Number(button.dataset.evidenceStep) + items.length) % items.length]?.id, true);
        });
    });
    root.querySelector('[data-board-open-document]')?.addEventListener('click', () => {
        [...root.querySelectorAll('[data-evidence-open]')].find(button => button.dataset.evidenceId === root.dataset.boardEvidenceId)?.click();
    });
    select?.addEventListener('change', () => choose(select.value, true));
    const onSelect = event => choose(event.detail.id, event.detail.focus ?? true);
    window.addEventListener('courtroom-select-evidence', onSelect);
    window.addEventListener('pagehide', () => window.removeEventListener('courtroom-select-evidence', onSelect), { once: true });
    if (items.length) choose(items[0].id, false, false);
    else if (status) status.textContent = 'Ushbu ssenariyda sizga ochiq dalil yo‘q.';
}

export function drawEvidenceBoard(context, item, index, count) {
    context.fillStyle = '#f4f1e9';
    context.fillRect(0, 0, 1536, 1024);
    context.fillStyle = '#142b46';
    context.fillRect(0, 0, 1536, 120);
    context.fillStyle = '#ffffff';
    context.font = 'bold 38px sans-serif';
    context.fillText('DALILLAR DOSKASI', 64, 76);
    context.font = '30px sans-serif';
    context.fillText(count ? `${index + 1} / ${count}` : '0 / 0', 1330, 76);
    const paragraph = (text, y, size, limit, color, bold = false) => {
        context.fillStyle = color;
        context.font = `${bold ? 'bold ' : ''}${size}px sans-serif`;
        const lines = [];
        let line = '';
        for (const word of String(text ?? '').split(/\s+/)) {
            if (line && context.measureText(`${line} ${word}`).width > 1408) {
                lines.push(line);
                line = word;
            } else line = line ? `${line} ${word}` : word;
        }
        if (line) lines.push(line);
        lines.slice(0, limit).forEach((line, row) => {
            const clipped = row === limit - 1 && lines.length > limit ? `${line.slice(0, -2)}…` : line;
            context.fillText(clipped, 64, y + row * size * 1.4, 1408);
        });
    };
    paragraph(item?.title ?? 'Dalil mavjud emas', 198, 46, 2, '#142b46', true);
    paragraph(item?.summary ?? 'Sizga ochiq ish materiallari shu yerda ko‘rsatiladi.', 340, 30, 2, '#516175');
    paragraph(item?.body || (item ? 'Faylni ko‘rish uchun “Hujjatni ochish” tugmasini bosing.' : ''), 458, 32, 9, '#263647');
    context.fillStyle = '#d9dfdf';
    context.fillRect(64, 900, 1408, 2);
    paragraph(item ? `Manba: ${item.source || 'Ko‘rsatilmagan'} · v${item.version || 1}` : 'Dalillar paneli', 953, 25, 1, '#516175');
    paragraph('To‘liq matn va shaxsiy qaydlar: Hujjatni ochish', 995, 22, 1, '#516175');
}
