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
        $semestridnumber = 'semestr';
        $itemstogenerate = [
            'fix' => [
                'name' => 'Баллы за семестр',
                'added' => 0,
                'skipped' => 0,
                'updated' => 0,
                'hidden' => 1,
                'calculation' => "=[[$semestridnumber]]",
            ],
            'dobor1' => [
                'name' => 'Добор 1',
                'added' => 0,
                'skipped' => 0,
                'updated' => 0,
                'hidden' => 0,
                'calculation' => '=0',
            ],
            'dobor2' => [
                'name' => 'Добор 2',
                'added' => 0,
                'skipped' => 0,
                'updated' => 0,
                'hidden' => 0,
                'calculation' => '=0',
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

                // Проверяем есть ли категория для подсчетов
                $semcategory = $DB->record_exists(
                    'grade_items',
                    ['courseid' => $course->id, 'idnumber' => $semestridnumber]
                );

                // Если такой еще нет, создаём
                if (!$semcategory) {
                    $newsemcat = new \grade_category();
                    $newsemcat->courseid = $course->id;
                    $newsemcat->fullname = 'Семестр';
                    $newsemcatid = $newsemcat->insert();

                    $catitem = \grade_item::fetch([
                        'courseid'     => $course->id,
                        'itemtype'     => 'category',
                        'iteminstance' => $newsemcatid,
                    ]);
                    $catitem->idnumber = $semestridnumber;
                    $catitem->update();
                    mtrace("Создал категорию для семестра (id=$newsemcatid) в курсе с id $course->id");
                }

                $sql = "SELECT cat.id
                    FROM {grade_categories} cat
                    JOIN {grade_items} i ON i.iteminstance = cat.id
                    WHERE 1=1
                        AND i.itemtype = 'category'
                        AND i.courseid = :courseid
                        AND i.idnumber LIKE :semestridnumber";
                $params = ['courseid' => $course->id, 'semestridnumber' => $semestridnumber];
                $semcatid = $DB->get_field_sql($sql, $params);

                // Суём весь верхний уровень в категорию
                $sql = "SELECT i.id, i.itemtype, i.iteminstance
                    FROM {grade_items} i
                    JOIN {course} c ON c.id = i.courseid
                    LEFT JOIN mdl_grade_categories cat ON cat.id = i.categoryid
                    WHERE 1=1
                      AND i.itemtype NOT LIKE 'course'
                      AND c.id = :courseid
                      AND cat.parent IS NULL
                      AND ((
                            i.idnumber NOT LIKE 'fix' AND
                            i.idnumber NOT LIKE 'dobor1' AND
                            i.idnumber NOT LIKE 'dobor2' AND
                            i.idnumber NOT LIKE :semestridnumber
                          ) OR i.idnumber IS NULL)
                      AND ((
                            i.itemname NOT LIKE '%бонус%' AND
                            i.itemname NOT LIKE '%Бонус%' AND
                            i.itemname NOT LIKE '%экзамен%' AND
                            i.itemname NOT LIKE '%Экзамен%'
                            ) OR i.itemname IS NULL)";
                $params = ['courseid' => $course->id, 'semestridnumber' => $semestridnumber];
                $toplevelitems = $DB->get_recordset_sql($sql, $params);

                mtrace("semcatid = $semcatid");
                foreach ($toplevelitems as $item) {
                    $item->categoryid = $semcatid;
                    $DB->update_record('grade_items', $item);

                    if ($item->itemtype == 'category') {
                        $newcategory = \grade_category::fetch(['id' => $item->iteminstance]);
                        if ($newcategory) {
                            $newcategory->parent = $semcatid;
                            $newcategory->update();
                            mtrace('Перетащил категорию');
                        }
                    }
                    mtrace("Добавил в категорию семестра ($semcatid) элемент с id $item->id");
                }
                $toplevelitems->close();

                // Генерим доборы и фикс
                foreach (['dobor1', 'dobor2', 'fix'] as $itemid) {
                    // Проверяем существование
                    $record = $DB->get_record('grade_items', [
                        'courseid' => $course->id,
                        'itemname' => $itemstogenerate[$itemid]['name'],
                    ]);

                    if ($record) {
                        // Если все ок, пропускаем
                        if ($record->idnumber === $itemid
                            && $record->hidden == $itemstogenerate[$itemid]['hidden']
                            && $record->multfactor == 0
                            && $record->weightoverride == 1
                            && $record->aggregationcoef == 0
                            && $record->aggregationcoef2 == 0
                            && $record->calculation === $itemstogenerate[$itemid]['calculation']) {
                            $itemstogenerate[$itemid]['skipped']++;
                            mtrace('Пропустил элемент с id '.$record->id);
                        } else {
                            // Подправляем, если какие-то настройки неверны
                            $record->idnumber = $itemid;
                            $record->hidden = $itemstogenerate[$itemid]['hidden'];
                            $record->multfactor = 0;
                            $record->weightoverride = 1;
                            $record->aggregationcoef = 0;
                            $record->aggregationcoef2 = 0;
                            $record->calculation = $itemstogenerate[$itemid]['calculation'];
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
                    $gradeitem->calculation = $itemstogenerate[$itemid]['calculation'];
                    $insertresult = $gradeitem->insert();

                    if ($insertresult) {
                        $itemstogenerate[$itemid]['added']++;
                        mtrace('Создал элемент с id '.$insertresult);
                    }
                }
            }
        }
        $categories->close();

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
