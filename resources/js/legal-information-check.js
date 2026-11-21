const statusLabels = {
    supported_by_verified_sources: 'supported',
    partially_supported: 'partial',
    potentially_misleading: 'misleading',
    contradicted_by_verified_sources: 'contradicted',
    cannot_be_verified: 'cannot',
    requires_expert_review: 'review',
};

const statusClasses = {
    supported: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    partial: 'bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200',
    misleading: 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
    contradicted: 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200',
    cannot: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
    review: 'bg-violet-50 text-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
};

const labelText = (form, key) => form.querySelector(`[data-legal-check-label-${key}]`)?.textContent.trim() || '';

document.querySelectorAll('[data-legal-check-form]').forEach(form => {
    const textarea = form.querySelector('[name="text"]');
    const button = form.querySelector('[data-legal-check-submit]');
    const status = document.querySelector('[data-legal-check-status]');
    const results = document.querySelector('[data-legal-check-results]');
    const overall = document.querySelector('[data-legal-check-overall]');
    const claims = document.querySelector('[data-legal-check-claims]');
    const disclaimer = document.querySelector('[data-legal-check-disclaimer]');
    if (!textarea || !button || !status || !results || !overall || !claims || !disclaimer) return;

    const renderCitation = (citation) => {
        const block = document.createElement('blockquote');
        block.className = 'mt-3 border-s-2 border-blue-400 ps-3 text-sm leading-6 text-slate-700 dark:text-slate-200';
        const quote = document.createElement('p');
        quote.textContent = `“${citation.quote}”`;
        block.appendChild(quote);

        const sourceMeta = document.createElement('p');
        sourceMeta.className = 'mt-2 flex flex-wrap items-center gap-2 text-xs';
        const link = document.createElement('a');
        try {
            const sourceUrl = new URL(citation.url);
            if (sourceUrl.protocol === 'https:') {
                link.href = sourceUrl.href;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.className = 'font-semibold text-blue-700 underline dark:text-blue-300';
                link.textContent = `${citation.title || citation.reference} ↗`;
                sourceMeta.appendChild(link);
            }
        } catch {
            // An invalid link is omitted; the quotation and source reference remain visible.
        }
        const verification = document.createElement('span');
        verification.textContent = `${citation.reference || ''}${citation.verified_at ? ` · Inson tekshirgan: ${new Date(citation.verified_at).toLocaleDateString()}` : ''}`;
        sourceMeta.appendChild(verification);
        block.appendChild(sourceMeta);
        return block;
    };

    const renderResult = data => {
        const overallKey = statusLabels[data.overall_status] || 'review';
        overall.className = `inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold ${statusClasses[overallKey]}`;
        overall.textContent = labelText(form, overallKey);
        claims.replaceChildren();
        (Array.isArray(data.claims) ? data.claims : []).forEach((claim, index) => {
            const claimKey = statusLabels[claim.status] || 'review';
            const article = document.createElement('article');
            article.className = 'rounded-xl border border-slate-200 p-4 dark:border-slate-700';
            const header = document.createElement('div');
            header.className = 'flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between';
            const title = document.createElement('h3');
            title.className = 'font-semibold text-slate-900 dark:text-white';
            title.textContent = `${index + 1}. ${claim.claim}`;
            const badge = document.createElement('span');
            badge.className = `inline-flex w-fit shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${statusClasses[claimKey]}`;
            badge.textContent = labelText(form, claimKey);
            header.append(title, badge);
            const explanation = document.createElement('p');
            explanation.className = 'mt-3 text-sm leading-6 text-slate-700 dark:text-slate-300';
            explanation.textContent = claim.explanation;
            article.append(header, explanation);
            (Array.isArray(claim.evidence) ? claim.evidence : []).forEach(citation => article.appendChild(renderCitation(citation)));
            claims.appendChild(article);
        });
        disclaimer.textContent = data.disclaimer || '';
        results.hidden = false;
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.reportValidity() || button.disabled) return;

        button.disabled = true;
        results.hidden = true;
        claims.replaceChildren();
        status.textContent = form.querySelector('[data-legal-check-loading]')?.textContent.trim() || '';
        form.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ text: textarea.value }),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.errors?.text?.[0] || data.error || data.message || 'Tahlil bajarilmadi. Keyinroq qayta urinib ko‘ring.');
            }
            renderResult(data);
            status.textContent = '';
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : (form.querySelector('[data-legal-check-failed]')?.textContent.trim() || '');
        } finally {
            button.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
});
