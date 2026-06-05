<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\RecordMenu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Mail\BareMail;
use App\Notifications\PasswordResetNotification;
use Filament\Models\Contracts\FilamentUser;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // fillableに追加したものがfilamentで変更・更新できる
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    public function canAccessFilament(): bool
    {
        return str_ends_with($this->email, '@gmail.com');
    }
    public function categories():HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function recordMenus():HasMany
    {
        return $this->hasMany(RecordMenu::class);
    }

    public function recordContents():HasMany
    {
        return $this->hasMany(RecordContent::class);
    }
    public function recordStates():HasMany
    {
        return $this->hasMany(RecordState::class);
    }
    public function menus():HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new PasswordResetNotification($token, new BareMail()));
    }
}
