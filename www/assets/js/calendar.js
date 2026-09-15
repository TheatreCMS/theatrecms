(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var mount = document.getElementById('calendar-mount');

        if (!mount || typeof window.FullCalendar === 'undefined') {
            return;
        }

        var eventsUrl = mount.dataset.eventsUrl;
        var listUrl = mount.dataset.listUrl;
        var detailUrl = mount.dataset.detailUrl;
        var listTarget = document.getElementById('event-list-content');
        var modalTarget = document.getElementById('event-detail-modal-content');

        var calendar = new window.FullCalendar.Calendar(mount, {
            initialView: 'dayGridMonth',
            height: 'auto',
            // No app-level timezone is configured anywhere in TheatreCMS, and the
            // events.json feed intentionally omits UTC offsets (see calendar.php),
            // so FullCalendar treats each value as a literal wall-clock time under
            // the default 'local' setting rather than converting it.
            events: {
                url: eventsUrl
            },
            eventClick: function (info) {
                info.jsEvent.preventDefault();

                if (modalTarget && window.htmx && detailUrl) {
                    window.htmx.ajax('GET', detailUrl + '/' + info.event.id, {
                        target: '#event-detail-modal-content',
                        swap: 'innerHTML'
                    });
                }
            },
            datesSet: function (info) {
                if (listTarget && window.htmx && listUrl) {
                    window.htmx.ajax('GET', listUrl + '?start=' + encodeURIComponent(info.startStr) + '&end=' + encodeURIComponent(info.endStr), {
                        target: '#event-list-content',
                        swap: 'innerHTML'
                    });
                }
            }
        });

        calendar.render();

        window.TheatreCMSCalendar = { instance: calendar };

        // HTML attribute names are lowercased by the browser, so Alpine can't reliably
        // bind to htmx's camelCase "htmx:afterRequest" event via an x-on:* attribute.
        // Bridge it here in plain JS (case-sensitive) to a lowercase custom event that
        // both the FullCalendar-driven modal open and the list-view card clicks share.
        document.body.addEventListener('htmx:afterRequest', function (evt) {
            if (evt.detail && evt.detail.target && evt.detail.target.id === 'event-detail-modal-content') {
                window.dispatchEvent(new CustomEvent('calendar-modal-open'));
            }
        });
    });
})();
