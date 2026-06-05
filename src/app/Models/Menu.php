<?php

namespace App\Models;

use App\Models\RecordMenu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'content', 'category_id', 'oneSide'];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category():BelongsTo
    {
        return $this->belongsTo(Category::class);
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
