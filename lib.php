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
    if (!$PAGE->url->compare(new \moodle_url('/admin/settings.php?section=local_dobor'), URL_MATCH_BASE)) {
        return;
    }
    $url = new \moodle_url('/local/dobor/action.php');
    $node = \navigation_node::create('Generate grades', $url);
    $settingsnav->add_node($node);
}

/**
 * Генерирует "Добор 1" в курсах категории /44/.
 * @param array $options (опционально: ['path' => '/44/'])
 * @return array ['added' => int, 'skipped' => int]
 */
function local_dobor_generate_grades($options = []) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $pathlike = $options['path'] ?? '/44/';
    $added = 0;
    $skipped = 0;

    $categories = \core_course_category::get_all(['like' => $pathlike]);
    foreach ($categories as $cat) {
        foreach ($cat->get_courses() as $course) {
            $gi = \grade_item::fetch(['courseid' => $course->id, 'itemname' => 'Добор 1']);
            if ($gi) {
                $skipped++;
                continue;
            }

            $gradeitem = new \grade_item([
                'courseid' => $course->id,
                'itemname' => 'Добор 1',
                'itemtype' => 'manual',
                'gradetype' => GRADE_TYPE_VALUE,
                'grademax' => 100,
                'grademin' => 0,
                'calculation' => '=0',  // Ваша формула
                'idnumber' => 'dobor1'
            ]);
            if ($gradeitem->insert()) {
                $added++;
            }
        }
    }

    return ['added' => $added, 'skipped' => $skipped];
}

