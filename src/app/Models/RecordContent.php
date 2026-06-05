<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordContent extends Model
{
    use HasFactory;

    protected $fillable = ['record_menu_id', 'weight','right_weight','left_weight', 'set', 'rep', 'memo'];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category():BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function menu():BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function recordState():BelongsTo
    {
        return $this->belongsTo(RecordState::class);
    }
    public function recordMenu():BelongsTo
    {
        return $this->belongsTo(RecordMenu::class);
    }
}
