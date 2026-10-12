/*!
 * WPToolkit multiple-choice picker: turns a checkbox list into a searchable chip picker.
 *
 * The checkboxes stay in the page and stay the source of truth, so the form submits exactly what it
 * would without this script, keyboard use works the same, and a failure here leaves a usable list.
 * No library and no jQuery. Plugins that bundle different copies of WPToolkit each print this file;
 * an element is enhanced by whichever runs first.
 */
(function () {
    'use strict';

    var READY = 'data-wptoolkit-ready';

    function make(tag, className, attributes) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        Object.keys(attributes || {}).forEach(function (name) { node.setAttribute(name, attributes[name]); });
        return node;
    }

    function toggle(box, checked) {
        box.checked = checked;
        box.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function enhance(root) {
        if (root.getAttribute('data-wptoolkit-picker') !== 'tokens' || root.hasAttribute(READY)) { return; }
        var boxes = [].slice.call(root.querySelectorAll('input[type="checkbox"]'));
        if (!boxes.length) { return; }
        root.setAttribute(READY, '1');

        var labels = {
            search: root.getAttribute('data-label-search') || 'Search…',
            remove: root.getAttribute('data-label-remove') || 'Remove %s',
            none: root.getAttribute('data-label-none') || 'No matches'
        };
        var options = boxes.map(function (box) {
            var row = box.closest('label');
            return { box: box, row: row, text: (row.textContent || '').trim() };
        });

        var control = make('div', 'wptoolkit-picker__control');
        var chips = make('ul', 'wptoolkit-picker__chips', { 'aria-live': 'polite' });
        var search = make('input', 'wptoolkit-picker__search', {
            type: 'text', autocomplete: 'off', placeholder: labels.search, 'aria-label': labels.search
        });
        var panel = make('div', 'wptoolkit-picker__panel');
        var empty = make('p', 'wptoolkit-picker__empty', { hidden: 'hidden' });
        empty.textContent = labels.none;

        options.forEach(function (option) { panel.appendChild(option.row); });
        panel.appendChild(empty);
        control.appendChild(chips);
        control.appendChild(search);
        root.appendChild(control);
        root.appendChild(panel);

        function open(show) { root.classList.toggle('is-open', show); }

        function visibleOptions() {
            return options.filter(function (option) { return !option.row.hidden; });
        }

        function filter() {
            var query = search.value.trim().toLowerCase();
            options.forEach(function (option) {
                option.row.hidden = query !== '' && option.text.toLowerCase().indexOf(query) === -1;
            });
            empty.hidden = visibleOptions().length > 0;
        }

        function renderChips() {
            chips.textContent = '';
            options.forEach(function (option) {
                if (!option.box.checked) { return; }
                var chip = make('li', 'wptoolkit-picker__chip');
                var text = make('span');
                text.textContent = option.text;
                var remove = make('button', 'wptoolkit-picker__remove', {
                    type: 'button', 'aria-label': labels.remove.replace('%s', option.text)
                });
                remove.textContent = '×';
                remove.addEventListener('click', function () {
                    toggle(option.box, false);
                    search.focus();
                });
                chip.appendChild(text);
                chip.appendChild(remove);
                chips.appendChild(chip);
            });
        }

        boxes.forEach(function (box) { box.addEventListener('change', renderChips); });
        search.addEventListener('input', function () { filter(); open(true); });
        search.addEventListener('focus', function () { open(true); });
        control.addEventListener('click', function () { search.focus(); open(true); });

        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                // Enter picks the first match instead of submitting the form.
                event.preventDefault();
                var first = visibleOptions()[0];
                if (first) {
                    toggle(first.box, !first.box.checked);
                    search.value = '';
                    filter();
                }
            } else if (event.key === 'Backspace' && search.value === '') {
                var selected = options.filter(function (option) { return option.box.checked; });
                var last = selected[selected.length - 1];
                if (last) { toggle(last.box, false); }
            } else if (event.key === 'Escape') {
                open(false);
            }
        });
        root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { open(false); search.focus(); }
        });
        root.addEventListener('focusout', function (event) {
            if (!event.relatedTarget || !root.contains(event.relatedTarget)) { open(false); }
        });
        document.addEventListener('mousedown', function (event) {
            if (!root.contains(event.target)) { open(false); }
        });
        if (root.form) {
            root.form.addEventListener('reset', function () { window.setTimeout(renderChips, 0); });
        }

        renderChips();
    }

    function init() {
        [].slice.call(document.querySelectorAll('[data-wptoolkit-picker="tokens"]')).forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
