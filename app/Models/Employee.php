<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'avatar',
        'department_id',
        'designation_id',
        'manager_id',
        'joining_date',
        'status',
        'is_manager',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'is_manager' => 'boolean',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_id');
    }
}
