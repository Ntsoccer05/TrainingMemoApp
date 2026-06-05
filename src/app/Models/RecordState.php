<?php

namespace App\Models;

use App\Models\RecordMenu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordState extends Model
{
    use HasFactory;

    // 初期データ入力時にupdated_atカラムへのデータ挿入させなくする
    const UPDATED_AT = NULL;
    
    protected $fillable = ['user_id','recorded_at'];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordMenus():HasMany
    {
        return $this->hasMany(RecordMenu::class);
    }

    public function recordContents():HasMany
    {
        return $this->hasMany(RecordContent::class);
    }

    public function menu():BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
