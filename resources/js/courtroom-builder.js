const builder = document.querySelector('[data-courtroom-builder]');
if (builder) {
    const reindex = () => {
        for (const type of ['roles', 'stages', 'criteria']) {
            const rows = builder.querySelector(`[data-repeater="${type}"] > [data-rows]`);
            [...rows.children].forEach((row, index) => {
                row.querySelectorAll('[name]').forEach(input => {
                    input.name = input.name.replace(new RegExp(`^${type}\\[[^\\]]+\\]`), `${type}[${index}]`);
                });
                if (type === 'stages') {
                    [...row.querySelector('[data-repeater="actions"] > [data-rows]').children].forEach((action, actionIndex) => {
                        action.querySelectorAll('[name]').forEach(input => {
                            input.name = input.name.replace(/^stages\[[^\]]+\]\[actions\]\[[^\]]+\]/, `stages[${index}][actions][${actionIndex}]`);
                        });
                    });
                }
            });
        }
    };
    const updateOptions = () => {
        const stageKeys = [...builder.querySelectorAll('[data-stage-key]')].map(input => input.value).filter(Boolean);
        const roleKeys = [...builder.querySelectorAll('[data-role-key]')].map(input => input.value).filter(Boolean);
        for (const [attribute, keys] of [['data-stage-options', stageKeys], ['data-role-options', roleKeys]]) {
            builder.querySelectorAll(`[${attribute}]`).forEach(select => {
                const selected = [...select.selectedOptions].map(option => option.value);
                const placeholder = [...select.options].find(option => option.value === '' || option.value === '*');
                select.replaceChildren();
                if (placeholder) select.append(placeholder);
                [...new Set(keys)].forEach(key => select.add(new Option(key, key, false, selected.includes(key))));
            });
        }
    };
    builder.addEventListener('click', event => {
        const add = event.target.closest('[data-add-row]');
        if (add) {
            const rows = add.closest('[data-repeater]').querySelector(':scope > [data-rows]');
            rows.append(document.getElementById(add.dataset.addRow).content.cloneNode(true));
            reindex();
            updateOptions();
            rows.lastElementChild.querySelector('input, select, textarea')?.focus();
        }
        const remove = event.target.closest('[data-remove-row]');
        if (remove) { remove.closest('[data-repeater-row]').remove(); reindex(); updateOptions(); }
        const move = event.target.closest('[data-move-row]');
        if (move) {
            const row = move.closest('[data-repeater-row]');
            if (move.dataset.moveRow === 'up' && row.previousElementSibling) row.previousElementSibling.before(row);
            if (move.dataset.moveRow === 'down' && row.nextElementSibling) row.nextElementSibling.after(row);
            reindex();
        }
    });
    builder.addEventListener('change', event => {
        if (event.target.matches('[data-stage-key], [data-role-key]')) updateOptions();
    });
    builder.addEventListener('submit', reindex);
}
