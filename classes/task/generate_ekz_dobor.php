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

class generate_ekz_dobor extends \core\task\adhoc_task
{

    public function get_name()
    {
        return get_string('task_generate_ekz_dobor', 'local_dobor');
    }

    public static function instance(int $userid, string $path): self
    {
        $task = new self();
        $task->set_custom_data((object)['path' => $path]);
        $task->set_userid($userid);
        return $task;
    }

    public function execute()
    {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot.'/mod/assign/lib.php');
        require_once($CFG->dirroot.'/mod/assign/locallib.php');
        require_once($CFG->dirroot.'/course/modlib.php');  // ← create_module()
        require_once($CFG->dirroot.'/course/lib.php');

        $data = $this->get_custom_data();
        $path = $data->path;
        $pathlike = "%/".$path."/%";
        $itemstocreate = [
            [
                'idnumber' => 'gen_retake1_modeus',
                'itemname' => 'СЮДА ОЦЕНКИ НЕ ВЫСТАВЛЯТЬ - Пересдача экзамена 1 (для модеуса)',
                'need' => 'gen_retake1',
            ],
            [
                'idnumber' => 'gen_retake2_modeus',
                'itemname' => 'СЮДА ОЦЕНКИ НЕ ВЫСТАВЛЯТЬ - Пересдача экзамена 2 (для модеуса)',
                'need' => 'gen_retake2',
            ],
        ];

        // Получаем категории с нужным path
        $sql = "SELECT cc.* 
            FROM {course_categories} cc 
            WHERE cc.path LIKE ?";
        $categories = $DB->get_recordset_sql($sql, [$pathlike]);
        mtrace('Кинул запрос с pathlike '.$pathlike);

        // Проходимся по каждой категории
        foreach ($categories as $category) {
            mtrace('Категория с id '.$category->id);
            // Получаем курсы в категории
            $courses = get_courses($category->id);

            // Проходимся по каждому курсу
            foreach ($courses as $course) {
                mtrace('Курс с id'.$course->id);

                $sql = "SELECT id, idnumber
                    FROM {grade_items}
                    WHERE courseid = :courseid
                      AND (itemname LIKE :itemnamerus OR itemname LIKE :itemnameeng)
                      AND grademax = 40";
                $params = ['courseid' => $course->id, 'itemnamerus' => '%экзамен%', 'itemnameeng' => '%exam%'];
                $hasexam = $DB->record_exists_sql($sql, $params);

                if (!$hasexam) {
                    mtrace('Курс без экзамена, пропускаем');
                    continue;
                }

                $changesmade = false;

                foreach ($itemstocreate as $item) {
                    // Проверяем существование доборов
                    $dependency = \grade_item::fetch(['idnumber' => $item['need'], 'courseid' => $course->id]);

                    if (!$dependency) {
                        mtrace('Нет '.$item['need']);
                        continue;
                    }

                    $exists = $DB->record_exists('grade_items', ['courseid' => $course->id, 'idnumber' => $item['idnumber']]);

                    if ($exists) {
                        mtrace($item['itemname'].' уже есть');
                        continue;
                    }

                    $gradeitem = new \grade_item();
                    $gradeitem->courseid = $course->id;
                    $gradeitem->itemname = $item['itemname'];
                    $gradeitem->itemtype = 'manual';
                    $gradeitem->idnumber = $item['idnumber'];
                    $gradeitem->grademax = 40;
                    $gradeitem->grademin = 0;
                    $gradeitem->gradepass = 0;
                    $gradeitem->iteminfo = 'Автоматически созданный элемент оценки';
                    $gradeitem->locked = 0;
                    $gradeitem->hidden = 1;
                    $gradeitem->multfactor = 0;
                    $gradeitem->weightoverride = 1;
                    $gradeitem->aggregationcoef = 0;
                    $gradeitem->aggregationcoef2 = 0;
                    $gradeitem->calculation = "=##gi$dependency->id##";

                    try {
                        $insertresult = $gradeitem->insert();
                        if ($insertresult) {
                            $changesmade = true;
                            mtrace("Создан assign ID: {$insertresult->id} ({$item['itemname']})");
                        }
                    } catch (Exception $e) {
                        mtrace("Ошибка создания {$item['itemname']}: " . $e->getMessage());
                    }
                }

                if ($changesmade) {
                    \grade_regrade_final_grades($course->id);
                    mtrace('Пересчет оценок выполнен для курса ' . $course->id);
                }
            }
        }
        $categories->close();
    }
}
