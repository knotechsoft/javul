<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['short_name','name'];

    /**
     * Get States for Country..
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function states(){
        return $this->hasMany('App\State');
    }

    /**
     * Get Cities of Country..
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function cities(){
        return $this->hasManyThrough('App\City','App\State');
    }

    /**
     * Get users of Country
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users(){
        return $this->hasMany('App\User');
    }
}