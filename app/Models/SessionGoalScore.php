<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionGoalScore extends Model
{
    protected $fillable = ['session_report_id', 'iep_goal_id', 'score', 'notes'];
}