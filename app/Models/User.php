<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];

    public function userPackage() {
        return $this->hasMany(UserPackage::class, 'user_id', 'id');
    }

    public function websites()
    {
        return $this->hasMany(Website::class, 'user_id', 'id');
    }

    public function adminRole()
    {
        return $this->belongsTo(Role::class, 'admin_role_id');
    }

    public function merchantEmployees()
    {
        return $this->hasMany(MerchantEmployee::class, 'merchant_user_id');
    }

    public function merchantOwner()
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }

    public function staffEmployee()
    {
        return $this->hasOne(MerchantEmployee::class, 'user_id');
    }

    public function hasPermission(string $permission): bool
    {
        return app(\App\Services\RbacService::class)->hasPermission($this, $permission);
    }

    public function isSuperAdmin(): bool
    {
        return app(\App\Services\RbacService::class)->isSuperAdmin($this);
    }

    public function canAccessPlatform(): bool
    {
        return ! $this->trashed() && (bool) $this->status;
    }

    public static function findForApiAccess(int $userId): ?self
    {
        $user = static::withTrashed()->find($userId);

        return ($user && $user->canAccessPlatform()) ? $user : null;
    }

    public function revokePlatformAccess(): void
    {
        AccessToken::query()
            ->where('tokenable_id', $this->id)
            ->where('tokenable_type', self::class)
            ->delete();

        DB::table('sessions')->where('user_id', $this->id)->delete();
    }
    
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
        'password' => 'hashed',
        'status' => 'boolean'
    ];
}
