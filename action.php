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
 * Action page.
 *
 * @package     local_dobor
 * @copyright   2026 Solomonov Ifraim <solomonov@sfedu.ru>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../config.php');
require_once(__DIR__.'/lib.php');

require_login();
require_sesskey();

if (!local_dobor_can_access()) {  // Вызов из lib.php
    throw new \moodle_exception('nopermissions', 'error');
}

$PAGE->set_url('/local/dobor/action.php');
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('Dobor: Generate grades');
$PAGE->set_heading('Генерация оценок');

$success = false;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $result = local_dobor_generate_grades();  // Вызов функции из lib.php
    $success = true;
    \core\notification::add("Добавлено: {$result['added']}, пропущено: {$result['skipped']}", \core\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

if ($success) {
    echo \html_writer::div("Результат: " . json_encode($result), 'alert alert-success');
}

echo \html_writer::start_div('card mt-3');
echo \html_writer::start_div('card-body');
echo \html_writer::tag('h4', 'Генерировать "Добор 1"');
echo \html_writer::tag('p', 'Добавит в курсы с path /44/, где нет "Добор 1".');

echo \html_writer::start_tag('form', ['method' => 'post']);
echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo \html_writer::empty_tag('button', [
    'type' => 'submit',
    'class' => 'btn btn-primary btn-lg',
    'name' => 'generate'
], '🚀 Запустить генерацию');
echo \html_writer::end_tag('form');

echo \html_writer::end_div(); // card-body
echo \html_writer::end_div(); // card

echo $OUTPUT->footer();