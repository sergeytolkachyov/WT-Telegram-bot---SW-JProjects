/**
 * @package     System - WT Telegram bot - SW JProjects
 * @version     1.0.0
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright   Copyright (C) 2024 Sergey Tolkachyov
 * @license     GNU/GPL 3
 * @since       1.0.0
 */

(() => {
    window.Wttelegrambotswjprojects = () => {

        let item_ids = [];

        let currentUrl = new URL(window.location.href);
        let view = currentUrl.searchParams.get('view');
        if ((view === 'version' || view === 'project' || view === 'document') && currentUrl.searchParams.get('layout') === 'edit') {
            item_ids.push(currentUrl.searchParams.get('id'));
        } else if (view === 'versions' || view === 'projects' || view === 'documentation') {
            let checkboxes = document.querySelectorAll('#adminForm input[name="cid[]"]:checked');

            if (checkboxes.length === 0) {
                alert('There is nothing selected');
                return;
            }
            checkboxes.forEach(checkbox => {
                item_ids.push(checkbox.value);
            });
        } else {
            return;
        }

        Joomla.request({
            url: 'index.php?option=com_ajax&plugin=wttelegrambotswjprojects&group=system&format=json',
            method: 'POST',
            data: JSON.stringify({
                'item_ids': item_ids,
                'type': view,
            }),
            onSuccess: function (response, xhr) {
                //Проверяем пришли ли ответы
                if (response !== '') {
                    let result = JSON.parse(response);

                    let sent_items = result.data.sent_items.length;

                    Joomla.renderMessages({
                        ['info']: [sent_items + ' item(s) has been sent to Telegram']
                    })
                }
            },
        });

    };
})();