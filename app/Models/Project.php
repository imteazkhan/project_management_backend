<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'client',
        'progress',
        'team_id',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    // Real progress: the average completion of the project's top-level
    // tasks (each 0-100 as an employee works through it and a manager
    // approves it), not the static `progress` column, which only ever
    // moves when someone manually drags the settings slider.
    public function progressPercent(): int
    {
        $tasks = $this->relationLoaded('tasks')
            ? $this->tasks->whereNull('parent_id')
            : $this->tasks()->whereNull('parent_id')->get();

        if ($tasks->isEmpty()) {
            return 0;
        }

        return (int) round($tasks->avg('progress'));
    }
}
