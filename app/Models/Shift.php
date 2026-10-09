<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'hotel_id', 'name', 'start_time', 'end_time',
    ];

    public function employeeShifts()
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function getLabelAttribute(): string
    {
        return sprintf('%s (%s - %s)', $this->name, substr($this->start_time, 0, 5), substr($this->end_time, 0, 5));
    }
}
