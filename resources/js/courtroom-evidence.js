export function initialiseEvidenceDialog(root, request, uuid) {
    const dialog = root.querySelector('[data-evidence-dialog]');
    if (!dialog) return;

    const body = dialog.querySelector('[data-dialog-body]');
    const form = dialog.querySelector('[data-evidence-annotation-form]');
    const save = form.querySelector('button[type="submit"]');
    const note = form.querySelector('textarea');
    const selectedText = form.querySelector('[data-annotation-selection]');
    const clearSelection = form.querySelector('[data-clear-annotation-selection]');
    const cite = dialog.querySelector('[data-cite-selection]');
    const input = root.querySelector('[data-courtroom-input]');
    const list = dialog.querySelector('[data-evidence-annotations]');
    const status = dialog.querySelector('[data-annotations-status]');
    let current;

    const selectionChanged = () => {
        if (!current?.text) return;
        const selection = window.getSelection();
        if (!selection?.rangeCount || selection.isCollapsed) return;
        const range = selection.getRangeAt(0);
        if (!body.contains(range.startContainer) || !body.contains(range.endContainer)) return;
        const quote = range.toString();
        if (!quote.trim()) return;
        const before = document.createRange();
        before.selectNodeContents(body);
        before.setEnd(range.startContainer, range.startOffset);
        const start = [...before.toString()].length;
        current.selection = { start_offset: start, end_offset: start + [...quote].length };
        selectedText.textContent = quote;
        clearSelection.hidden = false;
        cite.hidden = false;
    };
    document.addEventListener('selectionchange', selectionChanged);
    const resetSelection = () => {
        if (current) current.selection = null;
        selectedText.textContent = '';
        clearSelection.hidden = true;
        cite.hidden = true;
    };
    clearSelection.addEventListener('click', () => {
        resetSelection();
        window.getSelection()?.removeAllRanges();
    });

    cite.addEventListener('click', () => {
        if (!current?.selection || !input) return;
        const { start_offset, end_offset } = current.selection;
        const quote = [...current.text].slice(start_offset, end_offset).join('');
        const citation = `“${quote}” (${current.title})`;
        const draft = input.value ? `${input.value}\n${citation}` : citation;
        if (input.maxLength >= 0 && draft.length > input.maxLength) {
            status.textContent = cite.dataset.lengthMessage;
            return;
        }
        input.value = draft;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        window.dispatchEvent(new CustomEvent('courtroom-select-evidence', { detail: { id: current.id, focus: false } }));
        dialog.close();
        input.focus();
        input.setSelectionRange(draft.length, draft.length);
    });

    const render = state => {
        const characters = [...state.text];
        const fragment = document.createDocumentFragment();
        const highlights = [];
        const ranges = state.annotations.filter(item => Number.isInteger(item.start_offset)).sort((left, right) => left.start_offset - right.start_offset);
        for (const { start_offset: start, end_offset: end } of ranges) {
            const last = highlights.at(-1);
            if (last && last.end >= start) last.end = Math.max(last.end, end);
            else highlights.push({ start, end });
        }
        let offset = 0;
        for (const { start, end } of highlights) {
            const mark = document.createElement('mark');
            mark.textContent = characters.slice(start, end).join('');
            fragment.append(characters.slice(offset, start).join(''), mark);
            offset = end;
        }
        fragment.append(characters.slice(offset).join(''));
        if (!state.text) fragment.append('Fayl ko‘rinishidagi dalil.');
        body.replaceChildren(fragment);

        list.replaceChildren();
        for (const annotation of state.annotations) {
            const row = document.createElement('li');
            const quote = document.createElement('q');
            const content = document.createElement('p');
            const remove = document.createElement('button');
            if (annotation.start_offset !== null) {
                quote.textContent = characters.slice(annotation.start_offset, annotation.end_offset).join('');
                row.append(quote);
            }
            if (annotation.note) {
                content.textContent = annotation.note;
                row.append(content);
            }
            remove.type = 'button';
            remove.textContent = 'Qaydni o‘chirish';
            remove.addEventListener('click', async () => {
                remove.disabled = true;
                try {
                    await request(`${state.url}/${annotation.id}`, { method: 'DELETE' });
                    if (current !== state) return;
                    state.annotations = state.annotations.filter(item => item.id !== annotation.id);
                    resetSelection();
                    render(state);
                    status.textContent = 'Qayd o‘chirildi.';
                } catch (error) {
                    if (current === state) status.textContent = error.message;
                    remove.disabled = false;
                }
            });
            row.append(remove);
            list.append(row);
        }
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const state = current;
        if (!state?.ready || save.disabled) return;
        const draft = { note: note.value.trim() || null, ...state.selection };
        if (!draft.note && !state.selection) {
            status.textContent = 'Izoh yozing yoki dalil matnidan parcha tanlang.';
            note.focus();
            return;
        }
        const signature = JSON.stringify(draft);
        if (state.pending?.signature !== signature) state.pending = { signature, key: uuid() };
        save.disabled = true;
        note.disabled = true;
        status.textContent = 'Saqlanmoqda…';
        try {
            const { data } = await request(state.url, {
                method: 'POST', body: JSON.stringify({ ...draft, idempotency_key: state.pending.key }),
            });
            if (current !== state) return;
            state.pending = null;
            if (!state.annotations.some(item => item.id === data.annotation.id)) state.annotations.push(data.annotation);
            note.value = '';
            resetSelection();
            render(state);
            status.textContent = 'Qayd saqlandi.';
        } catch (error) {
            if (current === state) status.textContent = Object.values(error.validation ?? {})[0]?.[0] ?? error.message;
        } finally {
            if (current === state) save.disabled = note.disabled = false;
        }
    });

    root.querySelectorAll('[data-evidence-open]').forEach(button => button.addEventListener('click', async () => {
        const evidence = button.dataset;
        const state = current = { id: evidence.evidenceId, title: evidence.evidenceTitle, text: evidence.evidenceBody, url: evidence.evidenceAnnotationsUrl, annotations: [], ready: false };
        dialog.querySelector('[data-dialog-type]').textContent = evidence.evidenceType.toUpperCase();
        dialog.querySelector('[data-dialog-title]').textContent = evidence.evidenceTitle;
        dialog.querySelector('[data-dialog-summary]').textContent = evidence.evidenceSummary;
        dialog.querySelector('[data-dialog-source]').textContent = `Manba: ${evidence.evidenceSource}`;
        dialog.querySelector('[data-dialog-version]').textContent = `v${evidence.evidenceVersion}`;
        const file = dialog.querySelector('[data-dialog-file]');
        file.hidden = !evidence.evidenceUrl;
        if (evidence.evidenceUrl) file.href = evidence.evidenceUrl;
        else file.removeAttribute('href');
        form.hidden = !state.url;
        form.querySelector('[data-annotation-hint]').hidden = !state.text;
        save.disabled = true;
        note.disabled = false;
        note.value = '';
        resetSelection();
        render(state);
        status.textContent = state.url ? 'Qaydlar yuklanmoqda…' : '';
        dialog.showModal();
        window.dispatchEvent(new CustomEvent('courtroom-select-evidence', { detail: { id: evidence.evidenceId } }));
        if (!state.url) return;

        try {
            const { data } = await request(state.url);
            if (current !== state) return;
            state.annotations = data.annotations;
            state.ready = true;
            status.textContent = '';
            save.disabled = false;
            render(state);
        } catch (error) {
            if (current === state) status.textContent = error.message;
        }
    }));
    dialog.addEventListener('close', () => { current = null; });
}
