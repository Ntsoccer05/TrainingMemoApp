<?php

namespace App\Models;

use App\Models\RecordContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordMenu extends Model
{
    use HasFactory;
    protected $fillable = ['id', 'user_id', 'menu_id', 'category_id', 'record_state_id'];

    // 初期データ入力時にupdated_atカラムへのデータ挿入させなくする
    const UPDATED_AT = NULL;

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
    public function recordContents():HasMany
    {
        return $this->HasMany(RecordContent::class);
    }
}
