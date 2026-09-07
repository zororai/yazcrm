<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuccessStoryPhoto extends Model
{
    protected $fillable = ['success_story_id', 'path'];

    public function successStory()
    {
        return $this->belongsTo(SuccessStory::class);
    }
}
