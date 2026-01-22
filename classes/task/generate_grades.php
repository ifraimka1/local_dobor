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

    public static function instance(int $userid, string $path = '/2/'): self
    {
        $task = new self();
        $task->set_custom_data((object)['path' => $path]);
        // Можно указать пользователя, от имени которого выполняется задача.
        $task->set_userid($userid);
        return $task;
    }

    public function execute()
    {
        global $CFG;
        require_once($CFG->dirroot . '/local/dobor/lib.php');

        $data = $this->get_custom_data();
        $path = isset($data->path) ? $data->path : '/2/';

        // Вызов твоей логики
        $result = local_dobor_generate_grades(['path' => $path]);

        // Для логов cron’а
        mtrace($result);
    }
}
