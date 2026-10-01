// Mobile toolbars are horizontal swipe strips: always land on Search
// (scrollLeft = 0) on fresh load and on history/bfcache restore, which
// otherwise replays the strip's last scrolled position mid-row.
function resetToolbarScroll() {
    document.querySelectorAll('.module-toolbar').forEach((bar) => {
        bar.scrollLeft = 0;
    });
}

document.addEventListener('DOMContentLoaded', resetToolbarScroll);
window.addEventListener('pageshow', (event) => {
    if (event.persisted) resetToolbarScroll();
});
