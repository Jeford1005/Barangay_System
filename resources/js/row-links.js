// Tappable table rows: a row carrying data-row-href navigates when the tap
// itself hits no interactive control. Keyboard and assistive-tech users
// keep the row's own links and buttons, which the tap-through skips.
document.querySelectorAll('tr[data-row-href]').forEach((row) => {
    row.classList.add('cursor-pointer');
});

document.addEventListener('click', (event) => {
    const row = event.target.closest('tr[data-row-href]');
    if (!row) return;
    if (event.target.closest('a, button, input, select, textarea, label')) return;
    window.location.href = row.dataset.rowHref;
});
