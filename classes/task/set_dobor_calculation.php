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

class set_dobor_calculation extends \core\task\adhoc_task
{

    public function get_name()
    {
        return get_string('task_set_dobor_calculation', 'local_dobor');
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

        $data = $this->get_custom_data();
        $path = $data->path;
        $pathlike = "%/".$path."/%";
        $semestridnumber = 'semestr';
        $itemstoupdate = [
            'dobor1' => [
                'calculation' => '=[[semestr]] - [[fix]]',
                'calculationexam' => '=min([[semestr]]; max([[fix]]; 38)) - [[fix]]',
            ],
            'dobor2' => [
                'calculation' => '=[[semestr]] - [[fix]] - [[dobor1]]',
                'calculationexam' => '=min([[semestr]]; max([[fix]]; 38)) - [[fix]] - [[dobor1]]',
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

                $semcatitem = \grade_item::fetch([
                    'idnumber' => $semestridnumber,
                    'itemtype' => 'category',
                    'courseid' => $course->id
                ]);

                if (!$semcatitem) {
                    mtrace('Не нашли категорию семестра');
                    continue;
                }

                // Проверяем существование доборов
                $dobor1 = \grade_item::fetch(['idnumber' => 'dobor1', 'courseid' => $course->id]);

                if (!$dobor1) {
                    mtrace('Нет добора 1');
                    continue;
                }

                $dobor2 = \grade_item::fetch(['idnumber' => 'dobor2', 'courseid' => $course->id]);

                if (!$dobor2) {
                    mtrace('Нет добора 2');
                    continue;
                }

                // Проверяем, что есть fix
                $fix = \grade_item::fetch(['courseid' => $course->id, 'idnumber' => 'fix']);

                if (!$fix) {
                    mtrace('Нет фикса');
                    continue;
                }

                $sql = "SELECT id, idnumber
                    FROM {grade_items}
                    WHERE courseid = :courseid
                      AND (itemname LIKE :itemnamerus OR itemname LIKE :itemnameeng)
                      AND grademax = 40";
                $params = ['courseid' => $course->id, 'itemnamerus' => '%экзамен%', 'itemnameeng' => '%exam%'];
                $hasexam = $DB->record_exists_sql($sql, $params);

                $calculation1 = '';
                $calculation2 = '';

                if ($hasexam) {
                    mtrace('Есть экзамен');
                    $calculation1 = "=min(##gi$semcatitem->id##,max(##gi$fix->id##,38))-##gi$fix->id##";
                    $calculation2 = "=min(##gi$semcatitem->id##,max(##gi$fix->id##,38))-##gi$fix->id##-##gi$dobor1->id##";
                } else {
                    mtrace("Нет экзамена");
                    $calculation1 = "=##gi$semcatitem->id##-##gi$fix->id##";
                    $calculation2 = "=##gi$semcatitem->id##-##gi$fix->id##-##gi$dobor1->id##";
                }

                $changesmade = false;

                if ($dobor1->calculation == '=0') {
                    $dobor1->calculation = $calculation1;
                    $dobor1->update();
                    $dobor1->force_regrading();
                    $changesmade = true;
                    mtrace('Обновил добор1');
                }
                if ($dobor2->calculation == '=0') {
                    $dobor2->calculation = $calculation2;
                    $dobor2->update();
                    $dobor2->force_regrading();
                    $changesmade = true;
                    mtrace('Обновил добор2');
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
