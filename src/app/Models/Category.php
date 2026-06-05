<?php

namespace App\Models;

use App\Models\RecordMenu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'content'];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function menus():HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function recordMenus():HasMany
    {
        return $this->hasMany(RecordMenu::class);
    }

    public function recordContents():HasMany
    {
        return $this->hasMany(RecordContent::class);
    }
}
