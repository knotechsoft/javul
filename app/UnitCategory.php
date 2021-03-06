<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UnitCategory extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'unit_category';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name','status','parent_id'];

    /**
     * Get Unit of Category...
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function units(){
        return $this->hasMany('App\Unit','category_id');
    }

    public static function getName($id){
        if(!empty($id)){
            $tempid = explode(",",$id);
            if(count($tempid) > 1){
                $obj = self::whereIn('id',$tempid)->pluck('name')->all();
                return implode(",",$obj);
            }
            else{
                $obj = self::find($id);
                if(count($obj) > 0)
                    return $obj->name;
                return '-';
            }
        }

    }
}
