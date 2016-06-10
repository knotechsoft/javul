<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskAction extends Model
{
	
	
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task_actions';
	
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id','task_id','name','description','status'];
}
