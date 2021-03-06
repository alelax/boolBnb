<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Apartament extends Model
{
    protected $fillable = [
        'title', 
        'beds_number', 
        'bathrooms_number', 
        'area', 
        'address', 
        'price'
    ];

    public function user(){
        return $this->belongsTo('App\User');
    }

    public function features()
    {
        return $this->belongsToMany('App\Model\Feature');
    }

    public function messages()
    {
        return $this->hasMany('App\Model\Message');
    }

    public function images()
    {
        return $this->hasMany('App\Model\Image');
    }

    public function advertisements()
    {
        return $this->belongsToMany('App\Model\Advertisement');
    }
}
