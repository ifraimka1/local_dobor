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
 * Generate grades task.
 *
 * @package     local_dobor
 * @copyright   2026 Solomonov Ifraim <solomonov@sfedu.ru>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dobor\task;

defined('MOODLE_INTERNAL') || die();

class generate_grades extends \core\task\adhoc_task
{

    public function get_name()
    {
        return get_string('task_generate_grades', 'local_dobor');
    }

    public static function instance(int $userid, string $path): self
    {
        $task = new self();
        $task->set_custom_data((object)['path' => $path]);
        // Можно указать пользователя, от имени которого выполняется задача.
        $task->set_userid($userid);
        return $task;
    }

    public function execute()
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $data = $this->get_custom_data();
        $path = $data->path;

        $pathlike = "%/".$path."/%";
        $itemstogenerate = [
            'dobor1' => [
                'name' => 'Добор 1',
                'added' => 0,
                'skipped' => 0,
                'updated' => 0,
                'hidden' => 0,
            ],
            'dobor2' => [
                'name' => 'Добор 2',
                'added' => 0,
                'skipped' => 0,
                'updated' => 0,
                'hidden' => 0,
            ],
            'fix' => [
                'name' => 'Баллы за семестр',
                'added' => 0,
                'skipped' => 0,
                'updated' => 0,
                'hidden' => 1,
            ],
        ];

        // Получаем категории с нужным path
        $sql = "SELECT cc.* 
            FROM {course_categories} cc 
            WHERE cc.path LIKE ?";
        $categories = $DB->get_records_sql($sql, [$pathlike]);
        mtrace('Кинул запрос с pathlike '.$pathlike);
        mtrace('Статус запроса: '.count($categories));

        foreach ($categories as $category) {
            mtrace('Категория с id '.$category->id);
            $courses = get_courses($category->id);

            foreach ($courses as $course) {
                mtrace('Курс с id'.$course->id);
                foreach (['dobor1', 'dobor2', 'fix'] as $itemid) {
                    // Проверяем существование grade item
                    $record = $DB->get_record('grade_items', [
                        'courseid' => $course->id,
                        'itemname' => $itemstogenerate[$itemid]['name'],
                    ]);

                    if ($record) {
                        if ($record->idnumber === $itemid
                            && $record->hidden == $itemstogenerate[$itemid]['hidden']
                            && $record->multfactor == 0
                            && $record->weightoverride == 1
                            && $record->aggregationcoef == 0
                            && $record->aggregationcoef2 == 0) {
                            $itemstogenerate[$itemid]['skipped']++;
                            mtrace('Пропустил элемент с id '.$record->id);
                        } else {
                            mtrace('hidden = '.$record->hidden.' multfactor = '.$record->multfactor.' weighttooverride = '.$record->weightoverride);
                            $record->idnumber = $itemid;
                            $record->hidden = $itemstogenerate[$itemid]['hidden'];
                            $record->multfactor = 0;
                            $record->weightoverride = 1;
                            $record->aggregationcoef = 0;
                            $record->aggregationcoef2 = 0;
                            $DB->update_record('grade_items', $record);
                            $itemstogenerate[$itemid]['updated']++;
                            mtrace('Обновил элемент с id '.$record->id);
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
                    $gradeitem->multfactor = 0;
                    $gradeitem->weightoverride = 1;
                    $gradeitem->aggregationcoef = 0;
                    $gradeitem->aggregationcoef2 = 0;
                    $gradeitem->locked = 0;
                    $gradeitem->hidden = $itemstogenerate[$itemid]['hidden'];
                    $insertresult = $gradeitem->insert();

                    if ($insertresult) {
                        $itemstogenerate[$itemid]['added']++;
                        mtrace('Создал элемент с id '.$insertresult);
                    }
                }
            }
        }

        $result = "Добавлено:\n
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

        mtrace($result);
    }
}
