<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Main logic.
 *
 * @package     local_dobor
 * @copyright   2026 Solomonov Ifraim <solomonov@sfedu.ru>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Добавляет ссылку в настройки.
 */
function local_dobor_extend_settings_navigation(\settings_navigation $settingsnav, $context) {
    global $PAGE;

    // Проверяем права администратора
    if (!has_capability('moodle/site:config', $context)) {
        return;
    }

    // Проверяем, что находимся в разделе настроек плагина
    if ($PAGE->url->compare(new \moodle_url('/admin/settings.php'), URL_MATCH_BASE)
        && $PAGE->url->get_param('section') == 'local_dobor') {
        $url = new \moodle_url('/local/dobor/action.php');
        $node = $settingsnav->add(
            'Генерация оценок',
            $url,
            \navigation_node::TYPE_SETTING,
            null,
            'local_dobor_generate',
            new \pix_icon('i/settings', '')
        );
    }
}

