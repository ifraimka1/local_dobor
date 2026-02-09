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

require(__DIR__.'/../../config.php');
require_once($CFG->dirroot . '/local/dobor/lib.php');

require_login();

$PAGE->set_url('/local/dobor/dobor_init.php');
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('Dobor: Generate grades');
$PAGE->set_heading('Генерация оценок');

$categorypath = optional_param('categorypath', '', PARAM_TEXT);
$success = false;
$success_calculation = false;
$success_retakes = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate']) && confirm_sesskey()) {
    $task = \local_dobor\task\generate_grades::instance($USER->id, $categorypath);

    \core\task\manager::queue_adhoc_task($task, true);

    \core\notification::add(
        'Задача на генерацию оценок поставлена в очередь. Она будет выполнена при следующем запуске cron.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    $success = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_calculation']) && confirm_sesskey()) {
    $task = \local_dobor\task\set_dobor_calculation::instance($USER->id, $categorypath);

    \core\task\manager::queue_adhoc_task($task, true);

    \core\notification::add(
        'Задача на расчет добора поставлена в очередь. Она будет выполнена при следующем запуске cron.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    $success_calculation = true;
}

echo $OUTPUT->header();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_ekz_dobor']) && confirm_sesskey()) {
    $task = \local_dobor\task\generate_ekz_dobor::instance($USER->id, $categorypath);  // Новая задача
    \core\task\manager::queue_adhoc_task($task, true);
    \core\notification::add(
        'Задача на создание пересдач поставлена в очередь.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    $success_retakes = true;
}

if ($success) {
    echo \html_writer::div('Запуск генерации инициирован.', 'alert alert-info');
}
if ($success_calculation) {
    echo \html_writer::div('Расчет добора запущен.', 'alert alert-info');
}
if ($success_retakes) {
    echo \html_writer::div('Создание пересдач запущено.', 'alert alert-info');
}

echo \html_writer::start_div('card mt-3');
echo \html_writer::start_div('card-body');
echo \html_writer::tag('h4', 'Генерировать "Добор 1/2" и "Баллы за семестр"');
echo \html_writer::tag('p', 'Будут созданы оценки во всех курсах подходящих категорий. Операция выполняется в фоне через ad-hoc задачу.');

echo \html_writer::start_tag('form', ['method' => 'post']);
echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo \html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'categorypath',
    'id' => 'categorypath',
    'class' => 'form-control mb-3',
    'value' => $categorypath,
    'placeholder' => 'введите id категории',
    'required' => true
]);
echo \html_writer::tag('button', 'Запустить генерацию', [
    'type' => 'submit',
    'class' => 'btn btn-primary btn-lg',
    'name' => 'generate'
]);
echo \html_writer::end_tag('form');

echo \html_writer::end_div();
echo \html_writer::end_div();

// Для задачи set_dobor_calculation
echo \html_writer::start_div('card mt-3');
echo \html_writer::start_div('card-body');
echo \html_writer::tag('h4', 'Расчет добора');
echo \html_writer::tag('p', 'Будут расчитаны значения добора во всех курсах подходящих категорий. Операция выполняется в фоне через ad-hoc задачу.');

echo \html_writer::start_tag('form', ['method' => 'post']);
echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo \html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'categorypath',
    'id' => 'categorypath_calculation',
    'class' => 'form-control mb-3',
    'value' => $categorypath,
    'placeholder' => 'введите id категории',
    'required' => true
]);
echo \html_writer::tag('button', 'Запустить расчет добора', [
    'type' => 'submit',
    'class' => 'btn btn-primary btn-lg',
    'name' => 'set_calculation'
]);
echo \html_writer::end_tag('form');

echo \html_writer::end_div();
echo \html_writer::end_div();

// Для создания пересдач
echo \html_writer::start_div('card mb-4');
echo \html_writer::start_div('card-body');
echo \html_writer::tag('h4', '3. Создание пересдач');
echo \html_writer::tag('p', 'Будут созданы задания пересдач во всех курсах подходящих категорий.');
echo \html_writer::start_tag('form', ['method' => 'post']);
echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo \html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'categorypath',
    'class' => 'form-control mb-3',
    'placeholder' => '/2/',
    'required' => true
]);
echo \html_writer::tag('button', '🔄 Запустить создание пересдач', [
    'type' => 'submit',
    'class' => 'btn btn-warning',
    'name' => 'generate_ekz_dobor'
]);
echo \html_writer::end_tag('form');
echo \html_writer::end_div();
echo \html_writer::end_div();

echo $OUTPUT->footer();
