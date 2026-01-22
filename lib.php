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
    global $PAGE, $USER;

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

/**
 * Генерирует "Добор 1" в курсах категории /44/.
 */
function local_dobor_generate_grades($options = []) {
    global $DB, $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    $pathlike = $options['path'] ?? '/2/';
    $itemstogenerate = [
        'dobor1' => [
            'name' => 'Добор 1',
            'added' => 0,
            'skipped' => 0,
            'updated' => 0,
        ],
        'dobor2' => [
            'name' => 'Добор 2',
            'added' => 0,
            'skipped' => 0,
            'updated' => 0,
        ],
        'fix' => [
            'name' => 'Баллы за семестр',
            'added' => 0,
            'skipped' => 0,
            'updated' => 0,
        ],
    ];

    // Получаем категории с нужным path
    $sql = "SELECT cc.* 
            FROM {course_categories} cc 
            WHERE cc.path LIKE ?";
    $categories = $DB->get_records_sql($sql, [$pathlike . '%']);

    foreach ($categories as $category) {
        $courses = get_courses($category->id);

        foreach ($courses as $course) {
            foreach (['dobor1', 'dobor2', 'fix'] as $itemid) {
                // Проверяем существование grade item
                $record = $DB->get_record('grade_items', [
                    'courseid' => $course->id,
                    'itemname' => $itemstogenerate[$itemid]['name'],
                ]);

                if ($record) {
                    if ($record->idnumber === $itemid) {
                        $itemstogenerate[$itemid]['skipped']++;
                    } else {
                        $record->idnumber = $itemid;
                        $DB->update_record('grade_items', $record);
                        $itemstogenerate[$itemid]['updated']++;
                    }
                    continue;
                }

                // Создаем grade item
                $gradeitem = new \grade_item();
                $gradeitem->courseid = $course->id;
                $gradeitem->itemname = $itemstogenerate[$itemid]['name'];
                $gradeitem->itemtype = 'manual';
                $gradeitem->idnumber = $itemid;
                $gradeitem->grademax = 100;
                $gradeitem->grademin = 0;
                $gradeitem->gradepass = 0;
                $gradeitem->iteminfo = 'Автоматически созданный элемент оценки';
                $gradeitem->aggregationcoef = 0;
                $gradeitem->locked = 0;
                $gradeitem->sortorder = 999;

                if ($gradeitem->insert()) {
                    $itemstogenerate[$itemid]['added']++;
                }
            }
        }
    }

    return "Добавлено:\n
            - добор 1 - {$itemstogenerate['dobor1']['added']}\n
            - добор 2 - {$itemstogenerate['dobor2']['added']}\n
            - баллы за семестр - {$itemstogenerate['fix']['added']}\n
            Обновлено:\n
            - добор 1 - {$itemstogenerate['dobor1']['updated']}\n
            - добор 2 - {$itemstogenerate['dobor2']['updated']}\n
            - баллы за семестр - {$itemstogenerate['fix']['updated']}
            Пропущено:\n
            - добор 1 - {$itemstogenerate['dobor1']['skipped']}\n
            - добор 2 - {$itemstogenerate['dobor2']['skipped']}\n
            - баллы за семестр - {$itemstogenerate['fix']['skipped']}";
}

