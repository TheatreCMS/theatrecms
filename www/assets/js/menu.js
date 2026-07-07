(function () {
    if (!window.matchMedia('(hover: none) and (pointer: coarse)').matches) {
        return;
    }

    var openItems = [];

    function closeItem(item) {
        item.classList.remove('is-open');
        var link = item.querySelector(':scope > a');
        if (link) {
            link.setAttribute('aria-expanded', 'false');
        }
        openItems = openItems.filter(function (openItem) {
            return openItem !== item;
        });
    }

    function closeAll() {
        openItems.slice().forEach(closeItem);
    }

    document.querySelectorAll('.menu-item.has-children').forEach(function (item) {
        var link = item.querySelector(':scope > a');
        if (!link) {
            return;
        }

        link.setAttribute('aria-expanded', 'false');

        link.addEventListener('click', function (event) {
            if (item.classList.contains('is-open')) {
                return;
            }

            event.preventDefault();

            openItems.slice().forEach(function (openItem) {
                if (openItem !== item && !openItem.contains(item)) {
                    closeItem(openItem);
                }
            });

            item.classList.add('is-open');
            link.setAttribute('aria-expanded', 'true');
            openItems.push(item);
        });
    });

    document.addEventListener('click', function (event) {
        openItems.slice().forEach(function (item) {
            if (!item.contains(event.target)) {
                closeItem(item);
            }
        });
    });
})();
