<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        $tasks = $user->tasks()
            ->whereNotNull('due_date')
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->get(['id', 'title', 'due_date', 'priority', 'status', 'task_type']);

        $cases = CaseModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'situation_summary', 'status', 'created_at']);

        return Inertia::render('Calendar', [
            'tasks' => $tasks,
            'cases' => $cases,
        ]);
    }

    public function ics(Request $request): HttpResponse
    {
        $user = auth()->user();

        // Get all incomplete tasks with due dates
        $tasks = $user->tasks()
            ->whereNotNull('due_date')
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->get(['id', 'title', 'due_date', 'priority', 'description', 'status']);

        // Create calendar
        $calendar = new Calendar();

        // Add each task as an event
        foreach ($tasks as $task) {
            $dueDate = new \DateTimeImmutable($task->due_date);

            $event = new Event(new UniqueIdentifier('task-' . $task->id . '@aura.app'));
            $event->setSummary($task->title);
            $event->setOccurrence(new SingleDay(new Date($dueDate)));

            // Add description with priority
            $priorityLabels = [
                'low' => 'Lav prioritet',
                'medium' => 'Normal prioritet',
                'high' => 'Høj prioritet',
                'critical' => 'Kritisk prioritet'
            ];

            $description = $priorityLabels[$task->priority] ?? 'Normal prioritet';
            if ($task->description) {
                $description .= "\n\n" . $task->description;
            }
            $event->setDescription($description);

            $calendar->addEvent($event);
        }

        // Generate ICS content
        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($calendar);
        $icsContent = $calendarComponent->__toString();

        // Return ICS file
        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="aura-frister.ics"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}
