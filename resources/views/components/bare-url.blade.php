{{--
  Pathless address bar.

  The requirement is that the browser shows only the bare domain on every page,
  never /dashboard or /residents. Only the displayed history entry is rewritten:
  hrefs, forms, redirects and server routing are untouched, so navigation still
  behaves exactly as before.

  The query string is carried over deliberately. crud-dialogs.js deep links with
  ?open=<suffix> (dashboard quick actions) and reads window.location.search after
  this runs; dropping the query here would leave those quick actions navigating
  to an index page that never opens its dialog.

  Trade-off: refresh and Back resolve to the domain root, because the real path
  no longer exists in history. `request()->root()` is used rather than "/" so the
  snippet also behaves under a subdirectory install (local XAMPP).
--}}
<script>try { history.replaceState(null, '', @json(request()->root()) + location.search); } catch (e) {}
</script>
